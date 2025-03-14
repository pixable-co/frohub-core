<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerAddOns {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/partner-add-ons/(?P<partner_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_specific_partner_add_ons'),
            'permission_callback' => '__return_true', // Modify for authentication if needed
        ));
    }

    /**
     * Retrieves add-ons for a specific partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_specific_partner_add_ons(\WP_REST_Request $request) {
        $partner_id = intval($request['partner_id']);

        // Validate if the partner exists
        if (get_post_type($partner_id) !== 'partner') {
            return new \WP_Error('invalid_partner', 'Invalid partner ID', ['status' => 404]);
        }

        // Fetch the ACF repeater field 'add_ons'
        $add_ons = get_field('add_ons', $partner_id);

        if (empty($add_ons)) {
            return new \WP_REST_Response([
                'partner_id'   => $partner_id,
                'partner_name' => get_the_title($partner_id),
                'add_ons'      => []
            ], 200);
        }

        $add_ons_data = array_map(function ($add_on) {
            return [
                'add_on'    => is_object($add_on['add_on']) ? $add_on['add_on']->name : '',
                'add_on_id' => is_object($add_on['add_on']) ? $add_on['add_on']->term_id : '',
                'price'     => $add_on['price'],
                'duration'  => $add_on['duration_minutes'],
            ];
        }, $add_ons);

        return rest_ensure_response([
            'partner_id'   => $partner_id,
            'partner_name' => get_the_title($partner_id),
            'add_ons'      => $add_ons_data
        ]);
    }
}


