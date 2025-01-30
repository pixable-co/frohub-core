<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubProductPartnerPage {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_product_partner_page', array($self, 'frohub_product_partner_page_shortcode') );
    }

    public function frohub_product_partner_page_shortcode() {
        $unique_key = 'frohub_product_partner_page' . uniqid();
        $current_partner_id = get_the_id(); 
        $args = array(
            'post_type' => 'product', 
            'fields' => 'ids',
            'meta_query' => array( 
                array(
                    'key' => 'partner_id', 
                    'value' => $current_partner_id, 
                    'compare' => '=' 
                )
            )
        );

        // Perform the query
        $query = new WP_Query($args);

        // Get the post IDs
        $product_ids = $query->posts;
        $imploded_ids = implode(',', $product_ids);

        return do_shortcode('[us_grid post_type="ids" ids="'.$imploded_ids.'" items_layout="197" columns="4" overriding_link="%7B%22url%22%3A%22%22%7D"]'); 
        
        // return '<div class="frohub_product_partner_page" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
