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

        // Fetch comments for the post
        $comments = get_comments([
            'post_id' => $post_id,
            'status'  => 'approve',
        ]);

        // Format the comments with all metadata
        $formatted_comments = array_map(function ($comment) {
            // Fetch all comment metadata
            $meta_data = get_comment_meta($comment->comment_ID);

            // Fetch the ACF field "partner" associated with the comment
            $partner = get_field('partner', 'comment_' . $comment->comment_ID);
            $partner_id = is_object($partner) && isset($partner->ID) ? $partner->ID : null;

            return [
                'comment_id'   => $comment->comment_ID,
                'author'       => $comment->comment_author,
                'content'      => $comment->comment_content,
                'date'         => $comment->comment_date,
                'author_email' => $comment->comment_author_email,
                'meta_data'    => $meta_data,   // Include all comment meta
            ];
        }, $comments);

        return rest_ensure_response($formatted_comments);
    }
}
