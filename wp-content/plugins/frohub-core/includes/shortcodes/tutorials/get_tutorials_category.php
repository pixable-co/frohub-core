<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetTutorialsCategory {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_tutorials_category', array($self, 'get_tutorials_category_shortcode') );
    }

    public function get_tutorials_category_shortcode() {
        $unique_key = 'get_tutorials_category' . uniqid();
        //[wbcr_php_snippet id="903"] on woody snippets
        $post_id = get_the_ID();
        $homepage_url = home_url();

        // Get the categories associated with the post
        $categories = get_the_category($post_id);

        if ($categories) {
            foreach ($categories as $category) {
                return '<a href="'.$homepage_url.'/black-hair-beauty-tutorials/?filter_category='. $category->slug .'" style="color:var(--color-content-text)">' . $category->name . '</span>';
            }
        } else {
            return '';
        }
        // return '<div class="get_tutorials_category" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
