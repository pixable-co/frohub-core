<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ManagePartnerPostColumn {

    public static function init() {
        $self = new self();

        add_filter('manage_partner_posts_columns', array($self, 'modify_partner_columns'));
        add_action('manage_partner_posts_custom_column', array($self, 'display_partner_custom_columns'), 10, 2);
        add_action('admin_head', array($self, 'custom_admin_css'));
    }

    public function modify_partner_columns($columns) {
        $new_columns = [];

        // Add the thumbnail column first (without a header)
        $new_columns['partner_thumbnail'] = '';

        // Loop through existing columns and insert custom fields after 'title'
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['partner_email'] = __('Partner Email', 'textdomain');
                $new_columns['stripe_account_id'] = __('Stripe Account ID', 'textdomain');
                $new_columns['zoho_account_id'] = __('Zoho Account ID', 'textdomain');
            }
        }

        return $new_columns;
    }

    public function display_partner_custom_columns($column, $post_id) {
        if ($column === 'partner_thumbnail') {
            $thumbnail = get_the_post_thumbnail($post_id, [40, 40]); // 40x40px thumbnail
            echo $thumbnail ?: '';
        } elseif ($column === 'partner_email') {
            $email = get_field('partner_email', $post_id);
            if ($email) {
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            }
        } elseif ($column === 'stripe_account_id') {
            echo esc_html(get_field('stripe_account_id', $post_id));
        } elseif ($column === 'zoho_account_id') {
            echo esc_html(get_field('zoho_account_id', $post_id));
        }
    }

    public function custom_admin_css() {
        echo '<style>
            .column-partner_thumbnail {
                width: 50px !important;
                text-align: center;
            }
            .column-partner_thumbnail img {
                display: block;
                margin: auto;
            }
        </style>';
    }
}
