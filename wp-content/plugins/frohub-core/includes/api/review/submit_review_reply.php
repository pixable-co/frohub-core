<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubmitReviewReply {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/submit-reply', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'submit_review_reply'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the /submit-reply API request for replying to reviews.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function submit_review_reply(\WP_REST_Request $request) {
        // Review ID being the Review Post ID
        $review_id  = $request->get_param('review_id');
        $reply_text = sanitize_textarea_field($request->get_param('reply_text'));
        $partner_id = sanitize_text_field($request->get_param('partner_id')); // Accept Partner ID

        // Validate inputs
        if (empty($review_id) || empty($reply_text) || empty($partner_id)) {
            return new \WP_Error('missing_data', 'Review ID, Partner ID, and reply text are required', ['status' => 400]);
        }

        // Check if the review post exists
        $review_post = get_post($review_id);
        if (!$review_post || $review_post->post_type !== 'review') {
            return new \WP_Error('invalid_review', 'Review not found', ['status' => 404]);
        }

        // Fetch Partner Email from ACF field
        $partner_email = get_field('partner_email', $partner_id);

        // Validate Partner Email
        if (empty($partner_email)) {
            return new \WP_Error('missing_partner_email', 'Partner email not found for this review', ['status' => 400]);
        }

        // Prepare comment data
        $comment_data = [
            'comment_post_ID'      => $review_id,
            'comment_content'      => $reply_text,
            'comment_author'       => $partner_email, // Use Partner Email as Author
            'comment_author_email' => $partner_email,
            'comment_approved'     => 1, // Auto-approve the reply
        ];

        // Insert comment into WordPress
        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return new \WP_Error('comment_failed', 'Failed to submit reply', ['status' => 500]);
        }

        return rest_ensure_response([
            'success'       => true,
            'message'       => 'Reply submitted successfully!',
            'comment_id'    => $comment_id,
            'partner_email' => $partner_email, // Return Partner Email for reference
        ]);
    }
}

