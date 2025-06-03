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
        register_rest_route('frohub/v1', '/upload-comment-image', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'frohub_upload_comment_image'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    public function frohub_upload_comment_image(\WP_REST_Request $request) {
            if (empty($_FILES['file'])) {
                return new WP_REST_Response(['error' => 'No file uploaded.'], 400);
            }

            $file = $_FILES['file'];
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('file', 0);
            if (is_wp_error($attachment_id)) {
                return new WP_REST_Response(['error' => $attachment_id->get_error_message()], 500);
            }

            $url = wp_get_attachment_url($attachment_id);
            return new WP_REST_Response(['success' => true, 'url' => $url], 200);
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
         $sent_from   = isset($parameters['sent_from']) ? sanitize_text_field($parameters['sent_from']) : '';

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

         // Append image to comment content
         if (!empty($image_url)) {
             $comment .= '<br><img src="' . esc_url($image_url) . '" alt="Comment Image" style="max-width: 100%; height: auto;">';
         }

         // Prepare comment data
         $comment_data = [
             'comment_post_ID'      => $post_id,
             'comment_author'       => $author_name,
             'comment_author_email' => $email,
             'user_id'              => $user_id,
             'comment_content'      => $comment,
             'comment_approved'     => 1,
         ];

         // Insert comment
         $comment_id = wp_insert_comment($comment_data);

         if (!$comment_id) {
             return rest_ensure_response([
                 'error'   => true,
                 'message' => __('Failed to post comment.', 'textdomain')
             ], 500);
         }

         // Save comment meta
         if ($partner_id) {
             update_comment_meta($comment_id, 'partner', $partner_id);
         }

         if (!empty($sent_from)) {
             update_comment_meta($comment_id, 'sent_from', $sent_from);
         }

         // Set conversation as unread by customer
         update_post_meta($post_id, 'read_by_customer', 0);

         // Get comment object
         $comment_obj = get_comment($comment_id);

         // Retrieve stored meta
         $stored_partner_id = get_comment_meta($comment_id, 'partner', true);
         $stored_sent_from = get_comment_meta($comment_id, 'sent_from', true);

         return rest_ensure_response([
             'success'     => true,
             'message'     => __('Comment added successfully.', 'textdomain'),
             'comment_id'  => $comment_id,
             'author'      => $comment_obj->comment_author,
             'content'     => $comment_obj->comment_content,
             'date'        => $comment_obj->comment_date,
             'image_url'   => $image_url,
             'sent_from'   => $stored_sent_from ?: $sent_from,
             'partner_id'  => !empty($stored_partner_id) ? $stored_partner_id : ($partner_id ? $partner_id : null)
         ], 200);
     }

}
