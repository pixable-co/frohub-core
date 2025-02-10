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
        register_rest_route('/v1/partner/', 'create/', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_partner_post'),
            'permission_callback' => function () {
                                return current_user_can('manage_options');
            },
        ));
    }

    public function update_user_partner_post_id($partner_id, $wp_id) {
        // Prepare payload
        $payload = [
            'wp_id' => $wp_id,
            'partner_post_id' => $partner_id,
        ];

        // Endpoint URL
        $url = 'https://frohubpartners.mystagingwebsite.com/wp-json/v1/user/update';

        // Send request to external endpoint
        $response = wp_remote_post($url, [
            'body'    => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
    			'Authorization' => 'Basic ZnJvaHViLXBhcnRuZXItYXBpOnZoNFZnNVVkZk5UaTdLVllRelJKIER2eE0=',
            ],
        ]);

        // Handle response
        if (is_wp_error($response)) {
            error_log('Error sending data to external endpoint: ' . $response->get_error_message());
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    public function create_partner_post(WP_REST_Request $request) {
        // Retrieve the JSON data from the request body
        $data = $request->get_json_params();

        // Extract variables from the JSON data
        $wordpressUserId = $data['21'] ?? null;

        // Check if WordPress user ID is provided
        if (!$wordpressUserId) {
            return new WP_REST_Response([
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
        $availability = $data['29'] ?? null; // Adjusted key for availability
        $serviceName = $data['13'] ?? null;
        $durationHours = $data['23'] ?? null;
        $durationMinutes = $data['24'] ?? null;
        $price = $data['15'] ?? null;

        // Prepare response
        $response = [];

        // Create a new Partner post
        $partner_post_id = wp_insert_post([
            'post_title'   => $businessName,
            'post_type'    => 'partner',
            'post_status'  => 'draft',
        ]);

        if (is_wp_error($partner_post_id)) {
            return new WP_REST_Response([
                'message' => 'Failed to create partner post.',
            ], 500);
        }

        // Handle availability field
        if (!empty($availability)) {
            $availability_rows = [];

            foreach ($availability as $slot) {
                $availability_rows[] = [
                    'day' => $slot['1'] ?? '',  // Map ACF 'day' to '1' in payload
                    'from' => $slot['3'] ?? '', // Map ACF 'from' to '3' in payload
                    'to' => $slot['4'] ?? '', // Map ACF 'till' to '4' in payload
                ];
            }

            update_field('availability', $availability_rows, $partner_post_id);
        }

        if ($noticePeriod !== null) {
            update_field('booking_notice', $noticePeriod, $partner_post_id);
        }

        if ($advanceBooking !== null) {
            update_field('booking_period', $advanceBooking, $partner_post_id);
        }

        $serviceTypes = [];
        if ($homeBased) $serviceTypes[] = 'Home-based';
        if ($salonBased) $serviceTypes[] = 'Salon-based';
        if ($mobile) $serviceTypes[] = 'Mobile';
        update_field('service_types', $serviceTypes, $partner_post_id);

        if ($frohubProfileURL) {
            update_field('partner_profile_url', $frohubProfileURL, $partner_post_id);
        }

        // Update the user's ACF field 'partner' with the Partner post ID
        $update_user_field = update_field('partner_post_id', $partner_post_id, 'user_' . $wordpressUserId);
        update_field('wp_user', $wordpressUserId, $partner_post_id);

        // Send payload to external endpoint
        $external_response = update_user_partner_post_id($partner_post_id, $wordpressUserId);

        $response['partner_post_id'] = $partner_post_id;
        $response['acf_update'] = $update_user_field ? 'User partner field updated successfully.' : 'Failed to update user partner field.';
        $response['external_response'] = $external_response;

        // Add service details to the partner post
        $service_data = [
            'WordPress_ID' => $wordpressUserId,
            'Service_Name' => $serviceName,
            'Duration_Hours' => $durationHours,
            'Duration_Minutes' => $durationMinutes,
            'Price' => $price,
            'Partner_ID' => $partner_post_id,
            'Partner_Name' => $businessName,
        ];

        $json_service_data = json_encode($service_data);
        update_field('draft_service', $json_service_data, $partner_post_id);

        // Add partner ID to response
        $response['partner_id'] = $partner_post_id;

        // Return response
        return new WP_REST_Response($response, 200);
    }

}