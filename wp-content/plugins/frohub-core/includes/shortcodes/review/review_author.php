<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewAuthor {

    public static function init() {
        $self = new self();
        add_shortcode( 'review_author', array($self, 'review_author_shortcode') );
    }

    public function review_author_shortcode() {
        ob_start();

        $review_id = get_the_ID();
        $user = get_field('user', $review_id);

        if ($user) {
            $user_id = $user['ID']; // Get the user ID
            $first_name = get_the_author_meta('first_name', $user_id);
            $last_name = get_the_author_meta('last_name', $user_id);

            echo esc_html($first_name . ' ' . $last_name);
        } else {
            echo 'No user assigned.';
        }

        return ob_get_clean();
    }
}
