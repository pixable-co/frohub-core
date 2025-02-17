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
        register_rest_route('frohub/v1', '/create-product', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_product'),
            'permission_callback' => function () {
                return current_user_can('edit_products');
            },
        ));
    }

    /**
     * Creates or updates a WooCommerce product as a draft service product.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_product(\WP_REST_Request $request) {
        $data = $request->get_json_params();

        // Extract and sanitize input variables
        $serviceID = $data['serviceID'] ?? null;
        $serviceName = sanitize_text_field($data['serviceName'] ?? '');
        $serviceSize = sanitize_text_field($data['serviceSize'] ?? '');
        $serviceLength = sanitize_text_field($data['serviceLength'] ?? '');
        $serviceCategorie = sanitize_text_field($data['serviceCategorie'] ?? '');
        $serviceTag = sanitize_text_field($data['serviceTag'] ?? '');
        $serviceTypes = $data['serviceTypes'] ?? [];
        $serviceDurationHours = intval($data['serviceDurationHours'] ?? 0);
        $serviceDurationMinutes = intval($data['serviceDurationMinutes'] ?? 0);
        $servicePrice = floatval($data['servicePrice'] ?? 0);
        $availability = $data['availability'] ?? [];
        $notice = $data['notice_needed'] ?? [];
        $bookingInAdv = $data['booking_far_in_advance'] ?? [];
        $clientsPerSlot = $data['clients_per_slot'] ?? [];
        $privateService = $data['private_service'] ?? [];
        $partner_id = $data['partner_id'] ?? null;
        $serviceImages = $data['serviceImages'] ?? [];

        // Validate required fields
        if (empty($serviceName) || $servicePrice < 0) {
            return rest_ensure_response(['message' => 'Missing or invalid required fields.'], 400);
        }

        try {
            // Check if updating an existing product
            $product = empty($serviceID) ? new \WC_Product_Simple() : wc_get_product($serviceID);
            if (!$product) {
                return rest_ensure_response(['message' => 'Product not found.'], 404);
            }

            // Set product details
            $product->set_name($serviceName);
            $product->set_regular_price($servicePrice);
            $product->set_status('draft');
            $product->save();
            $product_id = $product->get_id();

            // Handle product images
            if (!empty($serviceImages)) {
                $image_ids = array_map('attachment_url_to_postid', $serviceImages);
                $image_ids = array_filter($image_ids); // Remove invalid IDs

                if (!empty($image_ids)) {
                    // Set the first image as the featured image
                    set_post_thumbnail($product_id, $image_ids[0]);

                    // Set remaining images as gallery
                    if (count($image_ids) > 1) {
                        $gallery_ids = array_slice($image_ids, 1);
                        update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                    }
                }
            }

            // Update ACF fields
            update_field('sizes', $serviceSize, $product_id);
            update_field('length', $serviceLength, $product_id);
            update_field('service_types', $serviceTypes, $product_id);
            
            // Update ACF repeater field for availability
            if (!empty($availability)) {
                $formatted_availability = [];

                foreach ($availability as $avail) {
                    $formatted_availability[] = [
                        'day' => $avail['Day'] ?? '',
                        'from' => $avail['From'] ?? '',
                        'to' => $avail['To'] ?? '',
                        'extra_charge' => $avail['ExtraCharge'] ?? ''
                    ];
                }

                update_field('availability', $formatted_availability, $product_id);
            }

            // Update additional fields
            update_field('notice_needed', $notice, $product_id);
            update_field('booking_far_in_advance', $bookingInAdv, $product_id);
            update_field('clients_can_serve_per_time_slot', $clientsPerSlot, $product_id);
            update_field('private_service', $privateService, $product_id);
            update_field('partner_id', $partner_id, $product_id);
            update_field('duration_hours', $serviceDurationHours, $product_id);
            update_field('duration_minutes', $serviceDurationMinutes, $product_id);

            // Save the product
            $product->save();

            return rest_ensure_response([
                'success' => true,
                'message' => 'Product created/updated successfully.',
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'price' => $product->get_regular_price()
            ], 200);

        } catch (\Exception $e) {
            return rest_ensure_response(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
