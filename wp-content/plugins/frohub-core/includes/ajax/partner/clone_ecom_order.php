<?php

namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class CloneEcomOrder {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/clone_ecom_order', [$self, 'clone_ecom_order']);
    }

    public function clone_ecom_order() {
        check_ajax_referer('frohub_nonce');

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(['error' => 'Missing order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['error' => 'Invalid order ID']);
        }

        // Build payload to send to REST API
        $payload = [
            'id'         => $order_id,
            'status'     => $order->get_status(),
            'billing'    => $order->get_address('billing'),
            'shipping'   => $order->get_address('shipping'),
            'meta_data'  => $this->format_order_meta($order),
            'line_items' => $this->format_order_line_items($order),
            'acf_fields' => [],
        ];

        // Optional partner_id stored in post_meta
        $partner_id = get_post_meta($order_id, 'partner_id', true);
        if ($partner_id) {
            $payload['acf_fields']['partner_id'] = $partner_id;
        }

        // Send data to REST endpoint
        $response = wp_remote_post('https://frohubpartners.mystagingwebsite.com/wp-json/frohub/v1/create-booking-post', [
            'method'      => 'POST',
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode($payload),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'error'   => 'API request failed',
                'details' => $response->get_error_message(),
            ]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            wp_send_json_error([
                'error'        => $body['error'] ?? 'Unknown API error',
                'api_response' => $body,
            ]);
        }

        wp_send_json_success([
            'message'          => 'Booking created successfully',
            'post_id'          => $body['post_id'] ?? null,
            'saved_partner_id' => $body['saved_partner_id'] ?? null,
        ]);
    }

    /**
     * Format all order meta
     */
    private function format_order_meta($order) {
        $meta = [];
        foreach ($order->get_meta_data() as $meta_data) {
            $meta[] = [
                'key'   => $meta_data->key,
                'value' => $meta_data->value,
            ];
        }
        return $meta;
    }

    /**
     * Format order line items
     */
    private function format_order_line_items($order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'sku'          => $product ? $product->get_sku() : '',
                'meta_data'    => $item->get_formatted_meta_data(null, true),
            ];
        }
        return $items;
    }
}
