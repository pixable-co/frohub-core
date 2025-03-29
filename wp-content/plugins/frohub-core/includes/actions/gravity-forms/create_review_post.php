<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateReviewPost {

    public static function init() {
        $self = new self();
        add_action('gform_after_submission_5', array($self, 'create_review_post'), 10, 2);
    }

    public function create_review_post($entry, $form) {
        $post_data = array(
            'post_title'   => 'Review for Order ID ' . rgar($entry, '18'),
            'post_content' => rgar($entry, '20'),
            'post_status'  => 'publish',
            'post_type'    => 'review',
        );

        $post_id = wp_insert_post($post_data);

        $order_id   = rgar($entry, '18');
        $product_id = rgar($entry, '19');
        $partner_id = get_field('partner_id', $product_id);

        if ($post_id) {
            update_field('overall_rating', rgar($entry, '7'), $post_id);
            update_field('reliability', rgar($entry, '14'), $post_id);
            update_field('skill', rgar($entry, '15'), $post_id);
            update_field('professionalism', rgar($entry, '16'), $post_id);
            update_field('service_booked', $product_id, $post_id);
            update_field('partner', $partner_id, $post_id);
            update_field('order', $order_id, $post_id);

            // Update order with review post
            update_field('review', $post_id, $order_id);
        }
    }
}
