<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PublishPartnerCreateProduct {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/publish-partner-create-product', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'publish_partner_create_product'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Publishes a partner-created product and updates the partner post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function publish_partner_create_product(\WP_REST_Request $request) {
        $data = $request->get_json_params();

        if (empty($data['partner_post_id'])) {
            return new \WP_REST_Response(['error' => 'Missing partner_post_id'], 400);
        }

        $partner_post_id = intval($data['partner_post_id']);

        // Retrieve the ACF field "draft_service" from the partner post
        $draft_service = get_field('draft_service', $partner_post_id);

        if (empty($draft_service)) {
            return new \WP_REST_Response(['error' => 'No draft_service found for partner_post_id: ' . $partner_post_id], 400);
        }

        // Decode the draft_service JSON
        $draft_service_data = json_decode($draft_service, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_REST_Response(['error' => 'Error decoding draft_service JSON: ' . json_last_error_msg()], 400);
        }

        // Prepare the product data
        $product_data = [
            'post_title'   => $draft_service_data['Service_Name'] ?? 'Draft Product',
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'product',
        ];

        // Insert the product as a draft
        $product_id = wp_insert_post($product_data);

        if (is_wp_error($product_id)) {
            return new \WP_REST_Response(['error' => 'Error creating product: ' . $product_id->get_error_message()], 500);
        }

        // Add product meta fields (e.g., price, duration, partner info)
        update_post_meta($product_id, '_price', $draft_service_data['Price'] ?? '');
        update_field('partner_id', $draft_service_data['Partner_ID'] ?? '', $product_id);
        update_field('duration_hours', $draft_service_data['Duration_Hours'] ?? '', $product_id);
        update_field('duration_minutes', $draft_service_data['Duration_Minutes'] ?? '', $product_id);
        update_field('partner_name', $draft_service_data['Partner_ID'] ?? '', $product_id);

        // ✅ Assign product category from Service_Category_ID
        if (!empty($draft_service_data['Service_Category_ID'])) {
            $category_id = intval($draft_service_data['Service_Category_ID']);
            wp_set_object_terms($product_id, [$category_id], 'product_cat');
        }

        // Update the partner post status to 'publish'
        $update_post = [
            'ID'          => $partner_post_id,
            'post_status' => 'publish',
        ];

        $updated_post_id = wp_update_post($update_post);

        if (is_wp_error($updated_post_id)) {
            return new \WP_REST_Response(['error' => 'Error updating partner post status: ' . $updated_post_id->get_error_message()], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'product_id' => $product_id,
            'partner_post_id' => $partner_post_id
        ], 200);
    }
}

