<?php
namespace FECore;

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
            'permission_callback' => function () {
                return current_user_can('manage_options'); // Modify permissions as needed
            },
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

        // Keep existing keys while adding new ones
        $partner_data = [
            'id'                 => $partner_post->ID,
            'title'              => $partner_post->post_title,
            'content'            => apply_filters('the_content', $partner_post->post_content),
            'featuredImage'      => $featured_image_url,
            'partnerName'        => get_the_title($partner_post_id),
            'serviceTypes'       => get_field('service_types', $partner_post_id),
            'partnerProfileUrl'  => get_field('partner_profile_url', $partner_post_id),
            'availability'       => get_field('availability', $partner_post_id),
            'bookingNotice'      => get_field('booking_notice', $partner_post_id),
            'bookingPeriod'      => get_field('booking_scope', $partner_post_id),
            'email'              => get_field('partner_email', $partner_post_id),
            'bufferPeriodMin'    => get_field('buffer_period_minutes', $partner_post_id),
            'bufferPeriodHour'   => get_field('buffer_period_hours', $partner_post_id),
        ];

        return rest_ensure_response($partner_data);
    }
}
