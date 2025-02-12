<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateZohoAccountId {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/update-zoho-account-id', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'update_partner_account_id'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Updates the Zoho Account ID for a given partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_partner_account_id(\WP_REST_Request $request) {
        // Retrieve the JSON payload from the request body
        $data = $request->get_json_params();

        // Extract variables from the payload
        $crmAccountId = $data['accountId'] ?? null;
        $partnerId = $data['partnerId'] ?? null;

        // Validate input
        if (empty($crmAccountId) || empty($partnerId)) {
            return rest_ensure_response([
                'error'   => true,
                'message' => 'Missing required parameters: accountId or partnerId.',
            ], 400);
        }

        // Update the ACF field 'zoho_account_id'
        $update_status = update_field('zoho_account_id', $crmAccountId, $partnerId);

        if ($update_status) {
            return rest_ensure_response([
                'success'      => true,
                'message'      => 'Zoho Account ID updated successfully.',
                'partnerId'    => $partnerId,
                'crmAccountId' => $crmAccountId,
            ], 200);
        }

        return rest_ensure_response([
            'error'   => true,
            'message' => 'Failed to update Zoho Account ID.',
        ], 500);
    }
}
