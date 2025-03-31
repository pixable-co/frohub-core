<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomMenuItems {

    public static function init() {
        $self = new self();
        add_filter('woocommerce_account_menu_items', array($self, 'modify_my_account_menu_items'));
    }

    public function modify_my_account_menu_items($items) {
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);

        $custom_items = [
            'messages' => __('Messages', 'your-textdomain'),
            'favourites' => __('Favourites', 'your-textdomain'),
            'my-qna' => __('My QnA', 'your-textdomain'),
        ];

        $items = array_merge($items, $custom_items);
        $items['customer-logout'] = $logout;

        return $items;
    }
}
