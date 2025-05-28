<?php

namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetConversationsComments {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-conversation-comments', [
            'methods'             => 'POST',
            'callback'            => array($this, 'get_conversation_comments'),
            'permission_callback' => function () {
                return current_user_can('edit_posts'); // Adjust permission as needed
            },
            'args'                => [
                'conversation_post_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    /**
     * Fetches comments for a given conversation post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_conversation_comments(\WP_REST_Request $request) {
    $post_id = $request->get_param('conversation_post_id');

    // Validate the conversation post
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'conversation') {
        return rest_ensure_response([
            'error'   => true,
            'message' => __('Invalid conversation post ID.', 'textdomain')
        ], 404);
    }

    // Fetch the latest approved comment
    $comments = get_comments([
        'post_id' => $post_id,
        'status'  => 'approve',
        'number'  => 1,
        'orderby' => 'comment_date',
        'order'   => 'DESC',
    ]);

    if (empty($comments)) {
        return rest_ensure_response([
            'message' => 'No comments found.',
            'profile_picture' => null,
        ]);
    }

    $comment = $comments[0];

    // 🧠 Try fetching custom avatar for registered users
    $avatar_url = '';
    if ($comment->user_id) {
        // Registered user: check YITH or fallback to Gravatar
        $yith_avatar_id = get_user_meta($comment->user_id, 'yith-wcmap-avatar', true);
        if ($yith_avatar_id) {
            $avatar_url = wp_get_attachment_url($yith_avatar_id);
        }
        if (!$avatar_url) {
            $avatar_url = get_avatar_url($comment->user_id, ['size' => 96]);
        }
    } else {
        // Guest comment: fallback to Gravatar via email
        $avatar_url = get_avatar_url($comment->comment_author_email, ['size' => 96]);
    }

    return rest_ensure_response([
        'comment_id'      => $comment->comment_ID,
        'author'          => $comment->comment_author,
        'content'         => $comment->comment_content,
        'date'            => $comment->comment_date,
        'author_email'    => $comment->comment_author_email,
        'profile_picture' => $avatar_url,
    ]);
    }

}
