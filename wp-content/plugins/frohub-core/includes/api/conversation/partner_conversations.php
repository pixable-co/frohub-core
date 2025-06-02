<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerConversations {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/partner-conversations', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }


    public function handle_request(\WP_REST_Request $request) {
        try {
            // Get partner_id from request query
            $partner_id = intval($request->get_param('partner_id'));

            if (!$partner_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Missing or invalid partner_id parameter.'
                ], 400);
            }

            // Query for conversation posts associated with this partner_id
            $args = array(
                'post_type'      => 'conversation',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'     => 'partner_id', // Assumes 'partner_id' is stored as meta on conversations
                        'value'   => $partner_id,
                        'compare' => '='
                    )
                ),
                'post_status'    => 'publish'
            );

            $query = new \WP_Query($args);
            $conversations = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $conversation_id = get_the_ID();

                    $customer_name = get_field('customer_name', $conversation_id) ?: 'Client #' . $conversation_id;
                    $read_by_partner = get_field('read_by_partner', $conversation_id);
                    $last_activity = get_the_modified_date('c', $conversation_id) ?: get_the_date('c', $conversation_id);

                    $conversations[] = [
                        'conversation_id' => (int)$conversation_id,
                        'customer_name' => $customer_name,
                        'read_by_partner' => (bool)$read_by_partner,
                        'last_activity' => $last_activity ?: date('c'),
                        'permalink' => get_permalink($conversation_id),
                        'status' => 'Active',
                        'last_message' => '', // Optional: Can add logic to fetch last message
                    ];
                }
                wp_reset_postdata();
            }

            // Sort conversations by last activity descending
            if (!empty($conversations)) {
                usort($conversations, function($a, $b) {
                    return strtotime($b['last_activity']) - strtotime($a['last_activity']);
                });
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $conversations
            ], 200);

        } catch (\Exception $e) {
            error_log('Error in handle_request: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'An error occurred while fetching conversations.'
            ], 500);
        } catch (\Error $e) {
            error_log('Fatal error in handle_request: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'A fatal error occurred.'
            ], 500);
        }
    }
}