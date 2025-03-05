<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreatePartnerPost {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/create-partner-post', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_partner_post'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Sends an update to the external user service.
     *
     * @param int $partner_id
     * @param int $wp_id
     * @return mixed
     */
    private function update_user_partner_post_id($partner_id, $wp_id) {
        $payload = [
            'wp_id' => $wp_id,
            'partner_post_id' => $partner_id,
        ];

        $url = 'https://frohubpartners.mystagingwebsite.com/wp-json/v1/user/update';

        $response = wp_remote_post($url, [
            'body'    => json_encode($payload),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ZnJvaHViLXBhcnRuZXItYXBpOnZoNFZnNVVkZk5UaTdLVllRelJKIER2eE0=',
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending data to external endpoint: ' . $response->get_error_message());
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Creates a new Partner post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_partner_post(\WP_REST_Request $request) {
    $data = $request->get_json_params();
    $wordpressUserId = $data['21'] ?? null;

    if (!$wordpressUserId) {
        return new \WP_REST_Response([
            'message' => 'User ID not found.',
        ], 400);
    }

    $businessName = $data['3'] ?? null;
    $noticePeriod = $data['9'] ?? null;
    $advanceBooking = $data['10'] ?? null;
    $frohubProfileURL = $data['4'] ?? null;
    $homeBased = $data['16.1'] ?? null;
    $salonBased = $data['16.2'] ?? null;
    $mobile = $data['16.3'] ?? null;
    $availability = $data['29'] ?? null;
    $serviceName = $data['13'] ?? null;
    $duration = $data['32'] ?? null;  // New Duration Field
    $price = $data['15'] ?? null;
    $bufferTime = $data['31'] ?? null; // New Buffer Time Field

    $partner_post_id = wp_insert_post([
        'post_title'  => $businessName,
        'post_type'   => 'partner',
        'post_status' => 'draft',
    ]);

    if (is_wp_error($partner_post_id)) {
        return new \WP_REST_Response([
            'message' => 'Failed to create partner post.',
        ], 500);
    }

    if (!empty($availability)) {
        $availability_rows = [];

        foreach ($availability as $slot) {
            $availability_rows[] = [
                'day'  => $slot['1'] ?? '',
                'from' => $slot['3'] ?? '',
                'to'   => $slot['4'] ?? '',
            ];
        }

        update_field('availability', $availability_rows, $partner_post_id);
    }

    if ($noticePeriod !== null) {
        update_field('booking_notice', $noticePeriod, $partner_post_id);
    }

    if ($advanceBooking !== null) {
        update_field('booking_scope', $advanceBooking, $partner_post_id);
    }

    $serviceTypes = [];
    if ($homeBased) $serviceTypes[] = 'Home-based';
    if ($salonBased) $serviceTypes[] = 'Salon-based';
    if ($mobile) $serviceTypes[] = 'Mobile';

    update_field('service_types', $serviceTypes, $partner_post_id);

    if ($frohubProfileURL) {
        update_field('partner_profile_url', $frohubProfileURL, $partner_post_id);
    }

    $update_user_field = update_field('partner_post_id', $partner_post_id, 'user_' . $wordpressUserId);
    update_field('wp_user', $wordpressUserId, $partner_post_id);

    $external_response = $this->update_user_partner_post_id($partner_post_id, $wordpressUserId);

    $response = [
        'partner_post_id' => $partner_post_id,
        'acf_update'      => $update_user_field ? 'User partner field updated successfully.' : 'Failed to update user partner field.',
        'external_response' => $external_response,
    ];

    $service_data = [
        'WordPress_ID'   => $wordpressUserId,
        'Service_Name'   => $serviceName,
        'Duration'       => $duration, // New Duration Field
        'Buffer_Time'    => $bufferTime, // New Buffer Time Field
        'Price'          => $price,
        'Partner_ID'     => $partner_post_id,
        'Partner_Name'   => $businessName,
    ];

    update_field('draft_service', json_encode($service_data), $partner_post_id);
    $response['partner_id'] = $partner_post_id;

    return new \WP_REST_Response($response, 200);
    }

}
