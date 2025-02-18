<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateProduct {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/create-product', [
            'methods'  => 'POST',
            'callback' => [$this, 'create_custom_woocommerce_product'],
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce'); // Restrict to WooCommerce managers
            },
        ]);
    }

    /**
     * Handles WooCommerce product creation.
     */
    public function create_custom_woocommerce_product(\WP_REST_Request $request) {
        $params = $request->get_json_params();

        // Extracting values from JSON payload
        $partnerId = isset($params["28"]) ? sanitize_text_field($params["28"]) : '';
        $partnerName = get_the_title($partnerId);
        $serviceName = isset($params["1"]) ? sanitize_text_field($params["1"]) : '';
        $size = isset($params["2"]) ? sanitize_text_field($params["2"]) : '';
        $length = isset($params["3"]) ? sanitize_text_field($params["3"]) : '';
        $overrideAvailability = isset($params["12"]) ? filter_var($params["12"], FILTER_VALIDATE_BOOLEAN) : false;
        $notice = isset($params["13"]) ? sanitize_text_field($params["13"]) : '';
        $farFuture = isset($params["14"]) ? sanitize_text_field($params["14"]) : '';
        $numOfClients = isset($params["15"]) ? sanitize_text_field($params["15"]) : '';
        $price = isset($params["17"]) ? floatval($params["17"]) : 0;
        $description = isset($params["19"]) ? sanitize_text_field($params["19"]) : '';
        $isPrivateService = isset($params["22"]) ? filter_var($params["22"], FILTER_VALIDATE_BOOLEAN) : false;
        $bookingDuration = isset($params["27"]) ? sanitize_text_field($params["27"]) : '';

		// Array fields
		$categories = isset($params["9"]) ? (is_array($params["9"]) ? $params["9"] : json_decode($params["9"], true)) : [];
		$tags = isset($params["10"]) ? (is_array($params["10"]) ? $params["10"] : json_decode($params["10"], true)) : [];
		$faq = isset($params["20"]) ? (is_array($params["20"]) ? $params["20"] : json_decode($params["20"], true)) : [];

        // Image fields
        $featuredImage = isset($params["29"]) ? sanitize_text_field($params["29"]) : '';
        $galleryImages = isset($params["21"]) ? (is_array($params["21"]) ? $params["21"] : json_decode($params["21"], true)) : [];

		// Decode attributes array
		$attribute_ids = isset($params["18"]) ? json_decode($params["18"], true) : [];

		// Ensure it's an array
		if (!is_array($attribute_ids)) {
			return new \WP_REST_Response([
				'success' => false,
				'message' => 'Invalid attribute format.'
			], 400);
		}

	
        // Split duration into hours and minutes
        $durationParts = explode(':', $bookingDuration);
        $hours = isset($durationParts[0]) ? intval($durationParts[0]) : 0;
        $minutes = isset($durationParts[1]) ? intval($durationParts[1]) : 0;

        // Extract service types dynamically
        $serviceTypes = [];
        foreach (["11.1", "11.2", "11.3"] as $key) {
            if (!empty($params[$key])) {
                $serviceTypes[] = sanitize_text_field($params[$key]);
            }
        }

        // Handle availability schedule
        $availability = isset($params["23"]) ? $params["23"] : [];
        $availabilityData = array_map(function ($slot) {
            return [
                "day" => isset($slot["1"]) ? sanitize_text_field($slot["1"]) : '',
                "from" => isset($slot["3"]) ? sanitize_text_field($slot["3"]) : '',
                "to" => isset($slot["4"]) ? sanitize_text_field($slot["4"]) : '',
                "extra_charge" => isset($slot["5"]) ? floatval($slot["5"]) : 0,
            ];
        }, $availability);

        // Create WooCommerce product
 		$product = new \WC_Product_Simple();
        $product->set_name($serviceName);
        $product->set_regular_price($price);
        $product->set_description($description);
        $product->set_stock_quantity($numOfClients);
        $product->set_manage_stock(true);
        $product->set_catalog_visibility('visible');

        // Assign categories and tags
        if (!empty($categories)) {
            $product->set_category_ids($categories);
        }
        if (!empty($tags)) {
            $product->set_tag_ids($tags);
        }


        // Set featured image
        if (!empty($featuredImage)) {
        $featuredImageId = $this->attach_image_from_url($featuredImage);
            if ($featuredImageId) {
                $product->set_image_id($featuredImageId);
            }
        }

        // Set product gallery images
        if (!empty($galleryImages)) {
        $galleryImageIds = array_map([$this, 'attach_image_from_url'], $galleryImages);
        $product->set_gallery_image_ids($galleryImageIds);
        }

		$product_id = $product->save();

		if (!$product_id) {
			return new \WP_REST_Response([
				'success' => false,
				'message' => 'Failed to create product.'
			], 500);
		}

		// Assign Attributes
		$taxonomy = 'pa_add-on'; // Change this to match your WooCommerce attribute slug
		$assigned_terms = [];
		$product_attributes = [];

		// Loop through attribute IDs
		foreach ($attribute_ids as $attribute_id) {
			$attribute_id = intval($attribute_id); // Ensure it's an integer

			// Fetch attribute term
			$attribute = get_term($attribute_id, $taxonomy);
			if (!$attribute || is_wp_error($attribute)) {
				continue; // Skip invalid attributes
			}

			$assigned_terms[] = $attribute->slug; // Collect all attribute slugs

			// Add attribute to product meta
			$product_attributes[$taxonomy] = [
				'name'         => $taxonomy,
				'value'        => implode('|', $assigned_terms), // Combine multiple values
				'is_visible'   => 1, // Show in product details
				'is_variation' => 0, // Not for variations
				'is_taxonomy'  => 1, // It's a taxonomy-based attribute
			];
		}

		// Apply Attributes to Product
		if (!empty($assigned_terms)) {
			wp_set_object_terms($product_id, $assigned_terms, $taxonomy, true); // Assign terms
			update_post_meta($product_id, '_product_attributes', $product_attributes); // Save attributes
		}
        // Update FAQs for ACF Repeater
        $faq_data = [];
        if (!empty($faq) && is_array($faq)) {
            foreach ($faq as $faq_id) {
                $faq_data[] = ['faq_post' => intval($faq_id)]; // Replace 'faq_post' with actual subfield name
            }
        }
        update_field('field_67978b43caeb0', $faq_data, $product_id);

        // **Populate ACF Fields**
        update_field('field_67853b658c2dd', $partnerId, $product_id);
        update_field('field_678928cad8da7', $partnerName, $product_id);
        update_field('field_6777c8532f7e8', $size, $product_id);
        update_field('field_6777c8692f7e9', $length, $product_id);
        update_field('field_6777c89c2f7ec', $serviceTypes, $product_id);
        update_field('field_67a4a2b3807e7', $overrideAvailability, $product_id);
        update_field('field_6777c8d02f7ee', $availabilityData, $product_id);
        update_field('field_6777c94b7ea36', $notice, $product_id);
        update_field('field_6777c95b7ea37', $farFuture, $product_id);
        update_field('field_6777c9737ea38', $numOfClients, $product_id);
        update_field('field_6777c7762f7e6', $hours, $product_id);
        update_field('field_6777c8252f7e7', $minutes, $product_id);
        update_field('field_6777ca0db882e', $isPrivateService ? "Yes" : "No", $product_id);
        update_field('published_globally','0',$product_id);

      
        return new \WP_REST_Response([
            'success' => true,
            'product_id' => $product_id,
            'message' => 'Product created successfully with ACF fields populated.'
        ], 200);
    }

    
    /**
     * Helper function to upload an image from a URL.
     */
    private function attach_image_from_url($image_url) {
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        $file_path = $upload_dir['path'] . '/' . $filename;

        if (file_put_contents($file_path, $image_data)) {
            $filetype = wp_check_filetype($filename, null);
            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            $attach_id = wp_insert_attachment($attachment, $file_path);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file_path));
            return $attach_id;
        }
        return 0;
    }
}