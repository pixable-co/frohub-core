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
        $product_id = isset($params["product_id"]) && !empty($params["product_id"]) ? intval($params["product_id"]) : 0;
        $is_update = $product_id > 0;

        // Extract values from JSON payload
        $partnerId = isset($params["partner_id"]) ? sanitize_text_field($params["partner_id"]) : '';
        $partnerName = get_the_title($partnerId);
        $serviceName = isset($params["service_name"]) ? sanitize_text_field($params["service_name"]) : '';
        $size_id = isset($params["size"]) ? intval($params["size"]) : 0;
        $length_id = isset($params["length"]) ? intval($params["length"]) : 0;
        $price = isset($params["service_price"]) ? floatval($params["service_price"]) : 0;
        $variation_price = round($price * 0.30, 2);
        $description = isset($params["service_description"]) ? sanitize_textarea_field($params["service_description"]) : '';
        $bookingDuration = isset($params["service_duration"]) ? sanitize_text_field($params["service_duration"]) : '';
        $bookingNotice = isset($params["booking_notice"]) ? sanitize_text_field($params["booking_notice"]) : '';
        $futureBooking = isset($params["future_booking"]) ? sanitize_text_field($params["future_booking"]) : '';

        $categories = isset($params["categories"]) ? (is_array($params["categories"]) ? $params["categories"] : json_decode($params["categories"], true)) : [];
        $tags = isset($params["tags"]) ? (is_array($params["tags"]) ? $params["tags"] : json_decode($params["tags"], true)) : [];
        $addOns = isset($params["add_ons"]) ? (is_array($params["add_ons"]) ? $params["add_ons"] : json_decode($params["add_ons"], true)) : [];
        $faqs = isset($params["faqs"]) ? (is_array($params["faqs"]) ? $params["faqs"] : json_decode($params["faqs"], true)) : [];
        $serviceTypes = isset($params["service_types"]) ? (is_array($params["service_types"]) ? $params["service_types"] : json_decode($params["service_types"], true)) : [];

        $overrideAvailability = isset($params["override_availability"]) ? sanitize_text_field($params["override_availability"]) : 'no';

        // Availability extraction
        $availability = [
            "days" => isset($params["availability"]["days"]) ? (is_array($params["availability"]["days"]) ? $params["availability"]["days"] : []) : [],
            "start_times" => isset($params["availability"]["start_times"]) ? (is_array($params["availability"]["start_times"]) ? $params["availability"]["start_times"] : []) : [],
            "end_times" => isset($params["availability"]["end_times"]) ? (is_array($params["availability"]["end_times"]) ? $params["availability"]["end_times"] : []) : [],
            "extra_charge" => isset($params["availability"]["extra_charge"]) ? (is_array($params["availability"]["extra_charge"]) ? $params["availability"]["extra_charge"] : []) : [],
        ];

        // Map service types to WooCommerce attributes
        $service_types_map = [
            "Home-based" => ['id' => 152, 'slug' => 'home-based'],
            "Salon-based" => ['id' => 153, 'slug' => 'salon-based'],
            "Mobile" => ['id' => 154, 'slug' => 'mobile']
        ];

        // Create or Update WooCommerce Product
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
                'value'        => implode('|', array_column($service_types_map, 'slug')),
                'is_visible'   => 1,
                'is_variation' => 1,
                'is_taxonomy'  => 1
            ]
        ];

        wp_set_object_terms($product_id, array_column($service_types_map, 'slug'), 'pa_service-type');
        update_post_meta($product_id, '_product_attributes', $attributes);

        // Generate Variations for all 3 service types
        foreach ($service_types_map as $key => $data) {
            $term_slug = $data['slug'];
            $status = in_array($key, $serviceTypes) ? 'publish' : 'private'; // Enable if in payload, otherwise disable

            $variation_id = wp_insert_post([
                'post_title'  => $serviceName . ' - ' . ucfirst(str_replace('-', ' ', $term_slug)),
                'post_status' => $status,
                'post_parent' => $product_id,
                'post_type'   => 'product_variation'
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
