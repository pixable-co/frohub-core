<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomFlushRewriteRules {

    public static function init() {
        $self = new self();
        register_activation_hook(__FILE__, array($self, 'flush_rewrite_rules_on_activation'));
    }

    public function flush_rewrite_rules_on_activation() {
        CustomEndpoints::add_my_account_endpoints();
        flush_rewrite_rules();
    }
}
