<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TryRahat {

    public static function init() {
        $self = new self();
        add_shortcode( 'try_rahat', array($self, 'try_rahat_shortcode') );
    }

    public function try_rahat_shortcode() {
        $unique_key = 'try_rahat' . uniqid();
        return '<div class="try_rahat" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
