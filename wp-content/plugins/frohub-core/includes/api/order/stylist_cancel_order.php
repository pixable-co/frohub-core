<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StylistCancelOrder {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/stylist-cancel-order', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'order_id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    },
                ),
            ),
        ));
    }

    /**
     * Handles the API request to cancel the order.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $order_id = intval($request->get_param('order_id'));

        $order = wc_get_order($order_id);

        // Extract customer info
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();
        
        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid order ID. Order not found.',
            ], 404);
        }

        $current_status = $order->get_status();
        if (in_array($current_status, ['cancelled', 'completed'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Order #$order_id cannot be cancelled because it is already '$current_status'.",
            ], 400);
        }

        $order->update_status('cancelled', 'Order cancelled via API by Stylist.');
        update_field('cancellation_status', 'Cancelled by Stylist', $order_id);
        $order->save();

        // Extract relevant data from ACF or fallback to placeholders
        $partner_name = get_field('partner_name', $order_id) ?: 'partner_name_value';
        $service_name = get_field('service_name', $order_id) ?: 'service_name_value';
        $booking_date_time = get_field('booking_date_time', $order_id) ?: 'booking_date_time_value';

        // Create payload
        $payload = json_encode([
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'booking_date_time' => $booking_date_time,
        ]);

        // Webhook endpoint
        $webhook_url = 'https://webhook.site/9bcb9f9b-596e-4efb-9b99-daa3b26f9bca';

        // Send POST request
        wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
            'body'      => $payload,
        ]);

        return new \WP_REST_Response([
            'success'             => true,
            'message'             => "Order #$order_id has been successfully cancelled.",
            'order_id'            => $order_id,
            'cancellation_status' => 'Cancelled by Stylist',
        ], 200);
    }
}
