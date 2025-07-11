<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerUnreadConversations {

    public static function init() {
        $self = new self();
        add_shortcode( 'customer_unread_conversations', array($self, 'customer_unread_conversations_shortcode') );
    }

    public function customer_unread_conversations_shortcode() {
        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            return '';
        }

        $count = $this->get_unread_conversation_count_for_user($current_user_id);

        return '<a href="/my-account/messages"><i class="fas fa-comments"></i> (' . intval($count) . ')</a>';
    }

    private function get_unread_conversation_count_for_user($user_id) {
        global $wpdb;

        // Step 1: Get all conversation post IDs where current user has commented
        $comment_post_ids = $wpdb->get_col( $wpdb->prepare("
            SELECT DISTINCT comment_post_ID
            FROM {$wpdb->comments}
            WHERE user_id = %d
        ", $user_id) );

        if ( empty( $comment_post_ids ) ) {
            return 0;
        }

        // Step 2: Sum unread_count_customer where post ID is in that list
        $placeholders = implode(',', array_fill(0, count($comment_post_ids), '%d'));

        $sql = "
            SELECT SUM(CAST(pm.meta_value AS UNSIGNED)) as total_unread
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'unread_count_customer'
              AND CAST(pm.meta_value AS UNSIGNED) > 0
              AND p.post_type = 'conversation'
              AND p.post_status = 'publish'
              AND p.ID IN ($placeholders)
        ";

        $prepared = $wpdb->prepare($sql, ...$comment_post_ids);
        $count = (int) $wpdb->get_var($prepared);

        return $count;
    }
}
