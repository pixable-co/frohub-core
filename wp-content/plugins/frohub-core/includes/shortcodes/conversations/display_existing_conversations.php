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

            $query = new \WP_Query($args);

            echo '<div class="chat-container">';
            echo '<div class="ongoing-conversations-list">';

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $conversation_id = get_the_ID();
                    $read_by_customer = get_field('read_by_customer', $conversation_id);
                    $partner = get_field('partner', $conversation_id); // This is a post object
                    $highlight_class = ($conversation_id == $current_post_id) ? 'highlight' : '';

                    echo '<a href="https://frohubecomm.mystagingwebsite.com/my-account/messages/?c_id=' . $conversation_id . '" class="ongoing-conversation ' . $highlight_class . '">';
                    echo '<div class="conversation-content">';
                    echo '<img class="partner-img" src="' . esc_url(get_the_post_thumbnail_url($partner->ID)) . '" alt="' . esc_attr(get_the_title($partner)) . '">';
                    echo '<h5 class="conversation-title">' . get_the_title($partner);

                    if (!$read_by_customer) {
                        echo ' <span class="red-dot"></span>';
                    }

                    echo '</h5>';
                    echo '</div>';
                    echo '</a>';
                }
                wp_reset_postdata();
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

            echo '</div>'; // Close .chat-container

        } else {
            echo '<p>You must be logged in to view your conversations.</p>';
        }

        return ob_get_clean();
    }
}
