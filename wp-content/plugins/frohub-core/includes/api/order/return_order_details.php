<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReturnOrderDetails {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/return-order-details', [
            'methods'             => 'POST',
            'callback'            => array($this, 'get_orders_by_partner_id'),
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce'); // Ensures only authorized users can fetch orders
            },
            'args'                => [
                'partner_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    /**
     * Fetches WooCommerce orders filtered by partner ID.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_orders_by_partner_id(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return rest_ensure_response(['error' => 'WooCommerce is not active.'], 400);
        }

        // Validate partner ID
        if (empty($partner_id)) {
            return rest_ensure_response(['error' => 'Partner ID is required.'], 400);
        }

        // Query orders with the matching ACF field
        $query_args = [
            'post_type'   => 'shop_order',
            'post_status' => 'any',
            'meta_query'  => [
                [
                    'key'     => 'partner_id',
                    'value'   => $partner_id,
                    'compare' => '=',
                ],
            ],
        ];

        $orders = get_posts($query_args);

        if (empty($orders)) {
            return rest_ensure_response(['error' => 'No orders found for the given Partner ID.'], 404);
        }

        $orders_data = [];

        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);

            if (!$order) {
                continue;
            }

            // Initialize booking fields
            $booking_day = '';
            $booking_start_time_slot = '';
            $booking_end_time_slot = '';
            $partner_id = '';

            // Loop through order items and fetch meta data
            foreach ($order->get_items() as $item_id => $item) {
                $booking_day = wc_get_order_item_meta($item_id, 'Selected Date', true) ?: '';
                $booking_start_time_slot = wc_get_order_item_meta($item_id, 'Selected Time', true) ?: '';
                $booking_end_time_slot = wc_get_order_item_meta($item_id, 'booking_end_time_slot', true) ?: '';
                $partner_id = wc_get_order_item_meta($item_id, 'partner_id', true) ?: '';
            }

            // Construct order data
            $order_data = [
                'id'         => $order->get_id(),
                'status'     => $order->get_status(),
                'total'      => $order->get_total(),
                'currency'   => $order->get_currency(),
                'created_at' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'billing'    => $order->get_address('billing'),
                'shipping'   => $order->get_address('shipping'),
                'acf_fields' => [
                    'service_type'            => get_post_meta($order_post->ID, 'service_type', true),
                    'booking_day'             => $booking_day,
                    'booking_start_time_slot' => $booking_start_time_slot,
                    'booking_end_time_slot'   => $booking_end_time_slot,
                    'partner_id'              => $partner_id,
                ],
                'line_items' => [],
            ];

            // Loop through order items again to build line items
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();

                $order_data['line_items'][] = [
                    'product_id'   => $item->get_product_id(),
                    'product_name' => $product ? $product->get_name() : '',
                    'name'         => $item->get_name(),
                    'quantity'     => $item->get_quantity(),
                    'total'        => $item->get_total(),
                    'sku'          => $product ? $product->get_sku() : '',
                ];
            }

            $orders_data[] = $order_data;
        }

        return rest_ensure_response($orders_data);
    }
}
