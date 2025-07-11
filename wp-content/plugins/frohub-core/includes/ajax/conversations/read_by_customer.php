<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReadByCustomer {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/read_by_customer', array($self, 'read_by_customer'));
        // add_action('wp_ajax_nopriv_frohub/read_by_customer', array($self, 'read_by_customer'));
    }

    public function read_by_customer() {
        check_ajax_referer('frohub_nonce');

        $post_id = isset($_POST['conversation_post_id']) ? intval($_POST['conversation_post_id']) : 0;

        if (! $post_id || get_post_type($post_id) !== 'conversation') {
            wp_send_json_error([
                'message' => 'Invalid or missing conversation_post_id.',
            ], 400);
        }

        // Update the conversation post meta
        update_post_meta($post_id, 'read_by_customer', 1);
        update_post_meta($post_id, 'unread_count_customer', 0);

        wp_send_json_success([
            'message' => 'Conversation marked as read by customer.',
            'post_id' => $post_id,
        ]);
    }
}
