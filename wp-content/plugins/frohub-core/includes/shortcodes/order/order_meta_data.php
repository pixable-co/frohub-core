<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderMetaData {

    public static function init() {
        $self = new self();
        add_shortcode( 'order_meta_data', array($self, 'order_meta_data_shortcode') );
    }

    public function order_meta_data_shortcode() {
        ob_start();

        $order_id = 3843; // Replace with your dynamic order ID if needed
        $order = wc_get_order($order_id);

        if ($order) {
            $selected_date = '';
            $selected_time = '';

            foreach ($order->get_items() as $item) {
                $selected_date = $item->get_meta('Selected Date');
                $selected_time = $item->get_meta('Selected Time');

                if ($selected_date && $selected_time) {
                    break;
                }
            }

            if ($selected_date && $selected_time) {
                list($start_time, $end_time) = explode(' - ', $selected_time);

                $appointment_start = $selected_date . ' ' . $start_time;
                $appointment_end = $selected_date . ' ' . $end_time;

                update_field('appointment_start', $appointment_start, $order_id);
                update_field('appointment_end', $appointment_end, $order_id);

                echo '<p>ACF fields updated successfully!</p>';
            } else {
                echo '<p>Selected Date or Time not found in order.</p>';
            }
        } else {
            echo '<p>Order not found.</p>';
        }

        return ob_get_clean();
    }
}
