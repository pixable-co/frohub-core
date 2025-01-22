<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FhSubmitForm {

    public static function init() {
        $self = new self();
        add_shortcode( 'fh_submit_form', array($self, 'fh_submit_form_shortcode') );
    }

    public function fh_submit_form_shortcode() {
        $unique_key = 'fh_submit_form' . uniqid();
        return '<div class="fh_submit_form" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
