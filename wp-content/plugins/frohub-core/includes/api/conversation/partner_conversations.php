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
                            $user_image = get_field('user_image', 'user_' . $customer_id); // ACF field
                            if (is_array($user_image) && isset($user_image['url'])) {
                                $profile_image_url = $user_image['url'];
                            } elseif (is_string($user_image)) {
                                $profile_image_url = $user_image;
                            }

                            // Fallback to meta field or WordPress avatar
                            if (!$profile_image_url) {
                                $avatar_attachment_id = get_user_meta($customer_id, 'yith-wcmap-avatar', true);
                                if ($avatar_attachment_id) {
                                    $profile_image_url = wp_get_attachment_url($avatar_attachment_id);
                                } else {
                                    $profile_image_url = get_avatar_url($customer_id, ['size' => 96]);
                                }
                            }
                        }
                    }

                    $read_by_partner = get_field('read_by_partner', $conversation_id);
                    $unread_count_partner = (int) get_post_meta($conversation_id, 'unread_count_partner', true);
                    $last_activity = get_the_modified_date('c', $conversation_id) ?: get_the_date('c', $conversation_id);
                    $auto_message_enabled = get_field('auto_message', $partner_id);

                    // WooCommerce Data
                    $total_completed_bookings = 0;
                    $last_booking_date = '';

                    if ($customer_id) {
                        $orders = wc_get_orders([
                            'customer_id' => $customer_id,
                            'status'      => 'completed',
                            'limit'       => -1,
                            'meta_key'    => 'partner_id',
                            'meta_value'  => $partner_id,
                            'meta_compare'=> '=',
                        ]);

                        $total_spent       = 0.0;
                        $total_completed_bookings = count($orders);
                        $first_order_ts    = null;

                            foreach ($orders as $order) {
                                   $created = $order->get_date_created();
                                    if ($created) {
                                        $ts = $created->getTimestamp();
                                        if ($first_order_ts === null || $ts < $first_order_ts) {
                                            $first_order_ts = $ts;
                                        }
                                    }

                                    $order_total = 0.0;
                                    foreach ($order->get_items() as $item) {
                                        if ((int)$item->get_product_id() === 28990) {
                                            continue; // skip Frohub booking fee
                                        }

                                        $order_total += (float) $item->get_subtotal();

                                        $meta_total_due = $item->get_meta('Total Due on the Day', true);
                                        if ($meta_total_due) {
                                            $meta_total_due = (float) preg_replace('/[^\d.]/', '', (string)$meta_total_due);
                                            $order_total += $meta_total_due;
                                        }
                                    }

                                    $total_spent += $order_total;
                                    $last_order = $orders[0];
                                    $last_booking_date = $last_order->get_date_created() ? $last_order->get_date_created()->date('Y-m-d') : '';
                        }
                    }


                    $partner_image_url = null;
                    $partner_post = get_field('partner', $conversation_id);
                    if ($partner_post && is_object($partner_post) && isset($partner_post->ID)) {
                        $partner_thumb_id = get_post_thumbnail_id($partner_post->ID);
                        if ($partner_thumb_id) {
                            $partner_image_url = wp_get_attachment_url($partner_thumb_id);
                        }
                    }

                    // Fetch last comment (message) for this conversation
                    $last_message_content = '';
                    $last_message_date    = '';

                    $comments = get_comments([
                        'post_id' => $conversation_id,
                        'number'  => 1,
                        'orderby' => 'comment_date',
                        'order'   => 'DESC',
                    ]);

                    if (!empty($comments)) {
                        $last_comment = $comments[0];
                        $last_message_content = $last_comment->comment_content;
                        $last_message_date    = $last_comment->comment_date_gmt
                            ? get_date_from_gmt($last_comment->comment_date_gmt, 'c')
                            : $last_comment->comment_date;
                    }

                    $conversations[] = [
                        'conversation_id' => (int)$conversation_id,
                        'customer_id' => $customer_id,
                        'customer_name' => $customer_name,
                        'customer_image' => $profile_image_url,
                        'partner_image' => $partner_image_url,
                        'customer_phone' => $billing_phone,
                        'read_by_partner' => (bool)$read_by_partner,
                        'auto_message' => (bool) $auto_message_enabled,
                        'unread_count_partner' => (int)$unread_count_partner,
                        'last_activity' => $last_activity ?: date('c'),
                        'permalink' => get_permalink($conversation_id),
                        'status' => 'Active',
                        'last_message' => $last_message_content,
                        'last_message_date' => $last_message_date,
                        'total_completed_bookings' => $total_completed_bookings,
                        'total_spend' => number_format($total_spent, 2),
                        'last_booking_date' => $last_booking_date,
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