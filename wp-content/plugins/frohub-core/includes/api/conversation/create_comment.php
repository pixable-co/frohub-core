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
            'args' => array(
                'comment_image' => array(
                    'required' => false,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param);
                    }
                ),
            ),
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

        $user_id = get_current_user_id();

        // Validate post ID
        if (!$post_id || get_post_type($post_id) !== 'conversation') {
            return rest_ensure_response([
                'error'   => true,
                'message' => __('Invalid conversation post.', 'textdomain')
            ], 400);
        }

        // Validate comment content
        if (empty($comment) && empty($_FILES['comment_image']['name'])) {
            return rest_ensure_response([
                'error'   => true,
                'message' => __('Comment cannot be empty.', 'textdomain')
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

        // Handle image upload (if provided)
        $image_url = '';
        if (!empty($_FILES['comment_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload($_FILES['comment_image'], array('test_form' => false));

            if (isset($upload['file'])) {
                $file_path = $upload['file'];
                $file_name = basename($file_path);
                $file_type = wp_check_filetype($file_name);

                $attachment = array(
                    'post_mime_type' => $file_type['type'],
                    'post_title'     => sanitize_file_name($file_name),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attachment_id = wp_insert_attachment($attachment, $file_path);
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));

                // Get the image URL
                $image_url = wp_get_attachment_url($attachment_id);
            }
        }

        // Append image to comment content if available
        if (!empty($image_url)) {
            $comment .= '<br><img src="' . esc_url($image_url) . '" alt="Comment Image" style="max-width:100%; height:auto;">';
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

