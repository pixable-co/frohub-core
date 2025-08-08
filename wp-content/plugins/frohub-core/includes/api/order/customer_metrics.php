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

    public function register_rest_routes()
    {
        register_rest_route('frohub/v1', '/customer-metrics', array(
            'methods'  => 'POST',
            'callback' => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_request(\WP_REST_Request $request)
    {
        $customer_id = (int) $request->get_param('customer_id');
        $partner_id  = (int) $request->get_param('partner_id');

        if (!$customer_id || !get_userdata($customer_id)) {
            return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
        }

        if (!$partner_id) {
            return new \WP_REST_Response(['error' => 'Missing partner ID'], 400);
        }

        // Query only completed orders for this customer with matching partner_id
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status'      => 'completed',
            'limit'       => -1,
            'meta_key'    => 'partner_id',
            'meta_value'  => $partner_id,
            'meta_compare'=> '=',
        ]);

        $total_spent       = 0.0;
        $completed_orders  = count($orders);
        $first_order_ts    = null;

        foreach ($orders as $order) {
            $created = $order->get_date_created();
            if ($created) {
                $ts = $created->getTimestamp();
                if ($first_order_ts === null || $ts < $first_order_ts) {
                    $first_order_ts = $ts;
                }
            }

            $order_total = 0.0;
            foreach ($order->get_items() as $item) {
                if ((int)$item->get_product_id() === 28990) {
                    continue; // skip Frohub booking fee
                }

                $order_total += (float) $item->get_subtotal();

                $meta_total_due = $item->get_meta('Total Due on the Day', true);
                if ($meta_total_due) {
                    $meta_total_due = (float) preg_replace('/[^\d.]/', '', (string)$meta_total_due);
                    $order_total += $meta_total_due;
                }
            }

            $total_spent += $order_total;
        }

        $user         = get_userdata($customer_id);
        $wc_customer  = new \WC_Customer($customer_id);

        $response = [
            'customer_id'      => $customer_id,
            'user_id'          => $user->ID,
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'phone_number'     => $wc_customer->get_billing_phone(),
            'total_spent'      => '£' . number_format($total_spent, 2),
            'completed_orders' => $completed_orders,
            'customer_since'   => $first_order_ts ? gmdate('Y-m-d H:i:s', $first_order_ts) : '',
            'partner_id'       => $partner_id,
        ];

        return new \WP_REST_Response($response, 200);
    }
}
