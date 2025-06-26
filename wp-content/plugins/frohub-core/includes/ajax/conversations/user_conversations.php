<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserConversations {

    public static function init() {
        $self = new self();
        add_action('wp_ajax_frohub/user_conversations', array($self, 'user_conversations'));
        add_action('wp_ajax_frohub/get_conversation_comments', array($self, 'get_all_conversation_comments'));
        add_action('wp_ajax_frohub/send_customer_message', array($self, 'send_customer_message'));

        add_action('wp_ajax_nopriv_frohub/user_conversations', array($self, 'user_conversations'));
        add_action('wp_ajax_nopriv_frohub/get_conversation_comments', array($self, 'get_all_conversation_comments'));
        add_action('wp_ajax_nopriv_frohub/send_customer_message', array($self, 'send_customer_message'));
    }

    public function user_conversations() {
        check_ajax_referer('frohub_nonce');
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'User not logged in.'], 403);
        }

        $args = array(
            'post_type'      => 'conversation',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'customer',
                    'value'   => $user_id,
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
                $partner = get_field('partner', $conversation_id);
                $partner_name = 'Unknown Partner';
                $partner_id = null;
                $partner_image_url = null;

                if (!empty($partner)) {
                    $partner_id = is_numeric($partner) ? $partner : ($partner->ID ?? $partner['ID'] ?? null);
                    if ($partner_id) {
                        $partner_name = get_the_title($partner_id);
                        $partner_image = get_field('profile_image', $partner_id);
                        $partner_image_url = is_array($partner_image) ? ($partner_image['url'] ?? null) : $partner_image;
                    }
                }

                $read_by_customer = get_field('read_by_customer', $conversation_id);
                $last_activity = get_the_modified_date('c', $conversation_id) ?: get_the_date('c', $conversation_id);

                $comments = get_comments([
                    'post_id' => $conversation_id,
                    'status'  => 'approve',
                    'order'   => 'ASC',
                ]);

                $comment_data = array_map(function($comment) {
                    return [
                        'comment_id'     => $comment->comment_ID,
                        'author'         => $comment->comment_author,
                        'author_email'   => $comment->comment_author_email,
                        'content'        => $comment->comment_content,
                        'date'           => $comment->comment_date,
                    ];
                }, $comments);

                $conversations[] = [
                    'conversation_id'   => (int) $conversation_id,
                    'partner_id'        => $partner_id,
                    'partner_name'      => $partner_name,
                    'partner_image'     => $partner_image_url,
                    'read_by_customer'  => (bool) $read_by_customer,
                    'last_activity'     => $last_activity ?: date('c'),
                    'permalink'         => get_permalink($conversation_id),
                    'status'            => 'Active',
                    'last_message'      => '',
                    'comments'          => $comment_data,
                ];
            }
            wp_reset_postdata();
        }

        usort($conversations, function($a, $b) {
            return strtotime($b['last_activity']) - strtotime($a['last_activity']);
        });

        wp_send_json_success(['data' => $conversations]);
    }

    public function get_all_conversation_comments() {
        check_ajax_referer('frohub_nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'User not logged in.'], 403);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if ($post_id) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'conversation') {
                wp_send_json_error(['message' => 'Invalid conversation.'], 400);
            }

            $comments = get_comments([
                'post_id' => $post_id,
                'status'  => 'approve',
                'order'   => 'ASC',
            ]);

            $result = array_map(function($comment) use ($post_id) {
                return [
                    'conversation_id' => $post_id,
                    'comment_id'      => $comment->comment_ID,
                    'author'          => $comment->comment_author,
                    'author_email'    => $comment->comment_author_email,
                    'content'         => $comment->comment_content,
                    'date'            => $comment->comment_date,
                    'meta_data'       => get_comment_meta($comment->comment_ID),
                ];
            }, $comments);

            wp_send_json_success([
                'comments'        => $result,
                'user_partner_id' => get_field('partner', $post_id)
            ]);

        } else {
            // Fallback to return all conversations’ comments (original logic)
            $args = [
                'post_type'      => 'conversation',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'customer',
                        'value'   => $user_id,
                        'compare' => '='
                    ]
                ]
            ];

            $query = new \WP_Query($args);
            $all_comments = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $conversation_id = get_the_ID();

                    $comments = get_comments([
                        'post_id' => $conversation_id,
                        'status'  => 'approve',
                        'order'   => 'ASC',
                    ]);

                    foreach ($comments as $comment) {
                        $all_comments[] = [
                            'conversation_id' => $conversation_id,
                            'comment_id'      => $comment->comment_ID,
                            'author'          => $comment->comment_author,
                            'author_email'    => $comment->comment_author_email,
                            'content'         => $comment->comment_content,
                            'date'            => $comment->comment_date,
                            'meta_data'       => get_comment_meta($comment->comment_ID),
                        ];
                    }
                }
                wp_reset_postdata();
            }

            wp_send_json_success(['comments' => $all_comments]);
        }
    }

        public function send_customer_message() {
            check_ajax_referer('frohub_nonce');

            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error(['message' => 'User not logged in.'], 403);
            }

            $post_id     = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $partner_id  = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;
            $comment     = isset($_POST['comment']) ? wp_kses_post($_POST['comment']) : '';
            $image_url   = isset($_POST['image_url']) ? esc_url($_POST['image_url']) : '';

            if (!$post_id || get_post_type($post_id) !== 'conversation') {
                wp_send_json_error(['message' => 'Invalid conversation post.']);
            }

            if (empty($comment) && empty($image_url)) {
                wp_send_json_error(['message' => 'Message cannot be empty.']);
            }

            $author = wp_get_current_user();
            $author_name = $author->display_name;
            $author_email = $author->user_email;

            if (!empty($image_url)) {
                $comment .= '<br><img src="' . esc_url($image_url) . '" alt="Uploaded Image" style="max-width: 100%; height: auto;">';
            }

            $comment_data = [
                'comment_post_ID'      => $post_id,
                'comment_author'       => $author_name,
                'comment_author_email' => $author_email,
                'user_id'              => $user_id,
                'comment_content'      => $comment,
                'comment_approved'     => 1,
            ];

            $comment_id = wp_insert_comment($comment_data);

            if (!$comment_id) {
                wp_send_json_error(['message' => 'Failed to post comment.']);
            }

            update_comment_meta($comment_id, 'sent_from', 'customer');
            if ($partner_id) {
                update_comment_meta($comment_id, 'partner', $partner_id);
            }

            update_post_meta($post_id, 'read_by_partner', 0);

            // 🚀 Notify Partner via Webhook (Zoho Flow)
            $partner_user = get_user_by('ID', $partner_id);
            $partner_email = $partner_user ? $partner_user->user_email : '';
            
            $customer_user = get_user_by('ID', $user_id);
            $client_first_name = $customer_user ? $customer_user->first_name : 'Customer';
            $partner_name = $partner_id ? get_the_title($partner_id) : 'Pixable Stylist';

            if (!empty($partner_email)) {
                $this->notify_partner_message($partner_email, $client_first_name, $partner_name);
            }

            // ✅ AUTO MESSAGE HANDLING
            $auto_enabled = get_field('auto_message', $partner_id);
            if ($auto_enabled) {
                $this->trigger_auto_reply($partner_id, $post_id); // internal REST call
            }

            $comment_obj = get_comment($comment_id);

            wp_send_json_success([
                'comment_id'   => $comment_obj->comment_ID,
                'author'       => $comment_obj->comment_author,
                'author_email' => $comment_obj->comment_author_email,
                'content'      => $comment_obj->comment_content,
                'date'         => $comment_obj->comment_date,
                'meta_data'    => [
                    'sent_from' => ['customer']
                ],
                'partner_id'   => $partner_id
            ]);
        }

        private function trigger_auto_reply($partner_id, $conversation_id) {
            $url = rest_url('frohub/v1/send-auto-message');

            $response = wp_remote_post($url, [
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'partner_id'      => $partner_id,
                    'conversation_id' => $conversation_id,
                ]),
            ]);

            // Optional: Log error if needed
            if (is_wp_error($response)) {
                error_log('Auto message failed: ' . $response->get_error_message());
            }
        }

        private function notify_partner_message($partner_email, $client_first_name, $partner_name = 'Pixable Stylist') {
            $url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming';
            $params = [
                'zapikey' => '1001.e83523e791d77d7d52578d8a6bf2d8fe.2bd19f022b6f6c88bbf0fa6d7da05c4d',
                'isdebug' => 'false'
            ];
            $endpoint = add_query_arg($params, $url);

            $body = [
                'partner_email'    => $partner_email,
                'client_first_name'=> $client_first_name,
                'partner_name'     => $partner_name,
            ];

            $response = wp_remote_post($endpoint, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode($body),
            ]);

            if (is_wp_error($response)) {
                error_log('Zoho Flow notify error: ' . $response->get_error_message());
            }
        }
}
