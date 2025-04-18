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
        register_rest_route('frohub/v1', '/create-comment', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_conversation_comment'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    /**
     * Handles the creation of a comment for a conversation post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_conversation_comment(\WP_REST_Request $request) {
        $parameters = $request->get_params();

        $post_id     = isset($parameters['post_id']) ? intval($parameters['post_id']) : 0;
        $partner_id  = isset($parameters['partner_id']) ? intval($parameters['partner_id']) : 0;
        $comment     = isset($parameters['comment']) ? wp_kses_post($parameters['comment']) : '';
        $author_name = isset($parameters['author_name']) ? sanitize_text_field($parameters['author_name']) : '';
        $email       = isset($parameters['email']) ? sanitize_email($parameters['email']) : '';
        $image_url   = isset($parameters['image_url']) ? esc_url($parameters['image_url']) : '';

        $user_id = get_current_user_id();

        // Validate post ID
        if (!$post_id || get_post_type($post_id) !== 'conversation') {
            return rest_ensure_response([
                'error'   => true,
                'message' => __('Invalid conversation post.', 'textdomain')
            ], 400);
        }

        // Validate comment content
        if (empty($comment) && empty($image_url)) {
            return rest_ensure_response([
                'error'   => true,
                'message' => __('Comment cannot be empty if no image is provided.', 'textdomain')
            ], 400);
        }

        // Validate partner ID (optional)
        if ($partner_id && !get_post($partner_id)) {
            return rest_ensure_response([
                'error'   => true,
                'message' => __('Invalid partner ID.', 'textdomain')
            ], 400);
        }

        // Use logged-in user display name if author_name is not provided
        if (empty($author_name)) {
            $author_name = wp_get_current_user()->display_name;
        }

        // Append image to the comment if an image URL is provided
        if (!empty($image_url)) {
            $comment .= '<br><img src="' . esc_url($image_url) . '" alt="Comment Image" style="max-width: 100%; height: auto;">';
        }

        // Prepare comment data
        $comment_data = array(
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author_name,
            'comment_author_email' => $email,
            'user_id'              => $user_id,
            'comment_content'      => $comment,
            'comment_approved'     => 1, // Automatically approve the comment
        );

        // Insert comment
        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return rest_ensure_response([
                'error'   => true,
                'message' => __('Failed to post comment.', 'textdomain')
            ], 500);
        }

        // Store partner ID as comment meta (optional)
        if ($partner_id) {
            update_comment_meta($comment_id, 'partner', $partner_id);
        }

        // Set conversation as read by customer to false
        update_post_meta($post_id, 'read_by_customer', 0);

        return rest_ensure_response([
            'success'    => true,
            'message'    => __('Comment added successfully.', 'textdomain'),
            'comment_id' => $comment_id,
            'image_url'  => $image_url
        ], 200);
    }
}
