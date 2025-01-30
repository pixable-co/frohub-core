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
            'fields' => 'ids', // Fetch only the IDs **NEW CODE**
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
        $imploded_ids = implode(',', $product_ids); // We implode the array of IDs so that the they are separated by commas. {123,435,6457,534}. This is what the shortcode requires


        //echo do_shortcode('[us_grid post_type="product" items_layout="197" columns="4" overriding_link="%7B%22url%22%3A%22%22%7D"]'); // This is what the grid layout usually looks like


        echo do_shortcode('[us_grid post_type="ids" ids="'.$imploded_ids.'" items_layout="197" columns="4" overriding_link="%7B%22url%22%3A%22%22%7D"]'); //We will change the post type to "Ids" and insert our imploded array of ids. 
                
        // return '<div class="frohub_product_partner_page" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
