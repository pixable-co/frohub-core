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
        $serviceName = isset($params["service_name"]) ? sanitize_text_field($params["service_name"]) : '';
        $size_id = isset($params["size"]) ? intval($params["size"]) : 0;
        $length_id = isset($params["length"]) ? intval($params["length"]) : 0;
        $price = isset($params["service_price"]) ? floatval($params["service_price"]) : 0;
        $variation_price = round($price * 0.30, 2);
        $description = isset($params["service_description"]) ? sanitize_textarea_field($params["service_description"]) : '';
        $bookingDuration = isset($params["service_duration"]) ? sanitize_text_field($params["service_duration"]) : '00:00';
        
        // **Fix Duration Parsing**
        $bookingDurationParts = explode(':', $bookingDuration);
        $bookingDurationHours = isset($bookingDurationParts[0]) ? intval($bookingDurationParts[0]) : 0;
        $bookingDurationMinutes = isset($bookingDurationParts[1]) ? intval($bookingDurationParts[1]) : 0;

        $bookingNotice = isset($params["booking_notice"]) ? intval($params["booking_notice"]) : 0;
        $futureBooking = isset($params["future_booking"]) ? intval($params["future_booking"]) : 0;

        $categories = isset($params["categories"]) ? (is_array($params["categories"]) ? $params["categories"] : json_decode($params["categories"], true)) : [];
        $tags = isset($params["tags"]) ? (is_array($params["tags"]) ? $params["tags"] : json_decode($params["tags"], true)) : [];
        $addOns = isset($params["add_ons"]) ? (is_array($params["add_ons"]) ? $params["add_ons"] : json_decode($params["add_ons"], true)) : [];
        $faqs = isset($params["faqs"]) ? (is_array($params["faqs"]) ? $params["faqs"] : json_decode($params["faqs"], true)) : [];
        $serviceTypes = isset($params["service_types"]) ? (is_array($params["service_types"]) ? $params["service_types"] : json_decode($params["service_types"], true)) : [];

        // Availability extraction
        $availability = [];
        if (isset($params["availability"]["days"]) && is_array($params["availability"]["days"])) {
            foreach ($params["availability"]["days"] as $index => $day) {
                $availability[] = [
                    "day"          => sanitize_text_field($day),
                    "from"         => sanitize_text_field($params["availability"]["start_times"][$index] ?? ''),
                    "to"           => sanitize_text_field($params["availability"]["end_times"][$index] ?? ''),
                    "extra_charge" => floatval($params["availability"]["extra_charge"][$index] ?? 0),
                ];
            }
        }

        // Map service types to WooCommerce attributes
        $service_types_map = [
            "Home-based" => ['id' => 152, 'slug' => 'home-based', 'virtual' => true],
            "Salon-based" => ['id' => 153, 'slug' => 'salon-based', 'virtual' => true],
            "Mobile" => ['id' => 154, 'slug' => 'mobile', 'virtual' => false] // Mobile is not virtual
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

        // **Assign Attributes (Size, Length, Add-ons)**
        $attributes = [
            'pa_size' => [
                'name'         => 'pa_size',
                'value'        => $size_id,
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 0
            ],
            'pa_length' => [
                'name'         => 'pa_length',
                'value'        => $length_id,
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 0
            ],
            'pa_addons' => [
                'name'         => 'pa_addons',
                'value'        => implode('|', $addOns),
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 0
            ]
        ];
        update_post_meta($product_id, '_product_attributes', $attributes);

        // **ACF Updates**
        update_field('partner_id', $partnerId, $product_id);
        update_field('service_price', $price, $product_id);
        update_field('booking_notice', $bookingNotice, $product_id);
        update_field('future_booking_scope', $futureBooking, $product_id);
        update_field('availability', $availability, $product_id);
        update_field('duration_hours', $bookingDurationHours, $product_id);
        update_field('duration_minutes', $bookingDurationMinutes, $product_id);

        // **Update FAQ Repeater**
        $faqs_repeater = [];
        foreach ($faqs as $faq_id) {
            $faqs_repeater[] = ["faq_post" => intval($faq_id)];
        }
        update_field('faqs', $faqs_repeater, $product_id);

        return new \WP_REST_Response(['message' => 'Product created/updated successfully', 'product_id' => $product_id], 200);
    }
}
