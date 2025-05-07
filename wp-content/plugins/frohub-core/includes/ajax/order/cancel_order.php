<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CancelOrder {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_cancel_order', array($self, 'cancel_order'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_cancel_order', array($self, 'cancel_order'));
    }

    /**
     * Handles AJAX cancellation of an order and sends two different webhook payloads.
     */
    public function cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $cancellation_reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        $cancellation_other_text = isset($_POST['other_reason']) ? sanitize_textarea_field($_POST['other_reason']) : '';

        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        // Initialize shared variables
        $partner_name = '';
        $partner_email = '';
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();
        $service_name = '';
        $service_type = 'Mobile';
        $formatted_date_time = '';

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            // Skip booking fee products
            if ($product_id == 28990) continue;

            // Service name
            $raw_service_name = $item->get_name();
            $parts = explode(' - ', $raw_service_name);
            $service_name = trim($parts[0]);

            // Booking date/time
            $start_datetime = $item->get_meta('Start Date Time');
            if ($start_datetime) {
                $formatted_date_time = date('H:i, d M Y', strtotime($start_datetime));
            }

            // Partner info
            $partner_post = get_field('partner_name', $product_id);
            if ($partner_post && is_object($partner_post)) {
                $partner_name = get_the_title($partner_post->ID);
                $partner_email = get_field('partner_email', $partner_post->ID);
            }

            // Service type from variation
            $product = $item->get_product();
            if ($product && $product->is_type('variation')) {
                $attrs = $product->get_attributes();
                if (!empty($attrs['pa_service-type'])) {
                    $service_type = ucfirst($attrs['pa_service-type']);
                }
            }

            break; // Process only first main service item
        }

        // ✅ Payload 1: Customer webhook
        $payload_customer = [
            'order_id' => '#' . $order_id,
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'service_type' => $service_type,
            'booking_date_time' => $formatted_date_time,
        ];

        // ✅ Payload 2: Partner webhook
        $payload_partner = [
            'order_id' => '#' . $order_id,
            'partner_email' => $partner_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'service_type' => $service_type,
            'booking_date_time' => $formatted_date_time,
        ];

        // 🔗 Webhook endpoints
        $customer_webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.f5f517804c4f3cad824e08e73a3d1de5.1b6c2804310ae465930ef15631808e89&isdebug=false';
        $partner_webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.9dc9d8e2982ee05fb07c6c2558b9811c.42d319bfe73b89e2f314888d692ea277&isdebug=false';

        // 🔁 Send both
        $this->send_webhook($customer_webhook_url, sendPayloadToZohoFlowPayload($order_id), 'Customer');
        $this->send_webhook($partner_webhook_url, sendPayloadToZohoFlowPayload($order_id), 'Partner');

        // 🧹 Clean up proposed time meta fields
        foreach ($order->get_items() as $item_id => $item) {
            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        // ❌ Cancel the order
        $order->update_status('cancelled', 'Order has been cancelled.');
        update_field('cancellation_reason', $cancellation_reason, $order_id);
        update_field('cancellation_other_reason_text', $cancellation_other_text, $order_id);
        $order->save();

        wp_send_json_success(['message' => 'Order has been cancelled.']);
    }

    /**
     * Helper to send webhook and log result
     */
    private function send_webhook($url, $payload, $type = 'Webhook') {
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 20,
        ]);

        $log_tag = strtoupper($type);

        if (is_wp_error($response)) {
            error_log("❌ $log_tag webhook failed: " . $response->get_error_message());
        } else {
            error_log("✅ $log_tag webhook sent to $url with order " . $payload['order_id']);
        }
    }
}
