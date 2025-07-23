<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewTab {

    public static function init() {
        $self = new self();
        add_shortcode( 'review_tab', array($self, 'review_tab_shortcode') );
    }

    public function review_tab_shortcode() {
        ob_start();

        $rating = isset($GLOBALS['s_average_rating']) ? $GLOBALS['s_average_rating'] : 0;
        $product_id = esc_attr(get_the_ID());

        $partner_id = get_field('partner_id', $product_id);

        $args = array(
            'post_type'   => 'review',
            'fields'      => 'ids',
            'meta_key'    => 'service_booked',
            'meta_value'  => $product_id,
            'numberposts' => -1,
        );

        $args2 = array(
            'post_type'   => 'review',
            'fields'      => 'ids',
            'meta_key'    => 'partner',
            'meta_value'  => $partner_id,
            'numberposts' => -1,
        );

        $posts = get_posts($args);
        $imploded_ids = !empty($posts) ? implode(',', $posts) : '';

        $posts_b = get_posts($args2);
        $imploded_ids_b = !empty($posts_b) ? implode(',', $posts_b) : '';

        $b_average_rating = 0;

        if (!empty($posts_b)) {
            $total_rating = $total_reliability = $total_skill = $total_professionalism = 0;
            $count = 0;

            foreach ($posts_b as $post) {
                $overall_rating = get_field('overall_rating', $post);
                $reliability = get_field('reliability', $post);
                $skill = get_field('skill', $post);
                $professionalism = get_field('professionalism', $post);

                if (!empty($overall_rating) || !empty($reliability) || !empty($skill) || !empty($professionalism)) {
                    $total_rating += $overall_rating;
                    $total_reliability += $reliability;
                    $total_skill += $skill;
                    $total_professionalism += $professionalism;
                    $count++;
                }
            }

            if ($count > 0) {
                $b_average_rating = $total_rating / $count;
            }
        }

        $tabs = '[vc_tta_tabs]';

        $tabs .= '[vc_tta_section title="Reviews for this Service" el_class="reviews_of_service"] ';
        $tabs .= do_shortcode('[us_grid post_type="ids" ids="' . esc_attr($imploded_ids) . '" columns="1" items_layout="28809" overriding_link="%7B%22url%22%3A%22%22%7D"]');
        $tabs .= ' [/vc_tta_section]';

        $tabs .= '[vc_tta_section title="Reviews for this Stylist" el_class="reviews_of_beautician"] ';
        $tabs .= do_shortcode('[wbcr_php_snippet id="30968"]');
        $tabs .= do_shortcode('[us_separator size="small" show_line="1"]');
        $tabs .= do_shortcode('[us_grid post_type="ids" ids="' . esc_attr($imploded_ids_b) . '" columns="2" items_layout="28803" overriding_link="%7B%22url%22%3A%22%22%7D"]');
        $tabs .= '[/vc_tta_section]';

        $tabs .= '[/vc_tta_tabs]';

        echo do_shortcode($tabs);
        ?>

        <script>
        jQuery(document).ready(function($) {
            let s_rating = <?php echo number_format((float)$GLOBALS['s_average_rating'],0); ?>;
            let b_rating = <?php echo number_format((float)$b_average_rating, 0); ?>;
            let sHtml = '<i class="fas fa-star"></i> ' + s_rating;
            let bHtml = '<i class="fas fa-star"></i> ' + b_rating;

            $('.reviews_of_service').each(function() {
                $(this).find('.w-tabs-item-title').append(' ' + sHtml);
            });

            $('.reviews_of_beautician').each(function() {
                $(this).find('.w-tabs-item-title').append(' ' + bHtml);
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }
}
