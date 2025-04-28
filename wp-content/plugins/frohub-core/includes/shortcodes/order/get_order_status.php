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
            
            $status_descriptions = [
                'pending'           => 'Your booking request has been received by the stylist but not yet confirmed.',
                'processing'        => 'The booking has been confirmed by the stylist.',
                'on-hold'           => 'Your booking request has been received by the stylist but not yet confirmed.', // Same as pending
                'completed'         => '', // You can define text if needed
                'cancelled'         => '', // Will be determined dynamically
                'refunded'          => '', // You can define text if needed
                'failed'            => '', // You can define text if needed
                'rescheduling'      => 'The stylist suggested a new date.'
            ];

            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : 'Unknown Status';
            $status_description = isset($status_descriptions[$status]) ? $status_descriptions[$status] : '';

            // Override label and description if status is 'cancelled' and cancellation_status is meaningful
            if ($status === 'cancelled') {
                $field_obj = get_field_object('cancellation_status', $order_id);
                $value = isset($field_obj['value']) ? $field_obj['value'] : '';
                $label = isset($field_obj['choices'][$value]) ? $field_obj['choices'][$value] : '';

                if (!empty($value) && $value !== 'N/A' && !empty($label)) {
                    $status_label = esc_html($label);

                    // Set specific description based on cancellation_status value
                    switch ($value) {
                        case 'cancelled_by_client_early':
                            $status_description = 'You cancelled within the allowed time and will receive a deposit refund.';
                            break;
                        case 'cancelled_by_client_late':
                            $status_description = 'You cancelled too late to receive a deposit refund.';
                            break;
                        case 'cancelled_by_stylist':
                            $status_description = 'The stylist cancelled the appointment. You will be refunded your deposit and booking fee.';
                            break;
                        case 'declined_by_stylist':
                            $status_description = 'The stylist declined your booking request. No charge was made.';
                            break;
                        case 'declined_by_client':
                            $status_description = 'You declined the rescheduled date. No charge was made.';
                            break;
                        case 'booking_expired':
                            $status_description = 'The stylist didn’t confirm your request in time, and it has expired. No charge was made.';
                            break;
                        default:
                            $status_description = '';
                    }
                }
            }

            echo '<div class="status_block">';
            echo '<span class="status_text">' . esc_html($status_label) . '</span>';
            if (!empty($status_description)) {
                echo '<span class="status_description">' . esc_html($status_description) . '</span>';
            }
            echo '</div>';
        }

        return ob_get_clean();
    }
}
