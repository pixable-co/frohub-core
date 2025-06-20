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
                $partner_id = intval($request->get_param('partner_id'));
                if (!$partner_id) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'Missing or invalid partner_id parameter.'
                    ], 400);
                }

                $args = array(
                    'post_type'      => 'conversation',
                    'posts_per_page' => -1,
                    'meta_query'     => array(
                        array(
                        'key'     => 'partner', // Query the Post Object field
                        'value'   => $partner_id, // The partner post ID you're passing
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

                    $customer = get_field('customer', $conversation_id);
                    $customer_name = 'Unknown Customer';
                    $customer_id = null;
                    $profile_image_url = null;

                    if (!empty($customer)) {
                        // Get the customer ID
                        if (is_numeric($customer)) {
                            $customer_id = $customer;
                        } elseif (is_object($customer) && isset($customer->ID)) {
                            $customer_id = $customer->ID;
                        } elseif (is_array($customer) && isset($customer['ID'])) {
                            $customer_id = $customer['ID'];
                        }

                        // Get user data if we have a valid ID
                        if ($customer_id) {
                            $user_info = get_userdata($customer_id);
                            if ($user_info) {
                                $first_name = $user_info->first_name;
                                $last_name = $user_info->last_name;
                                $billing_phone = get_user_meta($customer_id, 'billing_phone', true);

                                if (!empty($first_name) && !empty($last_name)) {
                                    $customer_name = $first_name . ' ' . $last_name;
                                } elseif (!empty($first_name)) {
                                    $customer_name = $first_name;
                                } elseif (!empty($last_name)) {
                                    $customer_name = $last_name;
                                } else {
                                    $customer_name = $user_info->display_name;
                                }
                            }

                            // Get profile image URL (ACF image field or user meta)
                            $user_image = get_field('user_image', 'user_' . $customer_id); // ACF-style
                            if (is_array($user_image) && isset($user_image['url'])) {
                                $profile_image_url = $user_image['url'];
                            } elseif (is_string($user_image)) {
                                $profile_image_url = $user_image;
                            }
                        }
                    }

                    $read_by_partner = get_field('read_by_partner', $conversation_id);
                    $last_activity = get_the_modified_date('c', $conversation_id) ?: get_the_date('c', $conversation_id);

                    $conversations[] = [
                        'conversation_id' => (int)$conversation_id,
                        'customer_id' => $customer_id,
                        'customer_name' => $customer_name,
                        'customer_image' => $profile_image_url,
                        'customer_phone' => $billing_phone,
                        'read_by_partner' => (bool)$read_by_partner,
                        'last_activity' => $last_activity ?: date('c'),
                        'permalink' => get_permalink($conversation_id),
                        'status' => 'Active',
                        'last_message' => '',
                    ];
                }
                    wp_reset_postdata();
                }

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