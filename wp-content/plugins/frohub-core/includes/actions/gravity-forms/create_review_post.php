<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class CreateReviewPost
{
    public static function init()
    {
        $self = new self();
        add_action('gform_after_submission_7', array($self, 'create_review_post'), 10, 2);
    }

    public function create_review_post($entry, $form)
    {
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
            update_field('user', get_current_user_id(), $post_id);
            update_field('overall_rating', rgar($entry, '7'), $post_id);
            update_field('reliability', rgar($entry, '14'), $post_id);
            update_field('skill', rgar($entry, '15'), $post_id);
            update_field('professionalism', rgar($entry, '16'), $post_id);
            update_field('service_booked', $product_id, $post_id);
            update_field('partner', $partner_id, $post_id);
            update_field('order', $order_id, $post_id);

            update_field('review', $post_id, $order_id); // Link review to order

            $uploaded_files = json_decode(rgar($entry, '21'), true);
            $attachment_ids = [];

            if ($uploaded_files && is_array($uploaded_files)) {
                foreach ($uploaded_files as $file_url) {
                    $upload_dir = wp_upload_dir();
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

                    if (file_exists($file_path)) {
                        $file_type = wp_check_filetype(basename($file_path), null);
                        $attachment = [
                            'post_mime_type' => $file_type['type'],
                            'post_title'     => sanitize_file_name(basename($file_path)),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ];

                        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                        $attachment_ids[] = $attach_id;
                    }
                }
            }

            if (!empty($attachment_ids)) {
                update_field('review_gallery', $attachment_ids, $post_id);
            }

            // ✅ Send review to booking post on partner side. /wp-admin/post.php?post=31668&action=edit
            send_review_to_webhook($post_id);

            // ✅ Send review to partner via email.
            $partner_email = get_field('partner_email',  $partner_id);
            $partner_name = get_the_title($partner_id);
            $order = wc_get_order($order_id);
            $customer_first_name = $order ? $order->get_billing_first_name() : '';

            $payload = array(
                'partner_email'     => $partner_email,
                'client_first_name' => $customer_first_name,
                'partner_name'      => $partner_name 
            );

            $response = wp_remote_post('https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e83523e791d77d7d52578d8a6bf2d8fe.2bd19f022b6f6c88bbf0fa6d7da05c4d&isdebug=false', array(
                'method'      => 'POST',
                'headers'     => array('Content-Type' => 'application/json'),
                'body'        => wp_json_encode($payload),
                'data_format' => 'body',
            ));
        }
    }
}
