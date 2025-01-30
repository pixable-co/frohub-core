<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateComment {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('conversation/v1', '/comment/', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_conversation_comment'),
            'permission_callback' => function () {
                                return is_user_logged_in();
            }
        ));
    }

    public function handle_conversation_comment(WP_REST_Request $request) {
        $parameters = $request->get_params();

        $post_id     = isset($parameters['post_id']) ? intval($parameters['post_id']) : 0;
        $partner_id  = isset($parameters['partner_id']) ? intval($parameters['partner_id']) : 0;
        $comment     = isset($parameters['comment']) ? wp_kses_post($parameters['comment']) : '';
        $author_name = isset($parameters['author_name']) ? sanitize_text_field($parameters['author_name']) : '';
    	$email = isset($parameters['email']) ? $parameters['email'] : 0;

        $user_id = get_current_user_id();

        // Validate post ID
        if (!$post_id || get_post_type($post_id) !== 'conversation') {
            return new WP_Error('invalid_post', __('Invalid conversation post.', 'textdomain'), array('status' => 400));
        }

        // Validate comment content
        if (empty($comment)) {
            return new WP_Error('empty_comment', __('Comment cannot be empty.', 'textdomain'), array('status' => 400));
        }

        // Validate partner ID
        if ($partner_id && !get_post($partner_id)) {
            return new WP_Error('invalid_partner', __('Invalid partner ID.', 'textdomain'), array('status' => 400));
        }

        // Use logged-in user display name if author_name is not provided
        if (empty($author_name)) {
            $author_name = wp_get_current_user()->display_name;
        }

        // Prepare comment data
        $comment_data = array(
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author_name,
            'comment_author_email' => $email,
            'user_id'              => $user_id,
            'comment_content'      => $comment,
            'comment_approved'     => 1, // Automatically approve
        );

        // Insert comment
        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return new WP_Error('comment_failed', __('Failed to post comment.', 'textdomain'), array('status' => 500));
        }

        // Store partner ID as comment meta (optional)
        if ($partner_id) {
            update_comment_meta($comment_id, 'partner', $partner_id);
        }

        return new WP_REST_Response(array(
            'success'    => true,
            'message'    => __('Comment added successfully.', 'textdomain'),
            'comment_id' => $comment_id
        ), 200);
    }
}