<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateLocationData {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/update-location-data', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */

    public function handle_request(\WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (empty($params['partner_id'])) {
                return new \WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Missing Partner ID fields.',
                ), 400);
        }

        $post_id = intval($params['partner_id']);
        $latitude = sanitize_text_field($params['latitude']);
        $longitude = sanitize_text_field($params['longitude']);
        $radius_fees = $params['radius_fees']; // Expecting an array

            // Verify post exists and is of correct type
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'partner') {
                return new \WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Invalid partner ID.',
                ), 404);
            }

            update_field('latitude', $latitude, $post_id);
            update_field('longitude', $longitude, $post_id);

            if (is_array($radius_fees)) {
                delete_field('radius_fees', $post_id); // Clear existing data
                foreach ($radius_fees as $fee) {
                    if (isset($fee['radius'], $fee['price'])) {
                        add_row('radius_fees', array(
                            'radius' => sanitize_text_field($fee['radius']),
                            'price'  => sanitize_text_field($fee['price']),
                        ), $post_id);
                    }
                }
            }

            return new \WP_REST_Response(array(
                'success' => true,
                'message' => 'Location data updated successfully.',
                'post_id' => $post_id
            ), 200);
        }
}