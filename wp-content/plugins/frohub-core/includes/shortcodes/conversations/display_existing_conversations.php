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
                    
					// Fetch unread comments
					$unread_comments = get_comments(array(
						'post_id' => $conversation_id, // Ensure this uses the correct conversation post ID
						'meta_query' => array(
							array(
								'key'     => 'partner', // Ensure comments have a 'partner' field
								'compare' => 'EXISTS'
							),
							array(
								'relation' => 'OR', // Handle different possible values
								array(
									'key'     => 'has_been_read_by_user', // Unread comments
									'value'   => '0', // ACF might store false as '0'
									'compare' => '='
								),
								array(
									'key'     => 'has_been_read_by_user', // If the field does not exist
									'compare' => 'NOT EXISTS'
								)
							)
						)
					));


    				$unread_count = count($unread_comments);
					 
                    echo '<div class="ongoing-conversation">';
                    echo '<h5 class="conversation-title"><a href="' . get_permalink() . '">' . get_the_title() .'</a></h5>';
					if($unread_count > 0)
					{
						echo '<p> You have '.$unread_count.' unread messages. </p>';
					}
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