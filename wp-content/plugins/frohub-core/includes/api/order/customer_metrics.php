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

        // Basic validation
        if (!$customer_id || !get_userdata($customer_id)) {
            return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
        }
        if (!$partner_id) {
            return new \WP_REST_Response(['error' => 'Missing partner_id'], 400);
        }

        // Fetch completed orders for this customer that match the partner on the ORDER's ACF meta
        // ACF stores fields as post meta, so we can filter via meta_query.
        // If your ACF field stores an integer ID, ensure $partner_id is a string to match meta_value.
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status'      => 'completed',
            'limit'       => -1,
            'meta_query'  => [
                [
                    'key'     => 'partner_name',     // ACF field key on the order
                    'value'   => (string) $partner_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $total_spent       = 0.0;
        $completed_orders  = count($orders);
        $first_order_dt    = null; // earliest completed order date with this partner

        foreach ($orders as $order) {
            // Track earliest order date
            $created = $order->get_date_created();
            if ($created) {
                $ts = $created->getTimestamp();
                if ($first_order_dt === null || $ts < $first_order_dt) {
                    $first_order_dt = $ts;
                }
            }

            // Calculate order total excluding booking fee product and including "Total Due on the Day"
            $order_total = 0.0;

            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();

                // Skip Frohub Booking Fee (product ID 28990)
                if ((int) $product_id === 28990) {
                    continue;
                }

                // Item subtotal (ex tax, before coupons)
                $subtotal = (float) $item->get_subtotal();
                $order_total += $subtotal;

                // Include "Total Due on the Day" from item meta if present
                $meta_total_due = $item->get_meta('Total Due on the Day', true);
                if (!empty($meta_total_due)) {
                    // Strip currency symbols and commas, keep decimal point
                    $clean = preg_replace('/[^\d.]/', '', (string) $meta_total_due);
                    if ($clean !== '' && is_numeric($clean)) {
                        $order_total += (float) $clean;
                    }
                }
            }

            $total_spent += $order_total;
        }

        // Get customer data
        $user      = get_userdata($customer_id);
        $customer  = new \WC_Customer($customer_id);

        $first_name   = $user ? $user->first_name : '';
        $last_name    = $user ? $user->last_name : '';
        $user_id      = $user ? $user->ID : 0;
        $phone_number = $customer ? $customer->get_billing_phone() : '';

        // Format totals & first order date with this partner
        $formatted_total_spent = '£' . number_format($total_spent, 2);
        $first_order_with_partner = $first_order_dt
            ? gmdate('Y-m-d H:i:s', $first_order_dt)
            : ''; // empty if none

        return new \WP_REST_Response([
            'customer_id'               => (int) $customer_id,
            'partner_id'                => (string) $partner_id,
            'user_id'                   => (int) $user_id,
            'first_name'                => $first_name,
            'last_name'                 => $last_name,
            'phone_number'              => $phone_number,
            'total_spent'               => $formatted_total_spent,
            'completed_orders'          => $completed_orders,
            'first_order_with_partner'  => $first_order_with_partner,
        ], 200);
    }
}
