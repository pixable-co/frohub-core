<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class GetLocationData
{
    public static function init()
    {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes()
    {
        register_rest_route('frohub/v1', '/get-location-data/(?P<partner_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'partner_id' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }

    /**
     * Handles the API request to retrieve location data for a specific partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request)
    {
        $partner_id = intval($request->get_param('partner_id'));

        // Validate if the partner post exists
        $post = get_post($partner_id);
        if (!$post || $post->post_type !== 'partner') {
            return new \WP_REST_Response(array(
                'success' => false,
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

        return new \WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ), 200);
    }
}
