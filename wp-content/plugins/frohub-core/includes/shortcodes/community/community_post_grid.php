<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CommunityPostGrid {

    public static function init() {
        $self = new self();
        add_shortcode('community_post_grid', array($self, 'community_post_grid_shortcode'));
    }

    public function community_post_grid_shortcode() {
        $args = array(
            'post_type'   => array('post'),
            'numberposts' => 9,
            'orderby'     => 'date',
            'order'       => 'ASC',
            'fields'      => 'ids' // Only get post IDs
        );

        $posts = get_posts($args);
        if (empty($posts)) {
            return ''; // Return nothing if no posts found
        }

        $imploded_ids = implode(',', $posts);

        $carousel = '[us_carousel orderby="rand" post_type="ids" ids="' . esc_attr($imploded_ids) . '" items_layout="28800" overriding_link="%7B%22url%22%3A%22%22%7D" items="3" arrows="1" items_quantity="9" responsive="%5B%7B%22breakpoint%22%3A%22mobiles%22%2C%22breakpoint_width%22%3A%221024px%22%2C%22items%22%3A%221%22%2C%22items_offset%22%3A%220px%22%2C%22center_item%22%3A%220%22%2C%22autoheight%22%3A%220%22%2C%22loop%22%3A%220%22%2C%22autoplay%22%3A%220%22%2C%22arrows%22%3A%220%22%2C%22dots%22%3A%220%22%7D%5D"]';

        return do_shortcode($carousel);
    }
}
