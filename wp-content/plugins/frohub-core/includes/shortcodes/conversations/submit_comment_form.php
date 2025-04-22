<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class SubmitCommentForm
{

    public static function init()
    {
        $self = new self();
        add_shortcode('submit_comment', array($self, 'submit_comment_shortcode'));
    }

    public function submit_comment_shortcode()
    {
        $post_id = isset($_GET['c_id']) ? absint($_GET['c_id']) : 0; // Needed to retrieve the ecommerce conversation post ID
        ob_start();
?>

        <!-- Message Input and File Upload -->
        <div class="chat-input-wrapper-custom">
            <div class="chat-input-box">
                <label for="image-upload" class="camera-icon">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="image-upload" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp" style="display: none;" />
                <input type="text" id="message" placeholder="Type a message..." />
            </div>
            <button id="send-button" class="send-message-button" data-post-id="<?php echo $post_id; ?>">
                Send Message
            </button>
        </div>


        <script>
            jQuery(document).ready(function($) {
                const sendButton = $('#send-button');
                const messageInput = $('#message');
                const imageInput = $('#image-upload');

                sendButton.on('click', function() {
                    const message = messageInput.val().trim();
                    const file = imageInput[0].files[0];
                    const postId = sendButton.data('post-id');

                    if (!message && !file) {
                        alert("Please enter a message or select an image to upload.");
                        return;
                    }

                    // Validate file (if exists)
                    if (file) {
                        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
                        const maxSize = 5 * 1024 * 1024; // 5MB in bytes

                        if (!allowedTypes.includes(file.type)) {
                            alert("Invalid file type. Please select a JPG, PNG, GIF, or WebP image.");
                            return;
                        }

                        if (file.size > maxSize) {
                            alert("File size exceeds 5MB. Please select a smaller image.");
                            return;
                        }
                    }

                    // Prepare form data
                    const formData = new FormData();
                    formData.append('action', 'submit_comment');
                    formData.append('post_id', postId);
                    formData.append('comment', message);

                    if (file) {
                        formData.append('image', file);
                    }

                    // Call the AJAX handler
                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(data) {
                            if (data.success) {
                                alert('Comment submitted successfully!');
                                location.reload(); // Reload the page
                            } else {
                                alert("Error: " + data.data);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
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
