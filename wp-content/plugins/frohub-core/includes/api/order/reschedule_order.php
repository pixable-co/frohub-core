<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class RescheduleOrder {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/reschedule-order', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_reschedule_order'],
            'permission_callback' => function () {
                return is_user_logged_in(); // Ensures authentication
            },
            'args'     => [
                'order_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
                'date' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param); // YYYY-MM-DD format validation
                    }
                ],
                'start_time' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return preg_match('/^\d{2}:\d{2}$/', $param); // HH:MM format validation
                    }
                ],
                'end_time' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return preg_match('/^\d{2}:\d{2}$/', $param); // HH:MM format validation
                    }
                ],
            ],
        ]);
    }

    /**
     * Handles order rescheduling.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_reschedule_order(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Get parameters from the request
        $order_id   = intval($request->get_param('order_id'));
        $date       = sanitize_text_field($request->get_param('date'));
        $start_time = sanitize_text_field($request->get_param('start_time'));
        $end_time   = sanitize_text_field($request->get_param('end_time'));

        // Get the WooCommerce order object
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid order ID.',
            ], 404);
        }

        // Ensure the order is not already completed or canceled
        $current_status = $order->get_status();
        if (in_array($current_status, ['completed', 'cancelled'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Order #$order_id cannot be rescheduled because it is already '$current_status'.",
            ], 400);
        }

        // Update order item meta (assuming first item)
        $order_items = $order->get_items();
        $item_id = null;

        foreach ($order_items as $item_key => $item) {
            $item_id = $item_key;
            break; // Assume first item, adjust if needed
        }

        if (!$item_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No order items found.',
            ], 400);
        }

        // Update meta data
        wc_update_order_item_meta($item_id, 'Selected Date', $date);
        wc_update_order_item_meta($item_id, 'Selected Time', $start_time . ' - ' . $end_time);

        // Fetch updated values correctly
        $selected_date = wc_get_order_item_meta($item_id, 'Selected Date', true);
        $selected_time = wc_get_order_item_meta($item_id, 'Selected Time', true);

        if (!$selected_date || !$selected_time) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to retrieve updated booking details.',
            ], 500);
        }

        // Extract start and end time safely
        $time_parts = explode(' - ', $selected_time);
        $start_time = $time_parts[0] ?? '';
        $end_time   = $time_parts[1] ?? '';

        if (!$start_time || !$end_time) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid time format. Expected "HH:MM - HH:MM".',
            ], 400);
        }

        // Format the date-time values for ACF (if needed)
        $appointment_start = $selected_date . ' ' . $start_time;
        $appointment_end = $selected_date . ' ' . $end_time;

        // Check if ACF is active before updating fields
        if (function_exists('update_field')) {
            update_field('appointment_start', $appointment_start, $order_id);
            update_field('appointment_end', $appointment_end, $order_id);
        }

        // Update order status to "rescheduling"
        $order->update_status('wc-rescheduling', 'Order rescheduled via API.');

        // Save changes
        $order->save();

        // Return a success response
        return new \WP_REST_Response([
            'success' => true,
            'message' => "Order #$order_id rescheduled to $selected_date from $start_time to $end_time.",
        ], 200);
    }
}

// Initialize the class
RescheduleOrder::init();
