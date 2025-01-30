<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrdersByPartnerId {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('custom/v1', '/orders', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'get_orders_by_partner_id'),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_orders_by_partner_id(WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response(['error' => 'WooCommerce is not active.'], 400);
        }

        if (empty($partner_id)) {
            return new WP_REST_Response(['error' => 'Partner ID is required.'], 400);
        }

        // Query orders with the matching ACF field
        $query_args = [
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => 'partner_id',
                    'value' => $partner_id,
                    'compare' => '=',
                ],
            ],
        ];

        $orders = get_posts($query_args);

        if (empty($orders)) {
            return new WP_REST_Response(['error' => 'No orders found for the given Partner ID.'], 404);
        }

        $orders_data = [];

        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);

            $order_data = [
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'created_at' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'billing' => $order->get_address('billing'),
                'shipping' => $order->get_address('shipping'),
                'acf_fields' => [
                    'service_type' => get_post_meta($order_post->ID, 'service_type', true),
                    'booking_day' => get_post_meta($order_post->ID, 'booking_day', true),
                    'booking_start_time_slot' => get_post_meta($order_post->ID, 'booking_start_time_slot', true),
                    'booking_end_time_slot' => get_post_meta($order_post->ID, 'booking_end_time_slot', true),
                    'partner_id' => get_post_meta($order_post->ID, 'partner_id', true),
                ],
                'line_items' => [],
            ];

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();

                $order_data['line_items'][] = [
                    'product_id' => $item->get_product_id(),
                    'product_name' => $product ? $product->get_name() : '',
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'sku' => $product ? $product->get_sku() : '',
                ];
            }

            $orders_data[] = $order_data;
        }

        return new WP_REST_Response($orders_data, 200);
    }
}