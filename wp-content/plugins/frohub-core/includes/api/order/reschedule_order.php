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

    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/reschedule-order', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_reschedule_order'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'     => [
                'order_id' => [
                    'required' => true,
                    'validate_callback' => fn($param) => is_numeric($param) && intval($param) > 0
                ],
                'date' => [
                    'required' => true,
                    'validate_callback' => fn($param) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)
                ],
                'start_time' => [
                    'required' => true,
                    'validate_callback' => fn($param) => preg_match('/^\d{2}:\d{2}$/', $param)
                ],
                'formatted_proposed_end_datetime' => [
                    'required' => true,
                    'validate_callback' => fn($param) => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $param)
                ],
            ],
        ]);
    }

    public function handle_reschedule_order(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        // Get parameters
        $order_id   = intval($request->get_param('order_id'));
        $date       = sanitize_text_field($request->get_param('date'));
        $start_time = sanitize_text_field($request->get_param('start_time'));
        $formatted_proposed_end_datetime = sanitize_text_field($request->get_param('formatted_proposed_end_datetime'));

        $formatted_proposed_start_datetime = date('H:i, d M Y', strtotime("$date $start_time"));
        $formatted_proposed_end_datetime = date('H:i, d M Y', strtotime($formatted_proposed_end_datetime));

        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid order ID.'], 404);
        }

        $current_status = $order->get_status();
        if (in_array($current_status, ['completed', 'cancelled'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Order #$order_id cannot be rescheduled because it is already '$current_status'.",
            ], 400);
        }

        // Update order item meta
        $order_items = $order->get_items();
        $item_id = null;
        $original_booking_datetime = '';
        foreach ($order_items as $item_key => $item) {
            $item_id = $item_key;
            $original_booking_datetime = wc_get_order_item_meta($item_id, 'Start Date Time', true);
            break;
        }

        if (!$item_id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'No order items found.'], 400);
        }

        wc_update_order_item_meta($item_id, 'Proposed Start Date Time', $formatted_proposed_start_datetime);
        wc_update_order_item_meta($item_id, 'Proposed End Date Time', $formatted_proposed_end_datetime);
        wc_update_order_item_meta($item_id, 'Has Been Rescheduled', 'Yes');

        // Confirm meta updates
        $proposed_start_datetime = wc_get_order_item_meta($item_id, 'Proposed Start Date Time', true);
        $proposed_end_datetime = wc_get_order_item_meta($item_id, 'Proposed End Date Time', true);

        if (!$proposed_start_datetime || !$proposed_end_datetime) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to retrieve updated proposed booking details.'], 500);
        }

        $order->update_status('wc-rescheduling', 'Order rescheduled with proposed times via API.');
        $order->save();

        // Client data
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();

        // Booking info
        $partner_name = '';
        $partner_email = '';
        $service_name = '';

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id == 28990) continue;

            $raw_service_name = $item->get_name();
            $service_name_parts = explode(' - ', $raw_service_name);
            $service_name = trim($service_name_parts[0]);

            $partner_post = get_field('partner_name', $product_id);
            if ($partner_post && is_object($partner_post)) {
                $partner_name = get_the_title($partner_post->ID);
                $partner_email = get_field('partner_email', $partner_post->ID);
            }
        }

        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.07b3be77c8b130450468de3b1b224675.0a399daca8ab79871ee2a7d5fc7e08f3&isdebug=false';
        //$webhook_customer = "https://webhook.site/46259b7b-17ea-4186-a9a8-1c976d72379c";

        wp_remote_post($webhook_customer, [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(handlePayloadTriggers($order_id)),
        ]);


        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.7ed3b56e85b7a0f137d0fee0503756b1.dafe549881529793b605b66682b49100&isdebug=false';

        wp_remote_post($webhook_partner, [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(handlePayloadTriggers($order_id)),
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'message' => "Order #$order_id rescheduled with proposed times: $proposed_start_datetime to $proposed_end_datetime.",
            'proposed_start_datetime' => $proposed_start_datetime,
            'proposed_end_datetime' => $proposed_end_datetime,
        ], 200);
    }
}
