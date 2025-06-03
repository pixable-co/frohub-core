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
         $meta_data   = isset($parameters['meta_data']) ? $parameters['meta_data'] : array();

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

         // Validate meta data (ensure it's an array or valid JSON)
         if (!empty($meta_data)) {
             if (is_string($meta_data)) {
                 $decoded_meta = json_decode($meta_data, true);
                 if (json_last_error() !== JSON_ERROR_NONE) {
                     return rest_ensure_response([
                         'error'   => true,
                         'message' => __('Invalid meta data format. Must be valid JSON.', 'textdomain')
                     ], 400);
                 }
                 $meta_data = $decoded_meta;
             }

             if (!is_array($meta_data)) {
                 return rest_ensure_response([
                     'error'   => true,
                     'message' => __('Meta data must be an array or valid JSON object.', 'textdomain')
                 ], 400);
             }
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

         // Store meta data as comment meta
         if (!empty($meta_data) && is_array($meta_data)) {
             foreach ($meta_data as $meta_key => $meta_value) {
                 // Sanitize meta key
                 $sanitized_key = sanitize_key($meta_key);

                 // Handle different value types
                 if (is_array($meta_value)) {
                     // For arrays (like your partner array), store as-is
                     $sanitized_value = array_map('sanitize_text_field', $meta_value);
                 } else {
                     // For single values, sanitize as text
                     $sanitized_value = sanitize_text_field($meta_value);
                 }

                 // Store each meta data item
                 update_comment_meta($comment_id, $sanitized_key, $sanitized_value);
             }

             // Store the entire meta data array as a single meta field for backup/reference
             update_comment_meta($comment_id, 'comment_meta_data', $meta_data);
         }

         // Set conversation as read by customer to false
         update_post_meta($post_id, 'read_by_customer', 0);

         // Get the inserted comment data for response
         $comment_obj = get_comment($comment_id);

         // Retrieve stored meta data
         $stored_meta_data = get_comment_meta($comment_id, 'comment_meta_data', true);
         $stored_partner_id = get_comment_meta($comment_id, 'partner', true);

         // If no meta data was stored via meta_data parameter, check if partner was stored via partner_id parameter
         if (empty($stored_meta_data) && $partner_id) {
             $stored_meta_data = array('partner' => $partner_id);
         }

         return rest_ensure_response([
             'success'     => true,
             'message'     => __('Comment added successfully.', 'textdomain'),
             'comment_id'  => $comment_id,
             'author'      => $comment_obj->comment_author,
             'content'     => $comment_obj->comment_content,
             'date'        => $comment_obj->comment_date,
             'image_url'   => $image_url,
             'meta_data'   => !empty($stored_meta_data) ? $stored_meta_data : [],
             'partner_id'  => !empty($stored_partner_id) ? $stored_partner_id : ($partner_id ? $partner_id : null)
         ], 200);
     }
//     public function handle_conversation_comment(\WP_REST_Request $request) {
//         $parameters = $request->get_params();
//
//         $post_id     = isset($parameters['post_id']) ? intval($parameters['post_id']) : 0;
//         $partner_id  = isset($parameters['partner_id']) ? intval($parameters['partner_id']) : 0;
//         $comment     = isset($parameters['comment']) ? wp_kses_post($parameters['comment']) : '';
//         $author_name = isset($parameters['author_name']) ? sanitize_text_field($parameters['author_name']) : '';
//         $email       = isset($parameters['email']) ? sanitize_email($parameters['email']) : '';
//         $image_url   = isset($parameters['image_url']) ? esc_url($parameters['image_url']) : '';
//         $meta_data   = isset($parameters['meta_data']) ? $parameters['meta_data'] : array();
//
//         $user_id = get_current_user_id();
//
//         // Validate post ID
//         if (!$post_id || get_post_type($post_id) !== 'conversation') {
//             return rest_ensure_response([
//                 'error'   => true,
//                 'message' => __('Invalid conversation post.', 'textdomain')
//             ], 400);
//         }
//
//         // Validate comment content
//         if (empty($comment) && empty($image_url)) {
//             return rest_ensure_response([
//                 'error'   => true,
//                 'message' => __('Comment cannot be empty if no image is provided.', 'textdomain')
//             ], 400);
//         }
//
//         // Validate partner ID (optional)
//         if ($partner_id && !get_post($partner_id)) {
//             return rest_ensure_response([
//                 'error'   => true,
//                 'message' => __('Invalid partner ID.', 'textdomain')
//             ], 400);
//         }
//
//         // Validate meta data (ensure it's an array or valid JSON)
//             if (!empty($meta_data)) {
//                 if (is_string($meta_data)) {
//                     $decoded_meta = json_decode($meta_data, true);
//                     if (json_last_error() !== JSON_ERROR_NONE) {
//                         return rest_ensure_response([
//                             'error'   => true,
//                             'message' => __('Invalid meta data format. Must be valid JSON.', 'textdomain')
//                         ], 400);
//                     }
//                     $meta_data = $decoded_meta;
//                 }
//
//                 if (!is_array($meta_data)) {
//                     return rest_ensure_response([
//                         'error'   => true,
//                         'message' => __('Meta data must be an array or valid JSON object.', 'textdomain')
//                     ], 400);
//                 }
//             }
//
//         // Use logged-in user display name if author_name is not provided
//         if (empty($author_name)) {
//             $author_name = wp_get_current_user()->display_name;
//         }
//
//         // Append image to the comment if an image URL is provided
//         if (!empty($image_url)) {
//             $comment .= '<br><img src="' . esc_url($image_url) . '" alt="Comment Image" style="max-width: 100%; height: auto;">';
//         }
//
//         // Prepare comment data
//         $comment_data = array(
//             'comment_post_ID'      => $post_id,
//             'comment_author'       => $author_name,
//             'comment_author_email' => $email,
//             'user_id'              => $user_id,
//             'comment_content'      => $comment,
//             'comment_approved'     => 1, // Automatically approve the comment
//         );
//
//         // Insert comment
//         $comment_id = wp_insert_comment($comment_data);
//
//         if (!$comment_id) {
//             return rest_ensure_response([
//                 'error'   => true,
//                 'message' => __('Failed to post comment.', 'textdomain')
//             ], 500);
//         }
//
//         // Store partner ID as comment meta (optional)
//         if ($partner_id) {
//             update_comment_meta($comment_id, 'partner', $partner_id);
//         }
//
//         // Set conversation as read by customer to false
//         update_post_meta($post_id, 'read_by_customer', 0);
//
//         return rest_ensure_response([
//             'success'    => true,
//             'message'    => __('Comment added successfully.', 'textdomain'),
//             'comment_id' => $comment_id,
//             'image_url'  => $image_url
//         ], 200);
//     }
}
