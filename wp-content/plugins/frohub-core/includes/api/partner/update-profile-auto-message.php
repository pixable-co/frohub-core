<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class UpdateProfileAutoMessage {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/update-profile-auto-message', array(
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
        $data = $request->get_json_params();

        if (empty($data['partnerPostId'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Missing partnerPostId'
            ], 400);
        }

        $partnerPostId = intval($data['partnerPostId']);
        $post = get_post($partnerPostId);

        if (!$post || $post->post_type !== 'partner') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid partnerPostId'
            ], 404);
        }

        // Sanitize and update ACF fields
        $auto_message = isset($data['auto_message']) ? intval($data['auto_message']) : 0;
        $auto_message_text = sanitize_textarea_field($data['auto_message_text'] ?? '');

        update_field('auto_message', $auto_message, $partnerPostId);
        update_field('auto_message_text', $auto_message_text, $partnerPostId);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Auto message settings updated successfully',
            'data'    => [
                'partnerPostId'       => $partnerPostId,
                'auto_message'        => $auto_message,
                'auto_message_text'   => $auto_message_text
            ]
        ], 200);
    }
}
