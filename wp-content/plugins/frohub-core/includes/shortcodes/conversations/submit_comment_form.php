<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubmitCommentForm {

    public static function init() {
        $self = new self();
        add_shortcode( 'submit_comment', array($self, 'submit_comment_shortcode') );
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
}

