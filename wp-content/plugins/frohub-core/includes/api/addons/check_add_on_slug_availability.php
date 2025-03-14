<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckAddOnSlugAvailability {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/check-add-on-slug-availability', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('frohub/v1', '/check-addon', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_addon_check'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the /check-add-on-slug-availability API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'check-add-on-slug-availability API endpoint reached',
        ), 200);
    }

    /**
     * Checks if an add-on slug (taxonomy term) exists.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_addon_check(\WP_REST_Request $request) {
        $addon_name = sanitize_text_field($request->get_param('addon_name'));
        $taxonomy   = 'pa_add-on';

        if (empty($addon_name)) {
            return new \WP_REST_Response(['error' => 'No add-on name provided.'], 400);
        }

        $term = get_term_by('name', $addon_name, $taxonomy);

        return new \WP_REST_Response(['exists' => $term ? true : false], 200);
    }
}

