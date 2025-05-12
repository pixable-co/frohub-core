<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class DisplayExistingConversations
{

    public static function init()
    {
        $self = new self();
        add_shortcode('display_existing_conversations', array($self, 'display_existing_conversations_shortcode'));
    }

    public function display_existing_conversations_shortcode()
    {
        ob_start();

        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $current_post_id = get_queried_object_id();
            $requested_cid = isset($_GET['c_id']) ? absint($_GET['c_id']) : 0;

            $args = array(
                'post_type'      => 'conversation',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'     => 'customer',
                        'value'   => $current_user_id,
                        'compare' => '='
                    )
                )
            );

            $conversations = get_posts($args);

            // Enrich each conversation with latest comment time
            $conversations_with_comments = [];

            foreach ($conversations as $post) {
                $latest_comment = get_comments(array(
                    'post_id' => $post->ID,
                    'number'  => 1,
                    'orderby' => 'comment_date',
                    'order'   => 'DESC',
                    'status'  => 'approve',
                ));

                $latest_comment_time = !empty($latest_comment)
                    ? strtotime($latest_comment[0]->comment_date)
                    : 0;

                $conversations_with_comments[] = [
                    'post' => $post,
                    'latest_comment_time' => $latest_comment_time,
                    'latest_comment_date_formatted' => !empty($latest_comment)
                        ? (new \DateTime($latest_comment[0]->comment_date))->format('d M Y, H:i')
                        : '',
                ];
            }

            // Sort descending by latest comment time
            usort($conversations_with_comments, function ($a, $b) {
                return $b['latest_comment_time'] <=> $a['latest_comment_time'];
            });

            echo '<div class="messages-root-container">';
            echo '<div class="ongoing-conversations-list">';

            if (!empty($conversations_with_comments)) {
                foreach ($conversations_with_comments as $entry) {
                    $post = $entry['post'];
                    $conversation_id = $post->ID;
                    $latest_comment_time = $entry['latest_comment_date_formatted'];
                    $read_by_customer = get_field('read_by_customer', $conversation_id);
                    $partner = get_field('partner', $conversation_id); // Post object
                    $highlight_class = ($conversation_id == $requested_cid) ? 'active-conversation' : '';

                    echo '<a href="https://frohubecomm.mystagingwebsite.com/my-account/messages/?c_id=' . $conversation_id . '" class="ongoing-conversation ' . $highlight_class . '">';
                    echo '<div class="conversation-content">';
                    if ($latest_comment_time) {
                        echo '<p class="latest-comment-time">' . esc_html($latest_comment_time) . '</p>';
                    }
                    if ($partner) {
                        echo '<img class="partner-img" src="' . esc_url(get_the_post_thumbnail_url($partner->ID)) . '" alt="' . esc_attr(get_the_title($partner)) . '">';
                        echo '<p class="conversation-title">' . esc_html(get_the_title($partner)) . '</p>';
                    }
                    if (!$read_by_customer) {
                        echo ' <span class="red-dot"></span>';
                    }
                    echo '</div>';
                    echo '</a>';
                }
            } else {
                echo '<p>No conversations found.</p>';
            }

            echo '</div>'; // Close .ongoing-conversations-list

            if ($requested_cid) {
                echo '<div class="chat-window">';
                echo '<div class="messages-container">';
                echo do_shortcode('[display_comments]'); // Message display
                echo '</div>'; // Close .messages-container
                echo '<div class="chat-input-wrapper">';
                echo do_shortcode('[submit_comment]'); // Chat input
                echo '</div>'; // Close .chat-input-wrapper
                echo '</div>'; // Close .chat-window
            } else {
                echo '<div class="chat-window-placeholder">
                    <p class="no-chat-selected">
                        <strong>👋🏾 Welcome to your inbox!</strong>
                        When you book a service or send an enquiry, you\'ll see your messages here.
                        Need help? Start a conversation with your stylist.
                    </p>
                </div>';
            }

            echo '</div>'; // Close .messages-root-container

        } else {
            echo '<p>You must be logged in to view your conversations.</p>';
        }

        return ob_get_clean();
    }
}
