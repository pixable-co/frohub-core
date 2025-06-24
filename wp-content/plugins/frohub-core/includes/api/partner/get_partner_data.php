<?php
namespace FECore;

use FECore\Helper;

if (!defined('ABSPATH')) {
    exit;
}

class GetPartnerData {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-partner-data', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'get_partner_acf_fields'),
            'args' => [
                'partner_post_id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ));
    }

    /**
     * Retrieves ACF fields for a given 'partner' post type, keeping existing keys while adding new fields.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_partner_acf_fields(\WP_REST_Request $request) {
        $partner_post_id = $request->get_param('partner_post_id');

        // Validate if the partner post exists
        $partner_post = get_post($partner_post_id);
        if (!$partner_post || $partner_post->post_type !== 'partner') {
            return new \WP_REST_Response(['error' => 'Invalid partner post ID'], 400);
        }

        // Get Featured Image URL
        $featured_image_url = get_the_post_thumbnail_url($partner_post_id, 'full') ?: '';
        $reviews = $this->get_partner_reviews($partner_post_id);
        $upcoming_bookings = Helper::get_next_upcoming_order_by_partner($partner_post_id);
        $pending_orders_count = $this->get_pending_orders_count($partner_post_id);
        $vacation_status = $this->get_vacation_status($partner_post_id);
        $stripe_data = $this->get_stripe_data($partner_post_id);

        // Keep existing keys while adding new ones
        $partner_data = [
            'id'                 => $partner_post->ID,
            'title'              => $partner_post->post_title,
            'content'            => apply_filters('the_content', $partner_post->post_content),
            'featuredImage'      => $featured_image_url,
            'bannerImage'        => get_field('hero_image', $partner_post_id),
            'partnerName'        => get_the_title($partner_post_id),
            'serviceTypes'       => get_field('service_types', $partner_post_id),
            'partnerProfileUrl'  => get_field('partner_profile_url', $partner_post_id),
            'availability'       => get_field('availability', $partner_post_id),
            'bookingNotice'      => get_field('booking_notice', $partner_post_id),
            'bookingScope'      => get_field('booking_scope', $partner_post_id),
            'email'              => get_field('partner_email', $partner_post_id),
            'bufferPeriodMin'    => get_field('buffer_period_minutes', $partner_post_id),
            'bufferPeriodHour'   => get_field('buffer_period_hours', $partner_post_id),
            'address'           => get_field('partner_address', $partner_post_id),
            // New ACF fields added
            'streetAddress'      => get_field('street_address', $partner_post_id),
            'city'               => get_field('city', $partner_post_id),
            'countyDistrict'    => get_field('county_district', $partner_post_id),
            'postcode'           => get_field('postcode', $partner_post_id),
            'latitude'           => get_field('latitude', $partner_post_id),
            'longitude'         => get_field('longitude', $partner_post_id),
            'phone'            => get_field('phone', $partner_post_id),
            'terms'            => get_field('terms_and_conditions', $partner_post_id),
            'lateFees'         => get_field('late_fees', $partner_post_id),
            'payments'         => get_field('payments', $partner_post_id),
            'reviews'         => $reviews,
            'mobileServiceFee' => get_field('radius_fees', $partner_post_id),
            'auto_message' => get_field('auto_message', $partner_post_id),
            'auto_message_text' => get_field('auto_message_text', $partner_post_id),
            'upcomingBookings' => $upcoming_bookings,
            'pendingOrdersCount' => $pending_orders_count,
            'onVacation' => $vacation_status,
            'stripeUserId'       => $stripe_data['stripe_user_id'] ?? '',
            'stripeConnected'    => !empty($stripe_data['stripe_user_id']),
            'showStripeWarning'  => empty($stripe_data['stripe_user_id']),
        ];

        return rest_ensure_response($partner_data);
    }

     /**
      * Fetch reviews related to the given partner ID.
      *
      * @param int $partner_id
      * @return array
      */
     private function get_partner_reviews($partner_id) {
         $reviews = [];

         $args = [
             'post_type'      => 'review',
             'posts_per_page' => -1,
             'meta_query'     => [
                 [
                     'key'     => 'partner', // ACF field key for the post object
                     'value'   => strval($partner_id), // Ensure we match against the ID as a string
                     'compare' => '='
                 ]
             ]
         ];

         $query = new \WP_Query($args);

         if ($query->have_posts()) {
             while ($query->have_posts()) {
                 $query->the_post();
                 $reviews[] = [
                     'id'      => get_the_ID(),
                     'title'   => get_the_title(),
                     'content' => apply_filters('the_content', get_the_content()),
                     'rating'  => get_field('overall_rating'), // Assuming there's a rating field in ACF
                     'author'  => get_the_author(),
                     'date'    => get_the_date()
                 ];
             }
             wp_reset_postdata();
         }

         return $reviews;
     }

     /**
      * Get count of pending orders for a partner.
      *
      * @param int $partner_id
      * @return int
      */
     private function get_pending_orders_count($partner_id) {
         $query = new \WP_Query(array(
             'post_type'      => 'shop_order',
             'post_status'    => 'wc-on-hold',
             'posts_per_page' => -1,
             'meta_query'     => array(
                 array(
                     'key'     => 'partner_name', // ACF field
                     'value'   => $partner_id,
                     'compare' => '='
                 ),
             ),
         ));

         return $query->found_posts;
     }

    /**
     * Get vacation status for a partner.
     *
     * @param int $partner_id
     * @return bool
     */
    private function get_vacation_status($partner_id) {
        // Use field name and force boolean logic
        $vacation_status = get_field('on_vacation', $partner_id);

        // Force to true/false explicitly
        $is_on_vacation = ($vacation_status === true || $vacation_status === '1' || $vacation_status === 1);

        return $is_on_vacation;
    }

    /**
     * Get Stripe account data for a partner.
     *
     * @param int $partner_id
     * @return array
     */
    private function get_stripe_data($partner_id) {
        // Get the Stripe Account ID directly from ACF field
        $stripe_account_id = get_field('stripe_account_id', $partner_id);

        $stripe_data = [];
        if (!empty($stripe_account_id)) {
            $stripe_data['stripe_user_id'] = $stripe_account_id;
        }

        return $stripe_data;
    }
}
