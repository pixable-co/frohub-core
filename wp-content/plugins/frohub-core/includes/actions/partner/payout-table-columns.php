<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayoutTableColumns {

    public static function init() {
        $self = new self();

        // Custom admin columns
        add_filter('manage_payout_posts_columns', array($self, 'modify_payout_columns'));
        add_action('manage_payout_posts_custom_column', array($self, 'display_payout_custom_columns'), 10, 2);
    }

    public function modify_payout_columns($columns) {
        $new_columns = [];

        // Remove unwanted default columns
        unset($columns['date']);
        unset($columns['statistics']);

        // Loop through existing columns and insert custom fields after 'title'
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['order'] = __('Order', 'textdomain');
                $new_columns['partner_name'] = __('Partner', 'textdomain');
                $new_columns['appointment_date_time'] = __('Appointment', 'textdomain');
                $new_columns['deposit'] = __('Deposit (£)', 'textdomain');
                $new_columns['commission'] = __('Commission (£)', 'textdomain');
                $new_columns['payout_amount'] = __('Payout(£)', 'textdomain');
                $new_columns['scheduled_date'] = __('Scheduled Date', 'textdomain');
                $new_columns['payout_date'] = __('Payout Date', 'textdomain');
                $new_columns['payout_status'] = __('Status', 'textdomain');
            }
        }

        return $new_columns;
    }

    public function display_payout_custom_columns($column, $post_id) {
        if ($column === 'partner_name' || $column === 'order') {
            $post_object = get_field($column, $post_id); // Get Post Object
            if ($post_object) {
                echo '<a href="' . get_edit_post_link($post_object->ID) . '">' . esc_html(get_the_title($post_object->ID)) . '</a>';
            }
        } elseif ($column === 'appointment_date_time') {
            $date_time = get_field($column, $post_id);
            if ($date_time) {
                echo date('g:i a, j F Y', strtotime($date_time));
            }
        } elseif ($column === 'scheduled_date' || $column === 'payout_date') {
            $date = get_field($column, $post_id);
            if ($date) {
                echo date('j F, Y', strtotime($date));
            }
        } else {
            // Generic handler for other text-based ACF fields
            $value = get_field($column, $post_id);
            if ($value) {
                echo esc_html($value);
            }
        }
    }
}
