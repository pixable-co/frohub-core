<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomQnAContent {

    public static function init() {
        $self = new self();
        add_action('woocommerce_account_my-qna_endpoint', array($self, 'display_qna_content'));
    }

    public function display_qna_content() {
        echo do_shortcode('[us_page_block id="28829" remove_rows="1"]');
    }
}
