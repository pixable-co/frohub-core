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
//             'permission_callback' => function () {
//                 return current_user_can('manage_woocommerce'); // Ensures only authorized users can fetch orders
//             },
            'permission_callback' => '__return_true', // ⚠️ Be careful, this makes it public!
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
    
        // Query orders with the matching partner_id meta field
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

            // Get Conversation Post
            $conversation_post = get_field('conversation', $order->get_id());
            $partner_conversation_id =  get_field("partner_client_post_id",$conversation_post->ID);
    
            $order_data = [
                'id'         => $order->get_id(),
                'status'     => $order->get_status(),
                'cancellation_status' => get_field('cancellation_status', $order->get_id()),
                'total'      => $order->get_total(),
                'currency'   => $order->get_currency(),
                'created_at' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'customer_id' => $order->get_customer_id(),
                'customer_note' => $order->get_customer_note(),
                'billing'    => $order->get_address('billing'),
                'shipping'   => $order->get_address('shipping'),
                'line_items' => [],
                'partner_conversation_id' => $partner_conversation_id,
            ];
    
            // Loop through order items and retrieve stored meta
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $product_id = $item->get_product_id();
    
                // Ignore product with ID 28990
                if ($product_id == 28990) {
                    continue;
                }
    
                // Retrieve stored product meta from order item
                $selected_add_ons = wc_get_order_item_meta($item_id, 'selected_add_ons', true) ?: '';
                $deposit_due = wc_get_order_item_meta($item_id, 'Total Due on the Day', true) ?: '';
                $service_type = wc_get_order_item_meta($item_id, 'pa_service-type', true) ?: '';
                $booking_date = wc_get_order_item_meta($item_id, 'Start Date Time', true) ?: '';
                $booking_time = wc_get_order_item_meta($item_id, 'Selected Time', true) ?: '';
                $duration= wc_get_order_item_meta($item_id, 'Duration', true) ?: '';

                // Extract time and date separately from booking_date (Start Date Time)
                $extracted_time = '';
                $extracted_date = '';

                if (!empty($booking_date)) {
                    $booking_parts = explode(', ', $booking_date, 2);
                    $extracted_time = isset($booking_parts[0]) ? trim($booking_parts[0]) : ''; // Time
                    $extracted_date = isset($booking_parts[1]) ? trim($booking_parts[1]) : ''; // Date
                }
    
                $order_data['line_items'][] = [
                    'product_id'   => $product_id,
                    'product_name' => $product ? $product->get_name() : '',
                    'name'         => $item->get_name(),
                    'quantity'     => $item->get_quantity(),
                    'total'        => $item->get_total(),
                    'sku'          => $product ? $product->get_sku() : '',
                    'meta'         => [
                        'selected_add_ons' => $selected_add_ons,
                        'deposit_due'      => $deposit_due,
                        'service_type'     => $service_type,
                        'booking_date'     => $booking_date,
                        'booking_time'     => $extracted_time,
                        'duration'         => $duration,
                    ],
                ];
            }
    
            $orders_data[] = $order_data;
        }
    
        return rest_ensure_response($orders_data);
    }
     
}
