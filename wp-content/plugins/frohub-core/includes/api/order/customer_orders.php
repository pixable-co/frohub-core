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

    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/customer-orders', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_request(\WP_REST_Request $request) {
        $customer_id = (int) $request->get_param('customer_id');
        $partner_id  = (int) $request->get_param('partner_id');

        if ( ! $customer_id || ! get_userdata($customer_id) ) {
            return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
        }

        if ( ! $partner_id ) {
            return new \WP_REST_Response(['error' => 'Missing partner ID'], 400);
        }

        // Only orders for this customer and matching partner_id
      $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'limit'       => -1,
            'meta_key'    => 'partner_id',
            'meta_value'  => $partner_id,
            'meta_compare'=> '=',
        ]);

        if (empty($orders)) {
            return new \WP_REST_Response([], 200);
        }

        $order_data = [];

        foreach ($orders as $order) {
            $order_data[] = [
                'order_id'       => $order->get_id(),
                'status'         => $order->get_status(),
                'total'          => $order->get_formatted_order_total(),
                'order_date'     => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'items'          => $this->get_order_items($order),
                'overall_rating' => $this->get_overall_rating_from_order($order->get_id()),
            ];
        }

        return new \WP_REST_Response($order_data, 200);
    }

    private function get_overall_rating_from_order($order_id) {
        $review = get_field('review', $order_id);
        $review_id = is_object($review) ? $review->ID : null;
        return $review_id ? get_field('overall_rating', $review_id) : null;
    }

    private function get_order_items($order) {
        $items = [];

        foreach ($order->get_items() as $item) {
            $product   = $item->get_product();
            $product_id = $product ? $product->get_id() : null;

            if ($product_id == 28990) {
                continue; // Skip booking fee
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
