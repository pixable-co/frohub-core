<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BroadcastMessage {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/broadcast-message', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('frohub/v1', '/broadcast-comment', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_conversation_comment'),
            'permission_callback' => '__return_true', // Modify for proper authentication if needed
        ));
    }

    /**
     * Handles the /broadcast-message API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'broadcast-message API endpoint reached',
        ), 200);
    }

    /**
     * Handles the /broadcast-comment API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_conversation_comment(\WP_REST_Request $request) {
        $conversations   = $request->get_param('conversations'); // Array of post IDs
        $message         = sanitize_textarea_field($request->get_param('message')); // Message content
        $partner_post_id = sanitize_text_field($request->get_param('partner_post_id')); // Partner post ID
        $partner_email      = sanitize_email($request->get_param('partner_email')); // User email

        // Validate input
        if (empty($conversations) || !is_array($conversations) || empty($message) || empty($partner_email) || empty($partner_post_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid input'], 400);
        }

        // Get the partner post title
        $partner_post = get_post($partner_post_id);
        $author_name = ($partner_post && !is_wp_error($partner_post)) ? get_the_title($partner_post_id) : "Anonymous Partner";

        $comments_created = [];

        // Loop through each conversation and create a comment
        foreach ($conversations as $conversation_id) {
            // Ensure the conversation post exists
            if (!get_post($conversation_id)) {
                continue; // Skip if post doesn't exist
            }

            // Prepare comment data
            $comment_data = [
                'comment_post_ID'      => $conversation_id,
                'comment_content'      => $message,
                'comment_author_email' => $partner_email,
                'comment_author'       => $author_name, // Use Partner Post Title as author name
                'comment_approved'     => 1, // Auto-approve comment
            ];

            // Insert comment into the conversation post
            $comment_id = wp_insert_comment($comment_data);

            update_field('partner', $partner_post_id, 'comment_' . $comment_id);

            if ($comment_id) {
                $comments_created[] = $comment_id;
            }
        }

        if (!empty($comments_created)) {
            return new \WP_REST_Response(['success' => true, 'message' => 'Comments created successfully', 'comments' => $comments_created], 200);
        } else {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to create comments'], 500);
        }
    }
}
