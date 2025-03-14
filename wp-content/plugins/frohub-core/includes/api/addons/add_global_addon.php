<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AddGlobalAddon {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/add-global-addon', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_addon_creation'),
            'permission_callback' => '__return_true', // Consider restricting access
        ));
    }

    /**
     * Handles the API request for adding a global add-on.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_addon_creation(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        $addon_name = sanitize_text_field($params['addon_name'] ?? '');
        $taxonomy = 'pa_add-on';

        if (empty($addon_name)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Add-on name is required.'], 400);
        }

        if (!taxonomy_exists($taxonomy)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'The taxonomy "pa_add-on" does not exist.'], 400);
        }

        $result = wp_insert_term($addon_name, $taxonomy);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => "Add-on '{$addon_name}' added successfully!",
            'term_id' => $result['term_id']
        ], 200);
    }
}
