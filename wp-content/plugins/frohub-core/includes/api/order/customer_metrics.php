<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class CustomerMetrics
{
    public static function init()
    {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes()
    {
        register_rest_route('frohub/v1', '/customer-metrics', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_request'),
            'permission_callback' => '__return_true', // TODO: lock this down
        ));
    }

    /**
     * Handles the API request to get customer metrics for a specific partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
public function handle_request(\WP_REST_Request $request)
{
    $customer_id = $request->get_param('customer_id');
    $partner_id  = $request->get_param('partner_id');

    if (!$customer_id || !get_userdata($customer_id)) {
        return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
    }
    if (!$partner_id) {
        return new \WP_REST_Response(['error' => 'Missing partner_id'], 400);
    }

    // Fetch ALL completed orders for this customer.
    // We'll filter at the line-item level to ensure per-partner accuracy.
    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'status'      => 'completed',
        'limit'       => -1,
    ]);

    $total_spent_per_partner = 0.0;
    $completed_orders_with_partner = 0; // number of orders that have >=1 matching line item
    $first_order_ts = null;

    foreach ($orders as $order) {
        $order_has_partner_item = false;
        $order_partner_sum = 0.0;

        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();

            // Skip Frohub Booking Fee product
            if ($product_id === 28990) {
                continue;
            }

            /**
             * Match the item to the partner.
             * Depending on your setup, partner might be stored as:
             * - $item->get_meta('partner_name')  (string ID or text)
             * - $item->get_meta('partner_id')    (numeric ID)
             * If you *know* the exact meta key, keep only that check.
             */
            $item_partner_raw = $item->get_meta('partner_id', true);
            if ($item_partner_raw === '' || $item_partner_raw === null) {
                $item_partner_raw = $item->get_meta('partner_name', true);
            }

            // Normalize both sides to strings for comparison
            $matches_partner = (string) $item_partner_raw !== '' && (string) $item_partner_raw === (string) $partner_id;
            if (!$matches_partner) {
                continue;
            }

            // This item belongs to the requested partner — include it.
            $order_has_partner_item = true;

            // Item subtotal (ex tax, before coupons) for this line
            $subtotal = (float) $item->get_subtotal();
            $order_partner_sum += $subtotal;

            // Include "Total Due on the Day" if present on this item
            $meta_total_due = $item->get_meta('Total Due on the Day', true);
            if (!empty($meta_total_due)) {
                $clean = preg_replace('/[^\d.]/', '', (string) $meta_total_due);
                if ($clean !== '' && is_numeric($clean)) {
                    $order_partner_sum += (float) $clean;
                }
            }
        }

        if ($order_has_partner_item) {
            $total_spent_per_partner += $order_partner_sum;
            $completed_orders_with_partner++;

            $created = $order->get_date_created();
            if ($created) {
                $ts = $created->getTimestamp();
                if ($first_order_ts === null || $ts < $first_order_ts) {
                    $first_order_ts = $ts;
                }
            }
        }
    }

    // Customer basics
    $user      = get_userdata($customer_id);
    $customer  = new \WC_Customer($customer_id);

    $first_name   = $user ? $user->first_name : '';
    $last_name    = $user ? $user->last_name : '';
    $user_id      = $user ? (int) $user->ID : 0;
    $phone_number = $customer ? $customer->get_billing_phone() : '';

    $formatted_total_spent = '£' . number_format($total_spent_per_partner, 2);
    $first_order_with_partner = $first_order_ts ? gmdate('Y-m-d H:i:s', $first_order_ts) : '';

    return new \WP_REST_Response([
        'customer_id'               => (int) $customer_id,
        'partner_id'                => (string) $partner_id,
        'user_id'                   => $user_id,
        'first_name'                => $first_name,
        'last_name'                 => $last_name,
        'phone_number'              => $phone_number,
        'total_spent'               => $formatted_total_spent,           // <-- now strictly per-partner
        'completed_orders'          => $completed_orders_with_partner,    // count of orders with that partner
        'first_order_with_partner'  => $first_order_with_partner,
    ], 200);
}

}
