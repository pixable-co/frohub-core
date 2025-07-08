<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReadByPartner {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/read-by-partner', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $post_id = $request->get_param('conversation_post_id');

        if (! $post_id || get_post_type($post_id) !== 'conversation') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid or missing conversation_post_id',
            ], 400);
        }

        // Update meta fields
        update_post_meta($post_id, 'read_by_partner', 1);
        update_post_meta($post_id, 'unread_count_partner', 0);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Conversation marked as read by partner.',
            'post_id' => $post_id,
        ], 200);
    }
}