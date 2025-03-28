<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerOrders {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/customer-orders', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request to retrieve customer orders.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $customer_id = $request->get_param('customer_id');

        if ( ! $customer_id || ! get_userdata($customer_id) ) {
            return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
        }

        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'limit'       => -1,
        ]);

        if (empty($orders)) {
            return new \WP_REST_Response(['message' => 'No orders found'], 200);
        }

        $order_data = [];

        foreach ($orders as $order) {
            $order_id = $order->get_id();

            $review = get_field('review', $order_id);
            $review_id = is_object($review) ? $review->ID : null;
            $overall_rating = $review_id ? get_field('overall_rating', $review_id) : null;

            $order_data[] = [
                'order_id'        => $order_id,
                'status'          => $order->get_status(),
                'total'           => $order->get_formatted_order_total(),
                'order_date'      => $order->get_date_created()->date('Y-m-d H:i:s'),
                'items'           => $this->get_order_items($order),
                'overall_rating'  => $overall_rating ?: null,
            ];
        }

        return new \WP_REST_Response($order_data, 200);
    }

    /**
     * Helper method to get order items with metadata, excluding product ID 2600.
     *
     * @param \WC_Order $order
     * @return array
     */
    private function get_order_items($order) {
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $product ? $product->get_id() : null;

            if ($product_id == 2600) {
                continue;
            }

            $meta_data = [];
            foreach ($item->get_meta_data() as $meta) {
                $meta_data[$meta->key] = $meta->value;
            }

            $items[] = [
                'product_id'   => $product_id,
                'product_name' => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'total_paid'   => wc_price($item->get_subtotal()),
                'meta_data'    => $meta_data,
            ];
        }

        return $items;
    }
}
