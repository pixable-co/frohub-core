<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomOrderStatus {

    public static function init() {
        $self = new self();

        add_action('init', array($self, 'register_custom_post_statuses'), 10);
        add_filter('wc_order_statuses', array($self, 'custom_wc_order_statuses'));
        add_filter('bulk_actions-edit-shop_order', array($self, 'custom_dropdown_bulk_actions_shop_order'), 20, 1);
    }

    public function register_custom_post_statuses() {
        // NEW Statuses
        register_post_status('wc-expired', array(
            'label'                     => _x('Expired', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'woocommerce')
        ));

        register_post_status('wc-rescheduling', array(
            'label'                     => _x('Rescheduling', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Rescheduling <span class="count">(%s)</span>', 'Rescheduling <span class="count">(%s)</span>', 'woocommerce')
        ));
    }

    public function custom_wc_order_statuses($order_statuses) {
        $order_statuses['wc-expired'] = _x('Expired', 'Order status', 'woocommerce');
        $order_statuses['wc-rescheduling'] = _x('Rescheduling', 'Order status', 'woocommerce');
        return $order_statuses;
    }

    public function custom_dropdown_bulk_actions_shop_order($actions) {
        $actions['mark_expired'] = __('Mark Expired', 'woocommerce');
        $actions['mark_rescheduling'] = __('Mark Rescheduling', 'woocommerce');
        return $actions;
    }
}
