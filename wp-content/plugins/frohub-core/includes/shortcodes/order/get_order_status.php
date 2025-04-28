<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class GetOrderStatus
{

    public static function init()
    {
        $self = new self();
        add_shortcode('get_order_status', array($self, 'get_order_status_shortcode'));
    }

    public function get_order_status_shortcode()
    {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            $status = $order->get_status();            
            $status_labels = [
                'pending'       => 'Pending payment',
                'processing'    => 'Confirmed',
                'on-hold'       => 'Pending',
                'completed'     => 'Completed',
                'cancelled'     => 'Cancelled',
                'refunded'      => 'Refunded',
                'failed'        => 'Failed',
                'rescheduling'  => 'Rescheduling'
            ];
            
            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : 'Unknown Status';
            
// Override label if status is 'cancelled' and cancellation_status field is meaningful
if ($status === 'cancelled') {
    $field_obj = get_field_object('cancellation_status', $order_id);
    $value = isset($field_obj['value']) ? $field_obj['value'] : '';
    $label = isset($field_obj['choices'][$value]) ? $field_obj['choices'][$value] : '';

    if (!empty($value) && $value !== 'N/A' && !empty($label)) {
        $status_label = esc_html($label);
    }
}
        
            echo '<span class="status_text">' . esc_html($status_label) . '</span>';
        
        }
?>

        

<?php
        return ob_get_clean();
    }
}
