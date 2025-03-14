<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetAllAddOns {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-all-add-ons', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_all_addons'),
            'permission_callback' => '__return_true', // Restrict if needed
        ));
    }

    /**
     * Retrieves all add-ons from the 'pa_add-on' taxonomy.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_all_addons(\WP_REST_Request $request) {
        // Get all terms from 'pa_add-on' taxonomy
        $addons = get_terms([
            'taxonomy'   => 'pa_add-on',
            'hide_empty' => false // Fetch even if no products are linked
        ]);

        // If no add-ons are found, return an empty response
        if (empty($addons) || is_wp_error($addons)) {
            return new \WP_REST_Response([
                'message' => 'No add-ons found.',
                'data'    => []
            ], 200);
        }

        // Format response
        $formatted_addons = array_map(function ($addon) {
            return [
                'add_on_term_id' => $addon->term_id,
                'add_on'         => $addon->name,
                'slug'           => $addon->slug,
                'description'    => $addon->description
            ];
        }, $addons);

        return new \WP_REST_Response([
            'message' => 'All add-ons fetched successfully.',
            'data'    => $formatted_addons
        ], 200);
    }
}

