<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StylistDeclineOrder {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/stylist-decline-order', array(
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
     * Handles the API request to decline the order.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $order_id = intval($request->get_param('order_id'));

        $order = wc_get_order($order_id);
        // Pull client data
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

        // Cancel order and update ACF field
        $order->update_status('cancelled', 'Order declined via API by Stylist.');
        update_field('cancellation_status', 'Declined by Stylist', $order_id);
        $order->save();

        // Replace with real meta values if available
        $partner_name = get_field('partner_name', $order_id) ?: 'partner_name_value';
        $service_name = get_field('service_name', $order_id) ?: 'service_name_value';
        $booking_date_time = get_field('booking_date_time', $order_id) ?: 'booking_date_time_value';

        // Build payload
        $payload = json_encode([
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name->post_title,
            'service_name_value' => $service_name,
            'booking_date_time_value' => $booking_date_time,
        ]);

        // Send to webhook.site
        $webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.1c24c2378d1cd964d192f3489f72be80.fdb99b1c82bc8acd0691cadf00896830&isdebug=false';

        wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
            'body'      => $payload,
        ]);

        return new \WP_REST_Response([
            'success'            => true,
            'message'            => "Order #$order_id has been successfully declined and cancelled.",
            'order_id'           => $order_id,
            'cancellation_status'=> 'Declined by Stylist',
        ], 200);
    }
}
