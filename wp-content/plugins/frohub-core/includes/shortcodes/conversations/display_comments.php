<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DisplayComments {

    public static function init() {
        $self = new self();
        add_shortcode('display_comments', array($self, 'display_comments_shortcode'));
    }

    public function display_comments_shortcode() {
        ob_start();

        $postId = get_the_ID(); // Get the current post ID
        echo
        $comments = get_comments(array(
            'post_id' => $postId,
        ));
        $currentUserId = get_current_user_id();
        $allComments = array();

        foreach ($comments as $comment) {
        $commentId = $comment->comment_ID; // Store comment ID in a variable
        $comment_meta = get_comment_meta($commentId);

        //Mark conversation as read by customer
        update_field('read_by_customer',1,$postId);

        $allComments[] = array(
        'comment_id' => $commentId,
        'user_id' => $comment->user_id,
        'author' => $comment->comment_author,
        'content' => $comment->comment_content,
        'date' => $comment->comment_date_gmt,
        );
        }

        // Sort by comment date (oldest to newest)
        usort($allComments, function ($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
        });
        ?>

        <div class="chat-container" id="chat-container" style="overflow-y: auto; max-height: 800px; height: 800px;">
        <div class="messages">
        <?php foreach ($allComments as $comment): ?>
        <div class="message <?php echo ($comment['user_id'] == $currentUserId) ? 'user' : 'partner'; ?>">
        <div class="bubble <?php echo ($comment['user_id'] == $currentUserId) ? 'user' : 'partner'; ?>">
        <strong><?php echo ($comment['user_id'] == $currentUserId) ? 'You' : esc_html($comment['author']); ?>:</strong> 
        <?php echo wp_kses_post($comment['content']); ?>
        <div class="timestamp"><?php echo esc_html($comment['date']); ?></div>
        </div>
        </div>
        <?php endforeach; ?>
            </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var chatContainer = document.getElementById('chat-container');
                chatContainer.scrollTop = chatContainer.scrollHeight;
            });
        </script>
        <?php
        return ob_get_clean();
    }
}