<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomMessagesContent {

    public static function init() {
        $self = new self();
        add_action('woocommerce_account_messages_endpoint', array($self, 'display_messages_content'));
    }

    public function display_messages_content() {
        echo do_shortcode('[us_page_block id="28827" remove_rows="1"]');
    }
}
