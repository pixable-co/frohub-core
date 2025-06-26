<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) exit;

class ConversationImport {

    public static function init() {
        $self = new self();

        add_action('wp_ajax_frohub/resolve_user_id_by_email', [$self, 'resolve_user_id_by_email']);
        add_action('wp_ajax_frohub/create_or_update_conversation', [$self, 'create_or_update_conversation']);
        add_action('wp_ajax_frohub/import_conversation_comment', [$self, 'import_conversation_comment']);
    }

    public function resolve_user_id_by_email() {
        check_ajax_referer('frohub_nonce');
        $email = sanitize_email($_POST['email'] ?? '');
        if (!$email || !is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email.']);
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found.']);
        }

        wp_send_json_success(['user_id' => $user->ID]);
    }

    public function create_or_update_conversation() {
        check_ajax_referer('frohub_nonce');

        $partner_id = intval($_POST['partner_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $external_ref_id = sanitize_text_field($_POST['external_ref_id'] ?? '');

        if (!$partner_id || !$customer_id) {
            wp_send_json_error(['message' => 'Missing partner or customer ID.']);
        }

        // Try to find existing conversation
        $existing = get_posts([
            'post_type'      => 'conversation',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'customer',
                    'value' => $customer_id,
                ],
                [
                    'key'   => 'partner',
                    'value' => $partner_id,
                ],
            ]
        ]);

        if (!empty($existing)) {
            $conversation_id = $existing[0]->ID;
        } else {
            $customer_user = get_user_by('ID', $customer_id);
            $partner_user = get_user_by('ID', $partner_id);

            $customer_name = $customer_user ? $customer_user->display_name : 'Customer';
            $partner_name = $partner_user ? $partner_user->display_name : 'Partner';

            $conversation_id = wp_insert_post([
                'post_type'   => 'conversation',
                'post_title'  => $customer_name . ' x ' . $partner_name,
                'post_status' => 'publish',
                'post_author' => $partner_id ?: 0,
            ]);

            if (is_wp_error($conversation_id)) {
                wp_send_json_error(['message' => 'Failed to create conversation.']);
            }

            update_field('partner', $partner_id, $conversation_id);
            update_field('customer', $customer_id, $conversation_id);
            update_field('partner_client_post_id', $external_ref_id, $conversation_id);
            update_field('read_by_customer', 1, $conversation_id);
            update_field('read_by_partner', 0, $conversation_id);
        }

        wp_send_json_success(['conversation_id' => $conversation_id]);
    }

    public function import_conversation_comment() {
        check_ajax_referer('frohub_nonce');

        $conversation_id = intval($_POST['post_id'] ?? 0);
        $partner_id = intval($_POST['partner_id'] ?? 0);
        $comment = wp_kses_post($_POST['comment'] ?? '');
        $comment_date = sanitize_text_field($_POST['comment_date'] ?? '');
        $sent_from = sanitize_key($_POST['sent_from'] ?? 'customer');
        $author_email = sanitize_email($_POST['author_email'] ?? '');

        if (!$conversation_id || empty($comment) || empty($author_email)) {
            wp_send_json_error(['message' => 'Missing required data.']);
        }

        $author_user = get_user_by('email', $author_email);

        $comment_data = [
            'comment_post_ID'      => $conversation_id,
            'comment_author'       => $author_user ? $author_user->display_name : $author_email,
            'comment_author_email' => $author_email,
            'user_id'              => $author_user ? $author_user->ID : 0,
            'comment_content'      => $comment,
            'comment_approved'     => 1,
        ];

        if (!empty($comment_date)) {
            $comment_data['comment_date'] = $comment_date;
        }

        $comment_id = wp_insert_comment($comment_data);

        if ($comment_id) {
            update_comment_meta($comment_id, 'sent_from', $sent_from);
            update_comment_meta($comment_id, 'partner', $partner_id);
            wp_send_json_success(['comment_id' => $comment_id]);
        }

        wp_send_json_error(['message' => 'Failed to insert comment.']);
    }
}
