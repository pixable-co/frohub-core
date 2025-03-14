<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckSlug {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/check-slug', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'check_slug_availability'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Checks the availability of a slug for the 'partner' post type.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function check_slug_availability(\WP_REST_Request $request) {
        $slug = sanitize_title($request->get_param('slug'));

        if (empty($slug)) {
            return new \WP_REST_Response(['message' => 'No slug provided'], 400);
        }

        $query_args = [
            'name'           => $slug,
            'post_type'      => 'partner',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
        ];

        $query = new \WP_Query($query_args);

        return new \WP_REST_Response(['available' => !$query->have_posts()], 200);
    }
}

