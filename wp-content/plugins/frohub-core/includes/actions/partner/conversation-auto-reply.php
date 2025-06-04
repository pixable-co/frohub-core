<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversationAutoReply {

    public static function init() {
        $self = new self();
        add_action('wp_insert_comment', [$self, 'maybe_add_auto_reply'], 10, 2);
    }

    /**
     * Trigger auto-reply for new comments on conversation post type
     */
    public function maybe_add_auto_reply($comment_id, $comment_object) {
        $post_id = $comment_object->comment_post_ID;
        $post_type = get_post_type($post_id);

        // Only apply to 'conversation' post type
        if ($post_type !== 'conversation') {
            return;
        }

        // Avoid infinite loops or re-replying
        if (get_comment_meta($comment_id, 'is_auto_reply', true)) {
            return;
        }

        // Optional: Check if the comment is from the customer, not from partner
        $sent_from = get_comment_meta($comment_id, 'sent_from', true);
        if ($sent_from === 'partner') {
            return;
        }

        // Compose auto-reply comment
        $auto_reply = [
            'comment_post_ID'      => $post_id,
            'comment_author'       => 'AutoResponder',
            'comment_author_email' => 'noreply@example.com',
            'comment_content'      => 'Thanks, we’ll get back to you shortly.',
            'comment_type'         => '',
            'comment_approved'     => 1,
            'user_id'              => 0,
            'comment_parent'       => 0,
        ];

        // Insert the comment
        $auto_comment_id = wp_insert_comment($auto_reply);

        if ($auto_comment_id) {
            add_comment_meta($auto_comment_id, 'is_auto_reply', 1, true);
            add_comment_meta($auto_comment_id, 'sent_from', 'partner', true);
        }
    }
}
