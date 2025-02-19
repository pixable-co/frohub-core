<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RequestBookButton {

    public static function init() {
        $self = new self();
        add_shortcode( 'request_book_button', array($self, 'request_book_button_shortcode') );
    }

    public function request_book_button_shortcode() {
        $unique_key = 'request_book_button' . uniqid();
        return '<div class="request_book_button" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
