<?php

namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class CloneEcomOrder {

    public static function init() {
        $self = new self();
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

        $payload = [
            'id'         => $order_id,
            'status'     => $order->get_status(),
            'billing'    => $this->sanitize_array_recursive($order->get_address('billing')),
            'shipping'   => $this->sanitize_array_recursive($order->get_address('shipping')),
            'meta_data'  => $this->format_order_meta($order),
            'line_items' => $this->format_order_line_items($order),
            'acf_fields' => [],
        ];

        $partner_id = get_post_meta($order_id, 'partner_id', true);
        if ($partner_id) {
            $payload['acf_fields']['partner_id'] = sanitize_text_field($partner_id);
        }

        $response = wp_remote_post(FHCORE_PARTNER_BASE_API_URL . '/wp-json/frohub/v1/create-booking-post', [
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
     * Format and sanitize all order-level meta
     */
    private function format_order_meta($order) {
        $meta = [];
        foreach ($order->get_meta_data() as $meta_data) {
            $meta[] = [
                'key'   => sanitize_text_field($meta_data->key),
                'value' => $this->sanitize_json_value($meta_data->value),
            ];
        }
        return array_values($meta);
    }

    /**
     * Format line items and map their meta data as flat associative arrays
     */
    private function format_order_line_items($order) {
        $items = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $item_data = [
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => sanitize_text_field($item->get_name()),
                'quantity'     => $item->get_quantity(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'sku'          => $product ? $product->get_sku() : '',
                'meta_data'    => $this->sanitize_item_meta_data_as_flat_array($item->get_meta_data(), $item),
            ];

            $items[] = $item_data;
        }

        return $items;
    }

    /**
     * Convert line item meta data into flat associative array, alias known keys
     */
    private function sanitize_item_meta_data_as_flat_array($meta_data_array, $item) {
        $sanitized = [];

        foreach ($meta_data_array as $meta) {
            $key = sanitize_text_field($meta->key);

            // Alias key if frontend expects different label
            if ($key === 'pa_service-type') {
                $key = 'Service Type';
            }

            $value = $this->sanitize_json_value($meta->value ?: $meta->display_value);
            $sanitized[$key] = $value;
        }

        // Check if service type is uncategorized or empty, then look for enabled variation
        if (empty($sanitized['Service Type']) ||
            strtolower($sanitized['Service Type']) === 'uncategorized' ||
            trim($sanitized['Service Type']) === '') {

            $service_type_from_variation = $this->get_service_type_from_enabled_variation($item);
            if (!empty($service_type_from_variation)) {
                $sanitized['Service Type'] = $service_type_from_variation;
            }
        }

        return $sanitized;
    }

    /**
     * Get service type from enabled product variation
     */
    private function get_service_type_from_enabled_variation($item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            return '';
        }

        // Get all variations for this product
        $variations = $product->get_available_variations();

        foreach ($variations as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            $variation = wc_get_product($variation_id);

            if (!$variation) {
                continue;
            }

            // Check if variation is enabled (published and in stock)
            $is_enabled = ($variation->get_status() === 'publish' &&
                          $variation->is_in_stock() &&
                          $variation->variation_is_visible());

            if ($is_enabled) {
                // Get service type attribute from this variation
                $service_type = $variation->get_attribute('pa_service-type');

                if (!empty($service_type) &&
                    strtolower($service_type) !== 'uncategorized') {
                    return sanitize_text_field($service_type);
                }
            }
        }

        // If no enabled variation found with valid service type, try meta approach
        return $this->get_service_type_from_variation_meta($product_id);
    }

    /**
     * Alternative method to get service type from variation meta
     */
    private function get_service_type_from_variation_meta($product_id) {
        // Get all variations using WordPress post query
        $variations = get_posts([
            'post_type'      => 'product_variation',
            'post_parent'    => $product_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ]);

        foreach ($variations as $variation_post) {
            $variation = wc_get_product($variation_post->ID);

            if (!$variation) {
                continue;
            }

            // Check if variation is enabled
            $is_enabled = ($variation->get_status() === 'publish' &&
                          $variation->is_in_stock());

            if ($is_enabled) {
                // Try different ways to get service type
                $service_type = $variation->get_attribute('service-type') ?:
                               $variation->get_attribute('pa_service-type') ?:
                               get_post_meta($variation_post->ID, 'attribute_pa_service-type', true);

                if (!empty($service_type) &&
                    strtolower($service_type) !== 'uncategorized') {
                    return sanitize_text_field($service_type);
                }
            }
        }

        return '';
    }

    /**
     * Escape HTML safely for JSON payloads
     */
    private function sanitize_json_value($value) {
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Recursively sanitize arrays like billing and shipping
     */
    private function sanitize_array_recursive($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitize_array_recursive($value);
            } else {
                $array[$key] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        return $array;
    }
}