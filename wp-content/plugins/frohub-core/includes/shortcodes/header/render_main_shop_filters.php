<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderMainShopFilters {

    public static function init() {
        $self = new self();
        add_shortcode( 'render_main_shop_filters', array($self, 'render_main_shop_filters_shortcode') );
    }

    public function render_main_shop_filters_shortcode() {
        $unique_key = 'render_main_shop_filters' . uniqid();

        ob_start();
        ?>
        <div class="render_main_shop_filters" data-key="<?php echo esc_attr($unique_key); ?>">
            <div class="header-filters">
                <?php echo do_shortcode('[facetwp facet="autocomplete"]'); ?>
                <?php echo do_shortcode('[facetwp facet="service_type"]'); ?>
                <?php echo do_shortcode('[facetwp facet="date"]'); ?>
                <?php echo do_shortcode('[facetwp facet="location"]'); ?>
                <?php if (!is_page(1339)) : ?>
                    <?php echo do_shortcode('[facetwp template="hidden"]'); ?>
                <?php endif; ?>
                <button class="fwp-submit" data-href="/book-black-afro-hair-stylist-beauty-appointments/">
                    <i class="far fa-search"></i>
                </button>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
