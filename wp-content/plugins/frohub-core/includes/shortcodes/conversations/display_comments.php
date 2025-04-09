<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DisplayCommentsPartner {

    public static function init() {
        $self = new self();
        add_shortcode('display_partner_comments', array($self, 'display_partner_comments_shortcode'));
    }

    public function display_partner_comments_shortcode() {
        ob_start();

        $postId = get_the_ID(); // Current client post
        $ecommConversationPostId = get_field('ecommerce_conversation_post_id', $postId);
        $basicAuth = get_field('frohub_ecommerce_basic_authentication', 'option');

        $response = wp_remote_post('https://frohubecomm.mystagingwebsite.com/wp-json/frohub/v1/get-conversation-comments', [
            'body' => json_encode(['conversation_post_id' => $ecommConversationPostId]),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => $basicAuth,
            ],
        ]);

        $allComments = [];

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $comments = json_decode(wp_remote_retrieve_body($response), true);

            if (is_array($comments)) {
                $allComments = array_map(function ($comment) {
                    return [
                        'comment_id' => $comment['comment_id'],
                        'author'     => $comment['author'],
                        'content'    => $comment['content'],
                        'date'       => $comment['date'],
                        'meta_data'  => $comment['meta_data'] ?? [],
                        'partner_id' => $comment['meta_data']['partner'][0] ?? null,
                    ];
                }, $comments);
            }
        }

        // Sort by date
        usort($allComments, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $currentUserId = get_current_user_id();
        $userPartnerPostId = get_field('partner_post_id', 'user_' . $currentUserId);

        // Allowed tags for content rendering
        $allowed_tags = [
            'p' => [], 'br' => [], 'strong' => [], 'em' => [],
            'ul' => [], 'ol' => [], 'li' => [],
            'a' => ['href' => [], 'title' => []],
            'img' => ['src' => [], 'alt' => [], 'width' => [], 'height' => []],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'blockquote' => [], 'code' => [], 'pre' => [],
        ];
        ?>

        <div class="chat-container" id="chat-container" style="overflow-y: auto; max-height: 800px; height: 800px;">
            <div class="messages">
                <?php foreach ($allComments as $comment): ?>
                    <?php 
                        $isPartnerMessage = ($comment['partner_id'] == $userPartnerPostId);
                    ?>
                    <div class="message <?php echo $isPartnerMessage ? 'user' : 'partner'; ?>">
                        <div class="bubble <?php echo $isPartnerMessage ? 'user' : 'partner'; ?>">
                            <strong><?php echo $isPartnerMessage ? 'You' : esc_html($comment['author']); ?>:</strong>
                            <?php echo wp_kses($comment['content'], $allowed_tags); ?>
                            <div class="timestamp"><?php echo esc_html($comment['date']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var chatContainer = document.getElementById('chat-container');
            chatContainer.scrollTop = chatContainer.scrollHeight;
        });
        </script>

        <?php
        return ob_get_clean();
    }
}
