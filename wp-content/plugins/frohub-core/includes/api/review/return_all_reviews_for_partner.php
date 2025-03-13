<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReturnAllReviewsForPartner {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/return-all-reviews-for-partner', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('frohub/v1', '/reviews', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_reviews_by_partner_id'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the /return-all-reviews-for-partner API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'return-all-reviews-for-partner API endpoint reached',
        ), 200);
    }

    /**
     * Fetches all reviews associated with a given partner ID.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_reviews_by_partner_id(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        if (!$partner_id) {
            return new \WP_Error('missing_partner_id', 'Partner ID is required', ['status' => 400]);
        }

        $args = [
            'post_type'      => 'review',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'partner', // ACF field name
                    'value'   => $partner_id,
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return new \WP_Error('no_reviews_found', 'No reviews found for this partner', ['status' => 404]);
        }

        $reviews = [];

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Fetch comments (replies) for this review
            $comments_query = get_comments([
                'post_id' => $post_id,
                'status'  => 'approve',
                'number'  => 1, // Only fetch the latest comment
                'order'   => 'DESC'
            ]);

            $latest_comment = !empty($comments_query) ? $comments_query[0]->comment_content : null;

            $reviews[] = [
                'id'                => $post_id,
                'title'             => get_the_title(),
                'content'           => get_the_content(),
                'date'              => get_the_date(),
                'author'            => get_the_author(),
                'service_booked'    => get_field('service_booked', $post_id),
                'overall_rating'    => get_field('overall_rating', $post_id),
                'reliability'       => get_field('reliability', $post_id),
                'skill'             => get_field('skill', $post_id),
                'professionalism'   => get_field('professionalism', $post_id),
                'reply'             => $latest_comment // Return the latest reply (if exists)
            ];
        }

        wp_reset_postdata();

        return rest_ensure_response($reviews);
    }
}
