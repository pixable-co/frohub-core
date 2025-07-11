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
//             'permission_callback' => function () {
//                 return current_user_can('edit_posts');
//             },
            'permission_callback' => '__return_true',
        ));
    }

    public function frohub_upload_comment_image(\WP_REST_Request $request) {
        if (empty($_FILES['file'])) {
            return new \WP_REST_Response(['error' => 'No file uploaded.'], 400);
        }

        $file = $_FILES['file'];
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            return new \WP_REST_Response(['error' => $attachment_id->get_error_message()], 500);
        }

        $url = wp_get_attachment_url($attachment_id);
        return new \WP_REST_Response(['success' => true, 'url' => $url], 200);
    }

//     public function upload_comment_image() {
//         check_ajax_referer('fpserver_nonce');
//         if (!is_user_logged_in()) {
//             wp_send_json_error(['message' => 'User not logged in.']);
//         }
//         if (empty($_FILES['file'])) {
//             wp_send_json_error(['message' => 'No file uploaded.']);
//         }
//
//         $file = $_FILES['file'];
//         $basicAuth = get_field('frohub_ecommerce_basic_authentication', 'option');
//
//         $curl = curl_init();
//         $cfile = curl_file_create($file['tmp_name'], $file['type'], $file['name']);
//
//         $data = ['file' => $cfile];
//
//         curl_setopt_array($curl, [
//             CURLOPT_URL => 'https://frohubecomm.mystagingwebsite.com/wp-json/frohub/v1/upload-comment-image',
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_POST => true,
//             CURLOPT_POSTFIELDS => $data,
//             CURLOPT_HTTPHEADER => [
//                 'Authorization: ' . $basicAuth
//             ],
//         ]);
//
//         $response = curl_exec($curl);
//         $error = curl_error($curl);
//         curl_close($curl);
//
//         if ($error) {
//             wp_send_json_error(['message' => 'cURL error: ' . $error]);
//         }
//
//         $response_body = json_decode($response, true);
//         if (!empty($response_body['success']) && !empty($response_body['url'])) {
//             wp_send_json_success(['url' => $response_body['url']]);
//         } else {
//             wp_send_json_error(['message' => $response_body['error'] ?? 'Unknown error']);
//         }
//
//         wp_die();
//     }

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
             $previous_unread_count = (int) get_post_meta($post_id, 'unread_count_customer', true);
             $new_unread_count = $previous_unread_count + 1;
             update_post_meta($post_id, 'read_by_customer', 0);
             update_post_meta($post_id, 'unread_count_customer', $new_unread_count);
         }

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
