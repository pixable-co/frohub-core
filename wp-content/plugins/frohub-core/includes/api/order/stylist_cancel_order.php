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

       // $order->update_status('cancelled', 'Order cancelled via API by Stylist.');
        update_field('cancellation_status', 'Cancelled by Stylist', $order_id);
        $order->save();

        foreach ($order->get_items() as $item) {

            $reschedule_meta = wc_get_order_item_meta($item->get_id(), 'Has Been Rescheduled', true);

            if (strtolower(trim($reschedule_meta)) === 'yes') {
                $has_been_rescheduled = true;
                break;
            }

            $product_id = $item->get_product_id();
            $item_total = $item->get_total();

            if ($product_id == 28990) {
                return; // Skip the booking fee item
            } else {

                // Get the service name and strip after ' - '
                $raw_service_name = $item->get_name();
                $service_name_parts = explode(' - ', $raw_service_name);
                $service_name = trim($service_name_parts[0]);

                $partner_post = get_field('partner_name', $product_id);
                if ($partner_post && is_object($partner_post)) {
                    $partner_name = get_the_title($partner_post->ID);
                }
                $selected_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);
            }
        }

        // Create payload
        $payload = json_encode([
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name->post_title,
            'service_name_value' => $service_name,
            'booking_date_time_value' => $booking_date_time,
        ]);

        // Webhook endpoint
        $webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.7f07c99121431dc8e17958ee0dc60a2b.9bdaa8eccc2446b091e2a4eb82f79ee5&isdebug=false';

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
