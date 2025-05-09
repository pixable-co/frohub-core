<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderBeauticianDetails {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_beautician_details', array($self, 'get_order_beautician_details_shortcode') );
    }

    public function get_order_beautician_details_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        $conversation = get_field('conversation', $order_id);
        $conversation_id = $conversation->ID;
        $conversation_url = get_permalink($conversation_id);

        if ($order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if ($product_id != 28990) {
                    $partner_id = get_field('partner_id', $product_id);
                    $partner_title = get_the_title($partner_id);
                    
                    $phone = get_field('phone', $partner_id);

                    if ($partner_id) {
                        echo '<span>' . esc_html($partner_title) . '</span>   ';
                        if ($conversation_url) {
                            echo '<a href="'. esc_attr($conversation_url) . '"><i class="fas fa-envelope"></i></a>';
                        }
                        echo '<br>';
                        if ($phone) {
                            echo '<i class="fas fa-phone-alt"></i>   <a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>';
                        }
                    }
                }
            }
        }

        return ob_get_clean();
    }
}
