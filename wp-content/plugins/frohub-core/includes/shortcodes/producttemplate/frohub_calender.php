<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubCalender {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_calender', array($self, 'frohub_calender_shortcode') );
    }

    public function frohub_calender_shortcode() {
        $unique_key = 'frohub_calender' . uniqid();
        return '<div class="frohub_calender" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
