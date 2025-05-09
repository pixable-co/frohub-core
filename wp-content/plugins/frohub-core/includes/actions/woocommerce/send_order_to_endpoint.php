<?php

namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sends order data to Zoho Flow based on WooCommerce status
class SendOrderToEndpoint {

    public static function init() {
        $self = new self();

        add_action('woocommerce_order_status_on-hold', array($self, 'send_order_to_endpoint'));
        add_action('woocommerce_order_status_processing', array($self, 'send_order_to_endpoint'));
    }

    public function send_order_to_endpoint($order_id) {
        $endpoints = [
           // 'on-hold'    => 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.a2b4f63dfb58631d29cd422e757e1643.ab4522f28de91914f023873159058bfb&isdebug=false',
            'on-hold'    => 'https://webhook.site/46259b7b-17ea-4186-a9a8-1c976d72379c',
            'processing' => 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.c24fdf1c034a419e885cbc1353668061.25d7d31440d630f3414b8de7488df2d3&isdebug=false',
        ];

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Order #$order_id not found.");
            return;
        }

        $order_status = $order->get_status();

        // Check reschedule meta for processing status
        if ($order_status === 'processing') {
            $has_been_rescheduled = $order->get_meta('Has Been Rescheduled');
            error_log("Order #$order_id - Has Been Rescheduled: " . var_export($has_been_rescheduled, true));

            if (strtolower(trim($has_been_rescheduled)) === 'yes') {
                error_log("Order #$order_id has been rescheduled. Skipping webhook.");
                return;
            }
        }

        if (!isset($endpoints[$order_status])) {
            error_log("No endpoint defined for order status: $order_status");
            return;
        }

        $endpoint = $endpoints[$order_status];

        // Generate the payload using the shared logic
        if (!function_exists('sendPayloadToZohoFlowPayload')) {
            error_log("Missing function: sendPayloadToZohoFlowPayload");
            return;
        }

        $payload = handlePayloadTriggers($order_id);

        if (!$payload) {
            error_log("Order #$order_id payload was skipped (likely due to reschedule).");
            return;
        }

        $payload['status'] = $order_status; // Include status in payload

        $response = wp_remote_post($endpoint, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending order data to ' . $endpoint . ': ' . $response->get_error_message());
        } else {
            error_log("Order #$order_id successfully sent to $endpoint.");
        }
    }
}
