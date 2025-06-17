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

        $draft_service = get_field('draft_service', $partner_post_id);
        if (empty($draft_service)) {
            return new \WP_REST_Response(['error' => 'No draft_service found for partner_post_id: ' . $partner_post_id], 400);
        }

        $draft_service_data = json_decode($draft_service, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_REST_Response(['error' => 'Error decoding draft_service JSON: ' . json_last_error_msg()], 400);
        }

        // Step 1: Create Product Post
        $product_data = [
            'post_title'   => $draft_service_data['Service_Name'] ?? 'Draft Product',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'product',
        ];

        $product_id = wp_insert_post($product_data);
        if (is_wp_error($product_id)) {
            return new \WP_REST_Response(['error' => 'Error creating product: ' . $product_id->get_error_message()], 500);
        }

        // Step 2: Set Product Type to Variable
        wp_set_object_terms($product_id, 'variable', 'product_type');

        // Step 3: Set Basic Meta Fields
        update_post_meta($product_id, '_price', ''); // price will come from variations
        update_field('partner_id', $draft_service_data['Partner_ID'] ?? '', $product_id);
        update_field('duration_hours', $draft_service_data['Duration_Hours'] ?? '', $product_id);
        update_field('duration_minutes', $draft_service_data['Duration_Minutes'] ?? '', $product_id);
        update_field('partner_name', $draft_service_data['Partner_ID'] ?? '', $product_id);

        // Step 4: Set Product Category
        if (!empty($draft_service_data['Service_Category_ID'])) {
            $category_id = intval($draft_service_data['Service_Category_ID']);
            wp_set_object_terms($product_id, [$category_id], 'product_cat');
        }

        // Step 5: Create an Attribute for Variations (example: size)
        $attribute_name = 'pa_size';
        $attribute_values = ['Small', 'Medium', 'Large'];

        // Register taxonomy terms if they don’t exist
        foreach ($attribute_values as $term) {
            if (!term_exists($term, $attribute_name)) {
                wp_insert_term($term, $attribute_name);
            }
        }

        // Set the attribute on the product
        $product_attributes = array(
            $attribute_name => array(
                'name'         => $attribute_name,
                'value'        => implode(' | ', $attribute_values),
                'position'     => 0,
                'is_visible'   => 1,
                'is_variation' => 1,
                'is_taxonomy'  => 1,
            )
        );
        update_post_meta($product_id, '_product_attributes', $product_attributes);

        // Step 6: Create Variations
        foreach ($attribute_values as $value) {
            $variation_post = array(
                'post_title'  => $product_data['post_title'] . ' – ' . $value,
                'post_name'   => 'product-' . $product_id . '-variation-' . sanitize_title($value),
                'post_status' => 'publish',
                'post_parent' => $product_id,
                'post_type'   => 'product_variation',
                'menu_order'  => 0,
            );

            $variation_id = wp_insert_post($variation_post);

            if (!is_wp_error($variation_id)) {
                // Set variation attributes
                $term_slug = sanitize_title($value);
                update_post_meta($variation_id, 'attribute_' . $attribute_name, $term_slug);

                // Example price and duration
                update_post_meta($variation_id, '_price', $draft_service_data['Price'] ?? '50');
                update_post_meta($variation_id, '_regular_price', $draft_service_data['Price'] ?? '50');
            }
        }

        // Step 7: Publish Partner Post
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

