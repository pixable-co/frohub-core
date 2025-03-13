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
					$query->the_post();
					$conversation_id = get_the_ID();
                    
                    echo '<div class="ongoing-conversation">';
                    echo '<h5 class="conversation-title"><a href="' . get_permalink() . '">' . get_the_title() .'</a></h5>';
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