<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetMobileLocationData {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/get_mobile_location_data', array($self, 'get_mobile_location_data'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_frohub/get_mobile_location_data', array($self, 'get_mobile_location_data'));
    }

    public function get_mobile_location_data() {
        // Verify nonce
        check_ajax_referer('frohub_nonce', 'security');

        // Get the request data
        $partner_id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;

        if (!$partner_id) {
            wp_send_json_error(array(
                'message' => 'Missing or invalid partner ID.',
            ), 400);
        }

        // Validate if the partner post exists
        $post = get_post($partner_id);
        if (!$post || $post->post_type !== 'partner') {
            wp_send_json_error(array(
                'message' => 'Invalid partner ID or partner not found.',
            ), 404);
        }

        // Fetch ACF fields
        $latitude = get_field('latitude', $partner_id);
        $longitude = get_field('longitude', $partner_id);
        $radius_fees = get_field('radius_fees', $partner_id);

        // Format repeater field
        $formatted_fees = [];
        if (!empty($radius_fees) && is_array($radius_fees)) {
            foreach ($radius_fees as $fee) {
                $formatted_fees[] = [
                    'radius' => $fee['radius'],
                    'price'  => $fee['price'],
                ];
            }
        }

        // Build response data
        $data = [
            'post_id'    => $partner_id,
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'radius_fees' => $formatted_fees,
        ];

        // Send JSON response
        wp_send_json_success(array(
            'data' => $data,
        ));
    }
}
