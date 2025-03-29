<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PrintOverallRating {

    public static function init() {
        $self = new self();
        add_shortcode( 'print_overall_rating', array($self, 'print_overall_rating_shortcode') );
    }

    public function print_overall_rating_shortcode() {
        ob_start();
        //need [get_product_rating] on the page
        if ( isset($GLOBALS['s_average_rating']) && $GLOBALS['s_average_rating'] ) {
            echo '<span><i class="fas fa-star"></i> ' . number_format((float) $GLOBALS['s_average_rating'], 2) . '</span>';
        }

        return ob_get_clean();
    }
}
