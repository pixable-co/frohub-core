<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomFavouritesContent {

    public static function init() {
        $self = new self();
        add_action('woocommerce_account_favourites_endpoint', array($self, 'display_favourites_content'));
    }

    public function display_favourites_content() {
        echo do_shortcode('[us_page_block id="4910" remove_rows="1"]');
    }
}
