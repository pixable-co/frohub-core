<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HideJetpackStatColumn {

    public static function init() {
        $self = new self();
        add_action('admin_head', array($self, 'hide_jetpack_statistics_column'));
    }

    public function hide_jetpack_statistics_column() {
        echo '<style>
            .column-stats {
                display: none !important;
            }
        </style>';
    }
}
