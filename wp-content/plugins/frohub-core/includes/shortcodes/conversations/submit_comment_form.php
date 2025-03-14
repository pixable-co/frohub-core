<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubmitCommentForm {

    public static function init() {
        $self = new self();
        add_shortcode( 'submit_comment', array($self, 'submit_comment_shortcode') );

        // Register AJAX handlers
        add_action( 'wp_ajax_submit_comment', array($self, 'handle_comment_submission') );
        add_action( 'wp_ajax_nopriv_submit_comment', array($self, 'handle_comment_submission') );
    }

    public function submit_comment_shortcode() {
        $unique_key = 'submit_comment' . uniqid();

        ob_start();
        ?>
        <!-- Message Input and File Upload -->
        <div class="submit_comment" data-key="<?php echo esc_attr($unique_key); ?>">
            <div class="chat-input">
                <input type="text" id="message" placeholder="Type a message..." />
                <input type="file" id="comment-image" accept="image/*" />
                <button id="send-button" data-post-id="<?php echo get_the_ID(); ?>">Send</button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function ($) {
            const sendButton = $('#send-button');
            const messageInput = $('#message');
            const imageInput = $('#comment-image');

            sendButton.on('click', function () {
                const message = messageInput.val().trim();
                const postId = sendButton.data('post-id');
                const file = imageInput[0].files[0]; 

                if (!message) {
                    alert("Please enter a message");
                    return;
                }

                // Prepare FormData
                const formData = new FormData();
                formData.append('action', 'submit_comment');
                formData.append('post_id', postId);
                formData.append('comment', message);
                
                if (file) {
                    formData.append('comment_image', file);
                }

                // AJAX Request
                $.ajax({
                    url: '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            alert('Comment submitted successfully!');
                            location.reload();  // Reload the page
                        } else {
                            alert("Error: " + response.data);
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error('Error:', textStatus, errorThrown);
                        alert("An error occurred. Please try again.");
                    }
                });
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }

    public function handle_comment_submission() {
        if (!isset($_POST['post_id']) || !isset($_POST['comment'])) {
            wp_send_json_error('Invalid request.');
        }

        $post_id = intval($_POST['post_id']);
        $comment_content = sanitize_text_field($_POST['comment']);
        $attachment_id = null;

        // Handle image upload
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
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
            }
        }

        // Prepare comment data
        $comment_data = array(
            'comment_post_ID'      => $post_id,
            'comment_content'      => $comment_content,
            'comment_author_IP'    => $_SERVER['REMOTE_ADDR'],
            'comment_agent'        => $_SERVER['HTTP_USER_AGENT'],
            'comment_approved'     => 1, 
        );

        // Insert comment
        $comment_id = wp_insert_comment($comment_data);

        if ($comment_id && $attachment_id) {
            add_comment_meta($comment_id, 'comment_image', $attachment_id);
        }

        wp_send_json_success('Comment submitted successfully!');
    }
}

