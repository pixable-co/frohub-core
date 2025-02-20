<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReturnAllProductTags {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/product-tags/', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_product_tags'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Retrieves all product tags.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_product_tags() {
        $tags = get_terms([
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ]);

        if (empty($tags) || is_wp_error($tags)) {
            return new \WP_Error('no_tags', 'No product tags found', ['status' => 404]);
        }

        $tag_list = [];
        foreach ($tags as $tag) {
            $tag_list[] = [
                'id'   => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ];
        }

        return rest_ensure_response($tag_list);
    }
}
