<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CommunityPostType {

    public static function init() {
        $self = new self();
        add_shortcode('community_post_type', array($self, 'community_post_type_shortcode'));
    }

    public function community_post_type_shortcode() {
        $post_type = get_post_type(get_the_ID());

        $label = '';
        if ($post_type === 'q-a') {
            $label = 'Q&A';
        } elseif ($post_type === 'post') {
            $label = 'Blog';
        } elseif ($post_type === 'tutorial') {
            $label = 'Tutorials';
        }

        return '<span class="community_post_type">' . esc_html($label) . '</span>';
    }
}
