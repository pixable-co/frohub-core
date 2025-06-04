<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class ConversationAutoReply {

    public static function init() {
        $self = new self();

        // Hook into comment creation
        add_action('comment_post', array($self, 'handle_new_conversation'), 10, 3);
    }

    /**
     * Handle new conversation comment and call internal REST API
     */
    public function handle_new_conversation($comment_id, $comment_approved, $commentdata) {
        // Log to confirm the hook is firing
        error_log('Auto-reply triggered for comment ID: ' . $comment_id);

        // Optional: Only proceed if comment is approved
        if ($comment_approved !== 1) {
            return;
        }

        // Get full comment object
        $comment = get_comment($comment_id);

        // Get post ID from comment
        $post_id = $comment->comment_post_ID;

        // Ensure this is a "conversation" post type
        if (get_post_type($post_id) !== 'conversation') {
            return;
        }

        // Get user info
        $user_id = $comment->user_id;
        $user = get_userdata($user_id);

        // Build the payload to send to your own REST API
        $payload = array(
            'post_id'     => $post_id,
            'partner_id'  => get_post_meta($post_id, 'partner', true), // assuming partner is stored in post meta
            'comment'     => $comment->comment_content,
            'author_name' => $comment->comment_author,
            'email'       => $comment->comment_email,
            'sent_from'   => 'system:auto_reply',
        );

        // URL to your own REST API endpoint
        $api_url = rest_url('frohub/v1/create-comment');

        // Make the internal REST API call
        $response = wp_remote_post($api_url, array(
            'method'    => 'POST',
            'timeout'   => 10,
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
            'body'      => json_encode($payload),
            'sslverify' => false, // Set to true in production if SSL is available
        ));

        // Log errors if any
        if (is_wp_error($response)) {
            error_log('Internal API call failed: ' . $response->get_error_message());
        } else {
            $body = wp_remote_retrieve_body($response);
            error_log('API Response: ' . $body);
        }
    }
}
