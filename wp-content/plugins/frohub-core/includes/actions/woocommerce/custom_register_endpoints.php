<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomRegisterEndpoints {

    public static function init() {
        $self = new self();
        add_action('init', array($self, 'add_my_account_endpoints'));
    }

    public function add_my_account_endpoints() {
        add_rewrite_endpoint('messages', EP_PAGES);
        add_rewrite_endpoint('favourites', EP_PAGES);
        add_rewrite_endpoint('my-qna', EP_PAGES);
    }
}
