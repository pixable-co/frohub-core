<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
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
        register_rest_route('/v1', '/partner/get-partner-data', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'get_partner_acf_fields'),
            'permission_callback' => '__return_true',
            'args' => [
                        'partner_post_id' => [
                        'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ));
    }

    public function get_partner_acf_fields(WP_REST_Request $request) {
        $partner_post_id = $request['partner_post_id'];

        // Get Partner Post information
        $partnerName = get_the_title($partner_post_id);
        $serviceTypes = get_field('service_types', $partner_post_id);
        $partner_profile_url = get_field('partner_profile_url', $partner_post_id);
        $availability = get_field('availability', $partner_post_id);
        $booking_notice = get_field('booking_notice', $partner_post_id);
        $booking_period = get_field('booking_period', $partner_post_id);

        // Prepare response data
        $acf_fields = [
            'partnerName' => $partnerName,
            'serviceTypes' => $serviceTypes,
            'partnerProfileUrl' => $partner_profile_url,
            'availability' => $availability,
            'bookingNotice' => $booking_notice,
            'bookingPeriod' => $booking_period,
        ];

        // Return ACF fields in the response as JSON
        return rest_ensure_response($acf_fields);
    }
}