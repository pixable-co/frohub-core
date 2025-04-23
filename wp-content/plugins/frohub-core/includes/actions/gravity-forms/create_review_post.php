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
            update_field('user',get_current_user_id(), $post_id);
            update_field('overall_rating', rgar($entry, '7'), $post_id);
            update_field('reliability', rgar($entry, '14'), $post_id);
            update_field('skill', rgar($entry, '15'), $post_id);
            update_field('professionalism', rgar($entry, '16'), $post_id);
            update_field('service_booked', $product_id, $post_id);
            update_field('partner', $partner_id, $post_id);
            update_field('order', $order_id, $post_id);

            // Update order with review post
            update_field('review', $post_id, $order_id);

            $uploaded_files = json_decode(rgar($entry, '21'), true);
            $attachment_ids = [];

            if ($uploaded_files && is_array($uploaded_files)) {
                foreach ($uploaded_files as $file_url) {
                    $upload_dir = wp_upload_dir();
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

                    // Check if file exists
                    if (file_exists($file_path)) {
                        // Prepare file for attachment
                        $file_type = wp_check_filetype(basename($file_path), null);
                        $attachment = [
                            'post_mime_type' => $file_type['type'],
                            'post_title'     => sanitize_file_name(basename($file_path)),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ];

                        // Insert attachment
                        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

                        // Generate attachment metadata
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                        $attachment_ids[] = $attach_id;
                    }
                }
            }

            // Update ACF gallery field
            if (!empty($attachment_ids)) {
                update_field('review_gallery', $attachment_ids, $post_id);
            }
        }
    }
}
