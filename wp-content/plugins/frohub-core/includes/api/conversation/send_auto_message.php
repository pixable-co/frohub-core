<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SendAutoMessage {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/send-auto-message', array(
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
        $partner_id      = intval($request->get_param('partner_id'));
        $conversation_id = intval($request->get_param('conversation_id'));

        // Validate partner
        if (!$partner_id || get_post_type($partner_id) !== 'partner') {
            return new \WP_REST_Response(['error' => true, 'message' => 'Invalid partner ID.'], 400);
        }

        // Validate conversation
        if (!$conversation_id || get_post_type($conversation_id) !== 'conversation') {
            return new \WP_REST_Response(['error' => true, 'message' => 'Invalid conversation ID.'], 400);
        }

        // Fetch auto message text from ACF
        $message = get_field('auto_message_text', $partner_id);

        if (empty($message)) {
            return new \WP_REST_Response(['error' => true, 'message' => 'Auto message text is empty.'], 400);
        }

        // Insert comment
        $comment_id = wp_insert_comment([
            'comment_post_ID'      => $conversation_id,
            'comment_author'       => get_the_title($partner_id),
            'comment_author_email' => '',
            'user_id'              => 0,
            'comment_content'      => wp_kses_post($message),
            'comment_approved'     => 1,
        ]);

        if (!$comment_id) {
            return new \WP_REST_Response(['error' => true, 'message' => 'Failed to create comment.'], 500);
        }

        // Attach meta
        update_comment_meta($comment_id, 'partner', $partner_id);
        update_comment_meta($comment_id, 'sent_from', 'partner');
        update_post_meta($conversation_id, 'read_by_customer', 0);

        return new \WP_REST_Response([
            'success'         => true,
            'message'         => 'Auto message created.',
            'comment_id'      => $comment_id,
            'conversation_id' => $conversation_id,
            'partner_id'      => $partner_id,
            'content'         => $message
        ], 200);
    }
}