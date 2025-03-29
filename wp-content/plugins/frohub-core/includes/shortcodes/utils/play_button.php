<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PlayButton {

    public static function init() {
        $self = new self();
        add_shortcode('play_button', array($self, 'play_button_shortcode'));
    }

    public function play_button_shortcode() {
        $post_id = get_the_ID();
        $post_type = get_post_type($post_id);

        if ($post_type === 'tutorial') {
            return do_shortcode('[us_iconbox icon="fas|play-circle" link="%7B%22url%22%3A%22%22%7D" size="50px"]');
        }

        return '';
    }
}
