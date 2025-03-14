<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DisplayExistingConversations {

    public static function init() {
        $self = new self();
        add_shortcode('display_existing_conversations', array($self, 'display_existing_conversations_shortcode'));
    }

    public function display_existing_conversations_shortcode() {
        ob_start();

        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $current_post_id = get_queried_object_id(); // Get the current viewed post ID

            $args = array(
                'post_type'      => 'conversation',
                'posts_per_page' => -1, // Get all conversations
                'meta_query'     => array(
                    array(
                        'key'     => 'customer', // ACF field storing user ID
                        'value'   => $current_user_id,
                        'compare' => '='
                    )
                )
            );

            $query = new \WP_Query($args);

            if ($query->have_posts()) {
                echo '<div class="ongoing-conversations-list">';
                
                while ($query->have_posts()) {
                    $query->the_post();
                    $conversation_id = get_the_ID();
                    $read_by_customer = get_field('read_by_customer', $conversation_id);

                    // Highlight class if it's the current post
                    $highlight_class = ($conversation_id == $current_post_id) ? 'highlight' : '';

                    echo '<a href="' . get_permalink() . '" class="ongoing-conversation ' . $highlight_class . '">';
                    echo '<div class="conversation-content">';
                    echo '<h5 class="conversation-title">' . get_the_title();

                    // Add red dot if "read_by_customer" is false
                    if (!$read_by_customer) {
                        echo ' <span class="red-dot"></span>';
                    }

                    echo '</h5>';
                    echo '</div>';
                    echo '</a>';
                }

                echo '</div>';
                wp_reset_postdata();
            } else {
                echo '<p>No conversations found.</p>';
            }
        } else {
            echo '<p>You must be logged in to view your conversations.</p>';
        }

        return ob_get_clean();
    }
}
