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
                <button id="send-button" data-post-id="<?php echo get_the_ID(); ?>" data-name>Send</button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function ($) {
            const sendButton = $('#send-button');
            const messageInput = $('#message');
            const messagesContainer = $('.messages');

            sendButton.on('click', function () {
                const message = messageInput.val().trim();

                if (!message) {
                    alert("Please enter a message");
                    return;
                }

                // Prepare form data
                const formData = new FormData();
                const postId = sendButton.data('post-id');
                const comment = message;

                // Call the AJAX
                $.ajax({
                    url: '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'submit_comment',
                        post_id: postId,
                        comment: comment
                    },
                    success: function (data) {
                        if (data.success) {
                            alert('Comment submitted successfully!');
                            location.reload();  // Reload the page
                        } else {
                            alert("Error: " + data);
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
