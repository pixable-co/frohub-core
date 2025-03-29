<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetProductRating {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_product_rating', array($self, 'get_product_rating_shortcode') );
    }

    public function get_product_rating_shortcode() {
        ob_start();

        $product_id = get_the_ID();
        $args = array(
            'post_type'      => 'review',
            'numberposts'    => -1,
            'meta_key'       => 'service_booked',
            'meta_value'     => $product_id,
        );

        $posts = get_posts($args);

        if (!empty($posts)) {
            $total_rating = 0;
            $total_reliability = 0;
            $total_skill = 0;
            $total_professionalism = 0;
            $count = 0;

            foreach ($posts as $post) {
                $overall_rating = get_field('overall_rating', $post->ID);
                $reliability = get_field('reliability', $post->ID);
                $skill = get_field('skill', $post->ID);
                $professionalism = get_field('professionalism', $post->ID);

                if (!empty($overall_rating) || !empty($reliability) || !empty($skill) || !empty($professionalism)) {
                    $total_rating += $overall_rating;
                    $total_reliability += $reliability;
                    $total_skill += $skill;
                    $total_professionalism += $professionalism;
                    $count++;
                }
            }

            if ($count > 0) {
                $GLOBALS['s_average_rating'] = $total_rating / $count;
                $GLOBALS['s_average_reliability'] = $total_reliability / $count;
                $GLOBALS['s_average_skill'] = $total_skill / $count;
                $GLOBALS['s_average_professionalism'] = $total_professionalism / $count;
            }
        }

        return ob_get_clean();
    }
}
