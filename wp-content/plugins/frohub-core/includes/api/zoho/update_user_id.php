<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateUserId {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('/v1', 'updateuseraccountId/', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'update_partner_account_id'),
            'permission_callback' => function () {
                                return current_user_can('manage_options');
            },
        ));
    }

    public function update_partner_account_id(WP_REST_Request $request) {
        // Retrieve the JSON payload from the request body
        $data = $request->get_json_params();

        // Extract variables from the payload
        $crmAccountId = $data['accountId'] ?? null;
        $partnerId = $data['partnerId'] ?? null;

        // Prepare response
        $response = [];

        // Validate input
        if ($crmAccountId == "" || $partnerId == "") {
            return new WP_REST_Response([
                'message' => 'Missing crmAccountID or email"' . $crmAccountId . " " . $partnerId,
            ], 400);
        }


        if ($partnerId) {
            // Update the ACF field leadid
           update_field('zoho_account_id', $crmAccountId, $partnerId);

           return new WP_Rest_Response([
                'message'=> 'Updated Successfully'. $partnerId ],200);


        } else {
            $response['message'] = 'User not found with the provided partner id.';
        }

        return new WP_REST_Response($response, 200);
    }
}