<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class SubmitComment
{

    public static function init()
    {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_submit_comment', array($self, 'submit_comment'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_submit_comment', array($self, 'submit_comment'));
    }

    /**
     * Handles submitting a comment with optional image and marking conversation unread for partner.
     */
    public function submit_comment()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in.');
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $comment_text = isset($_POST['comment']) ? sanitize_text_field($_POST['comment']) : '';

        if (!$post_id || empty($comment_text)) {
            wp_send_json_error('Post ID and comment are required.');
        }

        $current_user = wp_get_current_user();
        $user_id = get_current_user_id();
        $displayName = $current_user->display_name;
        $email = $current_user->user_email;

        // Handle image upload
        $image_url = '';
        if (!empty($_FILES['comment_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload($_FILES['comment_image'], array('test_form' => false));

            if (isset($upload['file'])) {
                $file_path = $upload['file'];
                $file_name = basename($file_path);
                $file_type = wp_check_filetype($file_name);

                $attachment = array(
                    'post_mime_type' => $file_type['type'],
                    'post_title'     => sanitize_file_name($file_name),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attachment_id = wp_insert_attachment($attachment, $file_path);
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
                $image_url = wp_get_attachment_url($attachment_id);
            }
        }

        // Append image to comment content
        if (!empty($image_url)) {
            $comment_text .= '<br><img class="comment-img" src="' . esc_url($image_url) . '" alt="Comment Image" >';
        }

        // Insert user comment
        $comment_data = array(
            'comment_post_ID'      => $post_id,
            'comment_content'      => $comment_text,
            'user_id'              => $user_id,
            'comment_author'       => $displayName,
            'comment_author_email' => $email,
        );

        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            wp_send_json_error('Failed to submit comment.');
        }

        update_comment_meta($comment_id, 'submitted_by_user_id', $user_id);

        $previous_unread_count = (int) get_post_meta($post_id, 'unread_count_partner', true);
        $new_unread_count = $previous_unread_count + 1;

        update_post_meta($post_id, 'read_by_partner', 0);
        update_post_meta($post_id, 'unread_count_partner', $new_unread_count);

        // 🔸 AUTO MESSAGE LOGIC
        $partner_id = get_field('partner', $post_id)->ID;
        $auto_message_enabled = get_field('auto_message', $partner_id);
        $auto_message_text = get_field('auto_message_text', $partner_id);

        if ($auto_message_enabled && !empty($auto_message_text)) {
            $datetime = new \DateTime('now', new \DateTimeZone('UTC'));
            $datetime->modify('+1 minute');
            $gmt_date = $datetime->format('Y-m-d H:i:s');

            $datetime->setTimezone(wp_timezone()); // Convert to WP local timezone
            $local_date = $datetime->format('Y-m-d H:i:s');

            $auto_comment_data = array(
                'comment_post_ID'      => $post_id,
                'comment_content'      => wp_kses_post($auto_message_text),
                'user_id'              => get_field('wp_user', $partner_id),
                'comment_author'       => get_the_title($partner_id),
                'comment_author_email' => get_field('partner_email', $partner_id),
                'comment_date'         => $local_date,
                'comment_date_gmt'     => $gmt_date,
            );

            wp_insert_comment($auto_comment_data);
        }

        // 🔹 API: mark unread for partner
        $partner_client_post_id = get_field('partner_client_post_id', $post_id);
        if ($partner_client_post_id) {
            $api_endpoint = FHCORE_PARTNER_BASE_API_URL . '/wp-json/frohub/v1/mark-unread-for-partner/';
            $response = wp_remote_post($api_endpoint, array(
                'method'  => 'POST',
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => json_encode(array('post_id' => $partner_client_post_id)),
                'timeout' => 45,
            ));
            if (is_wp_error($response)) {
                error_log('Failed to call API: ' . $response->get_error_message());
            } else {
                error_log('API Response: ' . wp_remote_retrieve_body($response));
            }
        } else {
            error_log('No partner_client_post_id found for post ID: ' . $post_id);
        }

        // 🔹 Send 2nd payload to partner webhook
        $partner_email = get_field('partner_email', $partner_id);
        $partner_name = get_the_title($partner_id);
        $user = get_field('customer', $post_id);
        $client_first_name = $user ? $user->first_name : '';

        $payload_partner = json_encode([
            'partner_email' => $partner_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
        ]);

        $partner_webhook = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e83523e791d77d7d52578d8a6bf2d8fe.2bd19f022b6f6c88bbf0fa6d7da05c4d&isdebug=false';

//         wp_remote_post($partner_webhook, [
//             'method'  => 'POST',
//             'headers' => ['Content-Type' => 'application/json'],
//             'body'    => $payload_partner,
//         ]);

        wp_send_json_success([
            'comment'     => $comment_text,
            'message'     => 'Comment submitted successfully!',
            'image_url'   => $image_url,
            'displayName' => $displayName,
            'email'       => $email,
        ]);
    }
}
