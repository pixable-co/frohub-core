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
        $bookingDurationHours = explode(':', $bookingDuration)[0] ?? 0;
        $bookingDurationMinutes = explode(':', $bookingDuration)[1] ?? 0;
        $bookingNotice = isset($params["booking_notice"]) ? sanitize_text_field($params["booking_notice"]) : '';
        $futureBooking = isset($params["future_booking"]) ? sanitize_text_field($params["future_booking"]) : '';

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
            "Home-based" => ['id' => 122, 'slug' => 'home-based', 'virtual' => true],
            "Salon-based" => ['id' => 124, 'slug' => 'salon-based', 'virtual' => true],
            "Mobile" => ['id' => 123, 'slug' => 'mobile', 'virtual' => false] // Mobile is not virtual
        ];

        // Handle Featured Image Upload
        $featuredImageId = null;
        if (!empty($params["featuredImage"])) {
            $featuredImageId = $this->upload_image_to_wordpress($params["featuredImage"], $serviceName);
        }

        // Handle Gallery Images Upload & Cleanup
        $existingGalleryImageIds = get_post_meta($product_id, '_product_image_gallery', true);
        $existingGalleryImageIds = !empty($existingGalleryImageIds) ? explode(',', $existingGalleryImageIds) : [];

        // Convert existing gallery IDs to URLs
        $existingGalleryImages = [];
        foreach ($existingGalleryImageIds as $imageId) {
            $imageUrl = wp_get_attachment_url($imageId);
            if ($imageUrl) {
                $existingGalleryImages[$imageId] = $imageUrl;
            }
        }

        // Extract images from payload
        $receivedGalleryImages = !empty($params["galleryImages"]) && is_array($params["galleryImages"]) ? $params["galleryImages"] : [];

        // Find images that need to be deleted and new ones to upload
        $imagesToDelete = array_diff($existingGalleryImages, $receivedGalleryImages);
        $imagesToUpload = array_diff($receivedGalleryImages, $existingGalleryImages);

        // Delete old images that are no longer in the payload
        foreach ($imagesToDelete as $imageId => $imageUrl) {
            wp_delete_attachment($imageId, true);
        }

        // Upload new images
        $newGalleryImageIds = [];
        foreach ($imagesToUpload as $imageUrl) {
            $uploadedImageId = $this->upload_image_to_wordpress($imageUrl, $serviceName);
            if ($uploadedImageId) {
                $newGalleryImageIds[] = $uploadedImageId;
            }
        }

        // Merge remaining images with newly uploaded images
        $finalGalleryImageIds = array_merge(array_diff($existingGalleryImageIds, array_keys($imagesToDelete)), $newGalleryImageIds);

        // Ensure unique values and update product metadata
        $finalGalleryImageIds = array_unique($finalGalleryImageIds);


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

        update_post_meta($product_id, '_product_image_gallery', implode(',', $finalGalleryImageIds));

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

        // ✅ Delete all existing variations before re-creating
        $existing_variations = get_children([
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'post_status' => 'any',
            'numberposts' => -1,
        ]);

        foreach ($existing_variations as $variation) {
            wp_delete_post($variation->ID, true);
        }

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

                // **Set Virtual Status for Home & Salon, but Not Mobile**
                update_post_meta($variation_id, '_virtual', $data['virtual'] ? 'yes' : 'no');
            }
        }

        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');

        // **Handle Add ons, Length and Size Attribute**/

        // Get term names from size & length IDs
        $size_term = $size_id ? get_term($size_id, 'pa_size') : null;
        $length_term = $length_id ? get_term($length_id, 'pa_length') : null;

        $size_name = ($size_term && !is_wp_error($size_term)) ? $size_term->name : '';
        $length_name = ($length_term && !is_wp_error($length_term)) ? $length_term->name : '';

        if ($size_id) {
            $attributes['pa_size'] = [
                'name'         => 'pa_size',
                'value'        => $size_name,
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 1
            ];
            wp_set_object_terms($product_id, [$size_id], 'pa_size');
        }
    
        if ($length_id) {
            $attributes['pa_length'] = [
                'name'         => 'pa_length',
                'value'        => $length_name,
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 1
            ];
            wp_set_object_terms($product_id, [$length_id], 'pa_length');
        }

        // Extract Add-Ons
        $assigned_add_ons = [];

        foreach ($addOns as $add_on_id) {
        $add_on_id = intval($add_on_id); // Ensure it's an integer
        $add_on_term = get_term($add_on_id, 'pa_add-on');

        if ($add_on_term && !is_wp_error($add_on_term)) {
                $assigned_add_ons[] = $add_on_term->slug; // Store the term slug
            }
        }

        if (!empty($addOns)) {
            $attributes['pa_add-on'] = [
                'name'         => 'pa_add-on',
                'value'        => implode('|', $assigned_add_ons),
                'is_visible'   => 1,
                'is_variation' => 0, // 🚨 Not for variations
                'is_taxonomy'  => 1
            ];
            wp_set_object_terms($product_id, $assigned_add_ons, 'pa_add-on');
        }

        update_post_meta($product_id, '_product_attributes', $attributes);


        // ** UPDATE ACF FIELDS ** 
        update_field('partner_id', $partnerId, $product_id);
        update_field('partner_name', $partnerId, $product_id);
        update_field('service_price', $price, $product_id);
        update_field('booking_notice', $bookingNotice, $product_id);
        update_field('future_booking_scope', $futureBooking, $product_id);
        update_field('availability', $availability, $product_id);
        update_field('duration_hours', $bookingDurationHours, $product_id);
        update_field('duration_minutes', $bookingDurationMinutes, $product_id);

        // Update FAQ Repeater
        if (!empty($faqs) && is_array($faqs)) {
            $faqs_repeater = [];
        
            foreach ($faqs as $index => $faq_id) {
                $faq_id = intval($faq_id);
                if ($faq_id > 0 && get_post_type($faq_id) === 'faq') {
                    $faqs_repeater[] = [
                        'faq_post' => $faq_id
                    ];
                }
            }
        
            update_field('field_67efe0c25ab08', $faqs_repeater, $product_id);
        } else {
            // Clear FAQs if none provided
            update_field('field_67efe0c25ab08', [], $product_id);
        }
        

        // Set Featured Image
        if ($featuredImageId) {
        // Set new featured image
        set_post_thumbnail($product_id, $featuredImageId);
        } else {
        // Remove existing featured image if none provided
        delete_post_thumbnail($product_id);
        }


        // Set Gallery Images
        if (!empty($galleryImageIds)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $galleryImageIds));
        }
        

        return new \WP_REST_Response(['message' => 'Product created/updated successfully', 'product_id' => $product_id], 200);
    }

    /**
     * Uploads an image to WordPress Media Library.
     * Renames it for SEO and sets metadata (alt, caption, description).
     */
    private function upload_image_to_wordpress($image_url, $service_name, $index = null) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $image_name = sanitize_title($service_name);
        if ($index !== null) {
            $image_name .= '-' . ($index + 1);
        }
        $image_name .= '.jpg'; // Change format if necessary

        // Download Image
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }

        // File Information
        $file_array = [
            'name'     => $image_name,
            'tmp_name' => $tmp
        ];

        // Upload Image to Media Library
        $attachment_id = media_handle_sideload($file_array, 0);

        // Check for errors
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']); // Remove temp file
            return false;
        }

        // Get Attachment URL
        $attachment_url = wp_get_attachment_url($attachment_id);

        // Set Image Meta (SEO)
        $alt_text = $service_name . ' image'; // Example: "Braid styles image"
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        wp_update_post([
            'ID'           => $attachment_id,
            'post_title'   => ucfirst($service_name),
            'post_excerpt' => 'High-quality image of ' . $service_name,
            'post_content' => 'This is an image related to ' . $service_name
        ]);

        return $attachment_id;
    }
}
