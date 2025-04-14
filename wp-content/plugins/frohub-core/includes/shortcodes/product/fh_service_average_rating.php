<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FhServiceAverageRating {

    public static function init() {
        $self = new self();
        add_shortcode( 'fh_service_average_rating', array($self, 'fh_service_average_rating_shortcode') );
    }

    public function fh_service_average_rating_shortcode() {
        $product_id = get_the_ID();
    
        if (!$product_id) {
            return '';
        }
    
        // Get all reviews for this product
        $args = array(
            'post_type'   => 'review',
            'meta_key'    => 'service_booked',
            'meta_value'  => $product_id,
            'numberposts' => -1,
            'fields'      => 'ids',
        );
    
        $reviews = get_posts($args);
        $total_rating = 0;
        $count = 0;
    
        if (!empty($reviews)) {
            foreach ($reviews as $review_id) {
                $rating = get_field('overall_rating', $review_id);
                if (!empty($rating)) {
                    $total_rating += floatval($rating);
                    $count++;
                }
            }
        }
    
        if ($count === 0) {
            return '';
        }
    
        $average_rating = number_format($total_rating / $count, 1);
    
        return '<div class="fh_service_average_rating"><i class="fas fa-star"></i> ' . esc_html($average_rating) . '</div>';
    }
    
}
