<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetPostCategory {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_post_category', array($self, 'get_post_category_shortcode') );
    }

    public function get_post_category_shortcode() {
        $unique_key = 'get_post_category' . uniqid();
        //[wbcr_php_snippet id="959"] on woody snippets
        $post_id = get_the_ID();
        $homepage_url = home_url();

        // Get the categories associated with the post
        $categories = get_the_category($post_id);

        if ($categories) {
            foreach ($categories as $category) {
                return '<a href="'.$homepage_url.'/black-hair-beauty-blogs/?filter_category='. $category->slug .'" style="color:var(--color-content-text)">' . $category->name . '</span>';
            }
        } else {
            return '';
        }
        // return '<div class="get_post_category" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
