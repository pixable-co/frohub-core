<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MarkRead {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/mark-read/', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'mark_conversation_as_read'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Marks a conversation as read.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function mark_conversation_as_read(\WP_REST_Request $request) {
        $conversation_id = $request->get_param('conversation_id');

        if (!$conversation_id) {
            return new \WP_Error('no_conversation', 'Conversation ID is required', array('status' => 400));
        }

        // Check if the conversation post exists
        if (!get_post($conversation_id)) {
            return new \WP_Error('invalid_conversation', 'Conversation ID does not exist', array('status' => 404));
        }

        $comments = get_comments(array('post_id' => $conversation_id)); // Get comments for the current post
        $updated_comments = []; // Store updated comment IDs

        foreach ($comments as $comment) {
            $commentId = $comment->comment_ID; // Store comment ID in a variable
            $comment_meta = get_comment_meta($commentId);

            $comment_partner = isset($comment_meta['partner'][0]) ? $comment_meta['partner'][0] : null; 
            $has_been_read_by_partner = isset($comment_meta['has_been_read_by_partner'][0]) ? $comment_meta['has_been_read_by_partner'][0] : null;

            if ($comment_partner === null && $has_been_read_by_partner != 1) {
                update_comment_meta($commentId, 'has_been_read_by_partner', 1);
                $updated_comments[] = $commentId; // Store updated comment ID
            }
        }

        return rest_ensure_response([
            'success' => true,
            'updated_comment_ids' => $updated_comments
        ]);
    }
}
