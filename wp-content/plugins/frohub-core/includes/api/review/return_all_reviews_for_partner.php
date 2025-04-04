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

        register_rest_route('frohub/v1', '/reviews', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_reviews_by_partner_id'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Fetches all reviews associated with a given partner ID and calculates averages.
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
        $total_reviews = 0;
        $total_overall_rating = 0;
        $total_reliability = 0;
        $total_skill = 0;
        $total_professionalism = 0;

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

            // Get ACF fields
            $overall_rating  = get_field('overall_rating', $post_id);
            $reliability     = get_field('reliability', $post_id);
            $skill           = get_field('skill', $post_id);
            $professionalism = get_field('professionalism', $post_id);

            // Ensure values are numeric before adding to totals
            if (is_numeric($overall_rating)) {
                $total_overall_rating += $overall_rating;
            }
            if (is_numeric($reliability)) {
                $total_reliability += $reliability;
            }
            if (is_numeric($skill)) {
                $total_skill += $skill;
            }
            if (is_numeric($professionalism)) {
                $total_professionalism += $professionalism;
            }

            $total_reviews++;

            // Fetch the order object from ACF
            $order = get_field('order', $post_id);
            $filtered_service = null;
            $start_date_time = null;

            if (!empty($order) && is_object($order)) {
            $order_id = $order->ID; // Ensure we are working with an order ID

            // Get order items
            $order_items = wc_get_order($order_id)->get_items();

            foreach ($order_items as $item) {
            $product_id = $item->get_product_id();

            if ($product_id == 2600) {
            continue; // Ignore product ID 2600
            }

            // Get product title
            $filtered_service = get_the_title($product_id);

            // Fetch the start date time from line item meta
            $start_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);

            // If a valid service is found, break out of the loop
            break;
            }
            }


            $reviews[] = [
                'id'                => $post_id,
                'title'             => get_the_title(),
                'content'           => get_the_content(),
                'date'              => get_the_date(),
                'author'            => get_field('user', $post_id),
                'service_booked'    => $filtered_service, // Return only the Post Title
                'start_date_time'   => $start_date_time,  // Return Start Date Time
                'overall_rating'    => $overall_rating,
                'reliability'       => $reliability,
                'skill'             => $skill,
                'professionalism'   => $professionalism,
                'reply'             => "test" //$latest_comment // Return the latest reply (if exists)
                ];
        }

        wp_reset_postdata();

        // Calculate averages (handle division by zero)
        $stylist_overall_rating_average  = $total_reviews ? $total_overall_rating / $total_reviews : 0;
        $stylist_reliability_average     = $total_reviews ? $total_reliability / $total_reviews : 0;
        $stylist_skill_average           = $total_reviews ? $total_skill / $total_reviews : 0;
        $stylist_professionalism_average = $total_reviews ? $total_professionalism / $total_reviews : 0;

        // Prepare response
        $response = [
            'stylist_overall_rating_average'  => round($stylist_overall_rating_average, 2),
            'stylist_reliability_average'     => round($stylist_reliability_average, 2),
            'stylist_skill_average'           => round($stylist_skill_average, 2),
            'stylist_professionalism_average' => round($stylist_professionalism_average, 2),
            'reviews'                         => $reviews
        ];

        return rest_ensure_response($response);
    }
}
