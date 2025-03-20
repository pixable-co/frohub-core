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
                'formatted_proposed_end_datetime' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $param); // YYYY-MM-DD HH:MM format
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
        $formatted_proposed_end_datetime = sanitize_text_field($request->get_param('formatted_proposed_end_datetime'));

        // Format the proposed start date time
        $formatted_proposed_start_datetime = date('H:i, d M Y', strtotime("$date $start_time"));

        // Format the proposed end date time to match the start date format
        $formatted_proposed_end_datetime = date('H:i, d M Y', strtotime($formatted_proposed_end_datetime));

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

        // Store the formatted proposed start and end times in order meta
        wc_update_order_item_meta($item_id, 'Proposed Start Date Time', $formatted_proposed_start_datetime);
        wc_update_order_item_meta($item_id, 'Proposed End Date Time', $formatted_proposed_end_datetime);

        // Fetch updated values correctly
        $proposed_start_datetime = wc_get_order_item_meta($item_id, 'Proposed Start Date Time', true);
        $proposed_end_datetime = wc_get_order_item_meta($item_id, 'Proposed End Date Time', true);

        if (!$proposed_start_datetime || !$proposed_end_datetime) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to retrieve updated proposed booking details.',
            ], 500);
        }

        // Update order status to "rescheduling"
        $order->update_status('wc-rescheduling', 'Order rescheduled with proposed times via API.');

        // Save changes
        $order->save();

        // Return a success response
        return new \WP_REST_Response([
            'success' => true,
            'message' => "Order #$order_id rescheduled with proposed times: $proposed_start_datetime to $proposed_end_datetime.",
            'proposed_start_datetime' => $proposed_start_datetime,
            'proposed_end_datetime' => $proposed_end_datetime,
        ], 200);
    }
}
