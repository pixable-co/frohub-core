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
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request to get customer metrics.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request)
    {
        $customer_id = $request->get_param('customer_id');

        if (!$customer_id || !get_userdata($customer_id)) {
            return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
        }

        // Fetch completed orders for the customer
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => 'completed',
            'limit' => -1,
        ]);

        $total_spent = 0;
        $completed_orders = count($orders);

        foreach ($orders as $order) {
            $order_total = 0;

            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();

                // Skip Frohub Booking Fee (product ID 28990)
                if ($product_id == 28990) {
                    continue;
                }

                $subtotal = floatval($item->get_subtotal());
                $order_total += $subtotal;

                $meta_total_due = $item->get_meta('Total Due on the Day', true);
                if ($meta_total_due) {
                    $meta_total_due = floatval(preg_replace('/[^\d.]/', '', $meta_total_due));
                    $order_total += $meta_total_due;
                }
            }

            $total_spent += $order_total;
        }

        // Get customer data
        $user = get_userdata($customer_id);
        $customer = new \WC_Customer($customer_id);
        $customer_since = $customer->get_date_created();
        $customer_since = $customer_since ? $customer_since->date('Y-m-d H:i:s') : '';

        $first_name = $user->first_name;
        $last_name = $user->last_name;
        $user_id = $user->ID;
        $phone_number = $customer->get_billing_phone();

        // Format total spent
        $formatted_total_spent = '£' . number_format($total_spent, 2);

        return new \WP_REST_Response([
            'customer_id' => $customer_id,
            'user_id' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone_number' => $phone_number,
            'total_spent' => $formatted_total_spent,
            'completed_orders' => $completed_orders,
            'customer_since' => $customer_since,
        ], 200);
    }

}
