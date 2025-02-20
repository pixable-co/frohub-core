<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReturnProductCategories {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/product-categories/', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_product_categories'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Retrieves product categories.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_product_categories() {
        $categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false, // Change to true if you want to exclude empty categories
        ));

        if (is_wp_error($categories)) {
            return new \WP_Error('no_categories', 'No product categories found', array('status' => 404));
        }

        $data = array();

        foreach ($categories as $category) {
            $data[] = array(
                'id'    => $category->term_id,
                'name'  => $category->name,
                'slug'  => $category->slug,
                'count' => $category->count,
            );
        }

        return rest_ensure_response($data);
    }
}

