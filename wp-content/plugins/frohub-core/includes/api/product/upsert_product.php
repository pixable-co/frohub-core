<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class UpsertProduct {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/upsert-product', [
            'methods'  => 'POST',
            'callback' => [$this, 'create_custom_woocommerce_product_new'],
            'permission_callback' => '__return_true' // Restrict if needed
        ]);
    }

    /**
     * Handles WooCommerce product creation and updating.
     */
    public function create_custom_woocommerce_product_new(\WP_REST_Request $request) {
        $params = $request->get_json_params();
	
        // Check if we're updating an existing product
        $product_id = isset($params["30"]) && !empty($params["30"]) ? intval($params["30"]) : 0;
        $is_update = $product_id > 0;

        // Extract values from JSON payload
        $partnerId = isset($params["28"]) ? sanitize_text_field($params["28"]) : '';
        $partnerName = get_the_title($partnerId);
        $serviceName = isset($params["1"]) ? sanitize_text_field($params["1"]) : '';
        $size_id = isset($params["2"]) ? intval($params["2"]) : 0;
        $length_id = isset($params["3"]) ? intval($params["3"]) : 0;
        $price = isset($params["17"]) ? floatval($params["17"]) : 0;
        $variation_price = round($price * 0.30, 2);
        $description = isset($params["19"]) ? sanitize_text_field($params["19"]) : '';
        $bookingDuration = isset($params["27"]) ? sanitize_text_field($params["27"]) : '';
        $categories = isset($params["9"]) ? (is_array($params["9"]) ? $params["9"] : json_decode($params["9"], true)) : [];
        $tags = isset($params["10"]) ? (is_array($params["10"]) ? $params["10"] : json_decode($params["10"], true)) : [];

        // Service Types
        $fixed_service_types = [
            "11.1" => ['id' => 152, 'slug' => 'home-based'],
            "11.2" => ['id' => 153, 'slug' => 'salon-based'],
            "11.3" => ['id' => 154, 'slug' => 'mobile']
        ];
        $enabled_service_types = [];
        foreach ($fixed_service_types as $payload_key => $data) {
            if (!empty($params[$payload_key])) {
                $enabled_service_types[$data['id']] = sanitize_text_field($params[$payload_key]);
            }
        }

        if ($is_update) {
            $product = wc_get_product($product_id);
            if (!$product) {
                return new \WP_REST_Response(['message' => 'Product not found for update.', 'product_id' => $product_id], 404);
            }
        } else {
            $product = new \WC_Product_Variable();
        }

        $product->set_name($serviceName);
        $product->set_description($description);
        $product->set_status('publish');
        $product->set_manage_stock(false);
        $product->set_virtual(true);

        if (!empty($categories)) {
            $product->set_category_ids($categories);
        }
        if (!empty($tags)) {
            $product->set_tag_ids($tags);
        }

        $product_id = $product->save();

        // Assign Attributes
        $attributes = [
            'pa_service-type' => [
                'name'         => 'pa_service-type',
                'value'        => implode('|', array_column($fixed_service_types, 'slug')),
                'is_visible'   => 1,
                'is_variation' => 1,
                'is_taxonomy'  => 1
            ]
        ];

        wp_set_object_terms($product_id, array_column($fixed_service_types, 'slug'), 'pa_service-type');
        update_post_meta($product_id, '_product_attributes', $attributes);

        // Generate Variations
        foreach ($fixed_service_types as $data) {
            $term_slug = $data['slug'];
            $variation_id = wp_insert_post([
                'post_title' => $serviceName . ' - ' . ucfirst(str_replace('-', ' ', $term_slug)),
                'post_status' => isset($enabled_service_types[$data['id']]) ? 'publish' : 'private',
                'post_parent' => $product_id,
                'post_type' => 'product_variation'
            ]);

            if (!is_wp_error($variation_id)) {
                update_post_meta($variation_id, 'attribute_pa_service-type', $term_slug);
                update_post_meta($variation_id, '_regular_price', $variation_price);
                update_post_meta($variation_id, '_price', $variation_price);
            }
        }

        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');

        return new \WP_REST_Response(['message' => 'Product created/updated successfully', 'product_id' => $product_id], 200);
    }
}

