<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateOrderStatus {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/update-order-status', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request to update the order status.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $order_id   = $request->get_param('order_id');
        $new_status = $request->get_param('status');

        if (empty($order_id)) {
            return new \WP_REST_Response(['message' => 'Missing order ID.'], 400);
        }

        if (empty($new_status)) {
            return new \WP_REST_Response(['message' => 'Missing status.'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_REST_Response(['message' => 'Invalid order ID.'], 404);
        }

        $valid_statuses = wc_get_order_statuses();
        $valid_statuses = array_map(function($status) {
            return str_replace('wc-', '', $status);
        }, array_keys($valid_statuses));

        if (!in_array($new_status, $valid_statuses)) {
            return new \WP_REST_Response([
                'message' => 'Invalid order status. Allowed: ' . implode(', ', $valid_statuses)
            ], 400);
        }

        $order->update_status($new_status, 'Order updated via API.');

        return new \WP_REST_Response([
            'message'     => "Order status updated to '$new_status' successfully!",
            'order_id'    => $order_id,
            'new_status'  => $new_status,
        ], 200);
    }
}
