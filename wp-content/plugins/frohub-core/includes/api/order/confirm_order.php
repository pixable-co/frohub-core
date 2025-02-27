<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class ConfirmOrder {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/confirm-order', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_confirm_order'],
            'permission_callback' => function () {
                return is_user_logged_in(); // Ensures only authenticated users can confirm orders
            },
            'args'     => [
                'order_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
            ],
        ]);
    }

    /**
     * Handles order confirmation and updates WooCommerce order status.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_confirm_order(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Get order ID from the request
        $order_id = intval($request->get_param('order_id'));

        // Get the WooCommerce order object
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid order ID.',
            ], 404);
        }

        // Check if order is already in processing or completed status
        $current_status = $order->get_status();
        if (in_array($current_status, ['processing', 'completed'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Order #$order_id is already in '$current_status' status.",
            ], 400);
        }

        // Update order status to "processing"
        $order->update_status('processing', 'Order confirmed via API.');
        $order->save();

        return new \WP_REST_Response([
            'success' => true,
            'message' => "Order #$order_id confirmed successfully and is now processing.",
        ], 200);
    }
}

// Initialize the class
ConfirmOrder::init();
