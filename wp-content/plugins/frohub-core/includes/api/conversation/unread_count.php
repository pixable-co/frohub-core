<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UnreadCount {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/unread-messages/', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'get_unread_messages_count'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Gets the unread messages count for a conversation.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_unread_messages_count(\WP_REST_Request $request) {
        $conversation_id = $request->get_param('conversation_id');

        if (!$conversation_id || !get_post($conversation_id)) {
            return new \WP_Error('invalid_conversation', 'Conversation ID does not exist', array('status' => 404));
        }

        $comments = get_comments(array('post_id' => $conversation_id));
        $unread_count = 0;

        foreach ($comments as $comment) {
            $comment_meta = get_comment_meta($comment->comment_ID);

            $comment_partner = isset($comment_meta['partner'][0]) ? $comment_meta['partner'][0] : null;
            $has_been_read_by_partner = isset($comment_meta['has_been_read_by_partner'][0]) ? $comment_meta['has_been_read_by_partner'][0] : null;

            // Count only customer-submitted messages (comment_partner == null) that are unread
            if ($comment_partner === null && !$has_been_read_by_partner) {
                $unread_count++;
            }
        }

        return rest_ensure_response([
            'unread_count' => $unread_count
        ]);
    }
}
