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
        <div class="submit-comment-form">
            <p class="deposit-warning"><i class="fas fa-exclamation-triangle"></i> Only deposits paid through FroHub are protected. <a href="/help-centre/">Learn more</a></p>
            <div class="chat-input-wrapper-custom">
            <div class="chat-input-box">
                <label for="image-upload" class="camera-icon" id="camera-icon-label" style="cursor: pointer;">
            <i class="fas fa-camera"></i>
            </label>
            <input type="file" id="image-upload" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp" style="display: none;" />
            <img id="image-preview" src="" alt="Image Preview" style="display: none; width:75px; height:75px; object-fit:cover;cursor:pointer; margin-right:1rem;" />


                    <input type="text" id="message" placeholder="Type a message..." />
                </div>
                <button id="send-button" class="w-btn us-btn-style_1" data-post-id="<?php echo $post_id; ?>">
                    <span class="btn-label">Send Message</span>
                    <span class="spinner" style="display: none; margin-left: 8px;">
                    </span>
                </button>

            </div>
            <p class="chat-respectful-message">Please be respectful: Keep your messages kind and considerate. Treat others as you would like to be treated.</p>
        </div>



        <script>
            jQuery(document).ready(function($) {
                const sendButton = $('#send-button');
                const messageInput = $('#message');
                const imageInput = $('#image-upload');

imageInput.on('change', function () {
    const file = this.files[0];
    const preview = $('#image-preview');
    const cameraIcon = $('#camera-icon-label');

    if (file) {
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            alert("Invalid file type. Please select a JPG, PNG, GIF, or WebP image.");
            this.value = '';
            preview.hide().attr('src', '');
            cameraIcon.show();
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.attr('src', e.target.result).show();
            cameraIcon.hide(); // Hide the camera icon when image is uploaded
        };
        reader.readAsDataURL(file);
    } else {
        preview.hide().attr('src', '');
        cameraIcon.show(); // Show the icon again if input cleared
    }
});

// Clicking the image opens file selector again
$('#image-preview').on('click', function () {
    $('#image-upload').click();
});

                sendButton.on('click', function() {
                    const message = messageInput.val().trim();
                    const file = imageInput[0].files[0];
                    const postId = sendButton.data('post-id');
                    const spinner = sendButton.find('.spinner');
                    const label = sendButton.find('.btn-label');

                    if (!message && !file) {
                        alert("Please enter a message or select an image to upload.");
                        return;
                    }

                    // Validate file
                    if (file) {
                        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
                        const maxSize = 5 * 1024 * 1024;

                        if (!allowedTypes.includes(file.type)) {
                            alert("Invalid file type. Please select a JPG, PNG, GIF, or WebP image.");
                            return;
                        }

                        if (file.size > maxSize) {
                            alert("File size exceeds 5MB. Please select a smaller image.");
                            return;
                        }
                    }

                    // Show spinner and disable button
                    spinner.show();
                    label.text("Sending...");
                    sendButton.prop('disabled', true);

                    const formData = new FormData();
                    formData.append('action', 'submit_comment');
                    formData.append('post_id', postId);
                    formData.append('comment', message);
                    if (file) {
                        formData.append('comment_image', file); // 🔧 match PHP: 'comment_image'
                    }

                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(data) {
                            if (data.success) {
                                alert('Comment submitted successfully!');
                                location.reload();
                            } else {
                                alert("Error: " + data.data);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('Error:', textStatus, errorThrown);
                            alert("An error occurred. Please try again.");
                        },
                        complete: function() {
                            // Hide spinner and re-enable button
                            spinner.hide();
                            label.text("Send Message");
                            sendButton.prop('disabled', false);
                        }
                    });
                });
            });



        </script>

<?php
        return ob_get_clean();
    }
}
