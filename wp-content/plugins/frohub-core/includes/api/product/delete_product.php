<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DeleteProduct {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/delete-product', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'product_id' => array(
                    'required'          => true,
                    'validate_callback' => function ( $param ) {
                        return is_numeric( $param ) && intval( $param ) > 0;
                    }
                ),
            ),
        ));
    }

    /**
     * Handles the API request to move a product to trash.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Authentication failed. Please log in.'
            ], 401);
        }

        $product_id = intval($request->get_param('product_id'));

        if (get_post_type($product_id) !== 'product') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Product not found or invalid product ID.'
            ], 404);
        }

        if (!current_user_can('delete_post', $product_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'You do not have permission to delete this product.'
            ], 403);
        }

        $trashed = wp_trash_post($product_id);

        if ($trashed && !is_wp_error($trashed)) {
            return new \WP_REST_Response([
                'success'    => true,
                'message'    => 'Product moved to trash successfully.',
                'product_id' => $product_id
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to move product to trash. Please try again.'
        ], 500);
    }
}
