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
                while ($query->have_posts()) {
                    $conversation_id = get_the_ID();
                    
                    // Fetch unread comments
                    $unread_comments = get_comments(array(
                        'post_id' => $conversation_id,
                        'meta_query' => array(
                            array(
                                'key'     => 'partner', // Only comments with 'partner'
                                'compare' => 'EXISTS'
                            ),
                            array(
                                'key'     => 'has_been_read_by_user', // Only unread comments
                                'value'   => false,
                                'compare' => '='
                            )
                        )
                    ));

                    $unread_count = count($unread_comments);

                    echo '<div class="ongoing-conversation">';
                    echo '<h4><a href="' . get_permalink() . '">' . get_the_title() . ($unread_count > 0 ? " ({$unread_count})" : '') . '</a></h4>';
                    echo '</div>';
                }
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