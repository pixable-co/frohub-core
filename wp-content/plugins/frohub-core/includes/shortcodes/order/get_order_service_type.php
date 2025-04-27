<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderServiceType {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_service_type', array($self, 'get_order_service_type_shortcode') );
    }

    public function get_order_service_type_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            foreach ($order->get_items() as $item) {
                $item_meta_data = $item->get_meta_data();
                if (!empty($item_meta_data)) {
                    foreach ($item_meta_data as $meta) {
                        if ($meta->key === 'pa_service-type') {
                            // Capitalize first letter only
                            $capitalized_value = ucwords(str_replace('-', ' ', strtolower($meta->value)));
                            echo esc_html($capitalized_value);
                        }
                    }
                }
            }
        }

        return ob_get_clean();
    }
}
?>
