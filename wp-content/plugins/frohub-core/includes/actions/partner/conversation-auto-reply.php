<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversationAutoReply {

    public static function init() {
        $self = new self();
        add_action('wp_insert_comment', [$self, 'maybe_add_auto_reply'], 20, 2);
    }

    public function maybe_add_auto_reply($comment_id, $comment) {
        $post_id = $comment->comment_post_ID;

        // Only for conversation post type
        if (get_post_type($post_id) !== 'conversation') {
            return;
        }

        // Prevent auto-reply loop (don't reply to partner messages)
        $sent_from = get_comment_meta($comment_id, 'sent_from', true);
        if ($sent_from === 'partner') {
            return;
        }

        // Insert auto-reply comment
        $auto_reply_id = wp_insert_comment([
            'comment_post_ID'       => $post_id,
            'comment_author'        => 'AutoResponder',
            'comment_author_email'  => 'noreply@example.com',
            'comment_content'       => 'Thanks, we’ll get back to you shortly.',
            'comment_approved'      => 1,
            'comment_type'          => '',
            'user_id'               => 0,
            'comment_parent'        => 0,
        ]);

        // Mark it as partner-sent to avoid recursion
        if ($auto_reply_id) {
            add_comment_meta($auto_reply_id, 'sent_from', 'partner');
        }
    }
}
