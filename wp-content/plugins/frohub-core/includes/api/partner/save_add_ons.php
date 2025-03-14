<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SaveAddOns {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/save-add-ons', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'save_all_partner_addons'),
            'permission_callback' => '__return_true', // Restrict if needed
        ));
    }

    /**
     * Handles the request to save partner add-ons.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function save_all_partner_addons(\WP_REST_Request $request) {
        $params = $request->get_json_params();

        // Validate required fields
        if (!isset($params['partner_id'], $params['add_ons']) || !is_array($params['add_ons'])) {
            return new \WP_REST_Response(['message' => 'Missing required fields or incorrect format.'], 400);
        }

        $partner_id = intval($params['partner_id']);
        $new_add_ons = $params['add_ons'];

        // Check if partner post exists
        if (!get_post($partner_id)) {
            return new \WP_REST_Response(['message' => 'Invalid partner_id.'], 404);
        }

        // Get current add-ons from ACF
        $existing_add_ons = get_field('add_ons', $partner_id);
        if (!$existing_add_ons) {
            $existing_add_ons = []; // Initialize if empty
        }

        $updated_add_ons = [];

        // Loop through new data and update or retain existing values
        foreach ($new_add_ons as $addon) {
            if (!isset($addon['add_on_term_id'], $addon['price'], $addon['duration_minutes'])) {
                continue; // Skip invalid entries
            }

            $term_id = intval($addon['add_on_term_id']);
            $price = floatval($addon['price']);
            $duration = intval($addon['duration_minutes']);

            // Check if the term already exists in the repeater field
            $existing_index = array_search($term_id, array_column($existing_add_ons, 'add_on'));

            if ($existing_index !== false) {
                // Update existing entry
                $existing_add_ons[$existing_index]['price'] = $price;
                $existing_add_ons[$existing_index]['duration_minutes'] = $duration;
                $updated_add_ons[] = $existing_add_ons[$existing_index];
            } else {
                // Add a new add-on
                $updated_add_ons[] = [
                    'add_on'           => $term_id,
                    'price'            => $price,
                    'duration_minutes' => $duration
                ];
            }
        }

        // Ensure valid updates
        if (empty($updated_add_ons)) {
            return new \WP_REST_Response(['message' => 'No valid add-ons to update.', 'success' => false], 400);
        }

        // Update ACF field
        update_field('add_ons', $updated_add_ons, $partner_id);

        return new \WP_REST_Response([
            'message' => 'Add-ons updated successfully.',
            'success' => true
        ], 200);
    }
}


