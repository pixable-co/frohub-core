<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderServiceName {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_service_name', array($self, 'get_order_service_name_shortcode') );
    }

    public function get_order_service_name_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if ($product_id != 28990) {
                    $product_link = get_permalink($product_id);
                    $full_name = $item->get_name();
            
                    // Get everything before the first " - "
                    $name_parts = explode(' - ', $full_name);
                    $clean_name = trim($name_parts[0]);
            
                    echo '<a href="' . esc_url($product_link) . '">' . esc_html($clean_name) . '</a>';
                }
            }
            
        }

        return ob_get_clean();
    }
}
