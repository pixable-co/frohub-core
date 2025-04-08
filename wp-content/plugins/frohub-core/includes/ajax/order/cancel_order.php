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
     * Handles AJAX cancellation of an order (simple cancel with meta cleanup).
     */
    public function cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        // Grab the first relevant item for meta (excluding booking fee products)
        $partner_name = '';
        $partner_email = '';
        $service_name = '';
        $service_type = 'Mobile';
        $formatted_date_time = '';

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            if ($product_id == 28990) continue;

            // Service name
            $raw_service_name = $item->get_name();
            $parts = explode(' - ', $raw_service_name);
            $service_name = trim($parts[0]);

            // Start Date Time
            $start_datetime = $item->get_meta('Start Date Time');
            if ($start_datetime) {
                $formatted_date_time = date('H:i, d M Y', strtotime($start_datetime));
            }

            // Partner post from product ACF
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

            break; // Only one main service item expected
        }

        // Build payload
        $payload = [
            'order_id' => '#' . $order->get_id(),
            'partner_email' => $partner_email,
            'client_first_name' => $order->get_billing_first_name(),
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'service_type' => $service_type,
            'booking_date_time' => $formatted_date_time,
        ];

        // Send webhook to both endpoints
        $endpoints = [
            'https://webhook.site/beaf0763-82db-4041-9189-77f408d823f2'
        ];

        foreach ($endpoints as $url) {
            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                error_log("❌ Cancel webhook failed for order #{$order_id} - " . $response->get_error_message());
            } else {
                error_log("✅ Cancel webhook sent to $url for order #{$order_id}");
            }
        }

        // Remove proposed time fields
        foreach ($order->get_items() as $item_id => $item) {
            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        // Cancel the order
        $order->update_status('cancelled', 'Order has been cancelled.');
        $order->save();

        wp_send_json_success(['message' => 'Order has been cancelled.']);
    }
}
