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
        $post_id = isset($_GET['c_id']) ? absint($_GET['c_id']) : 0;
        ob_start();
?>

        <!-- Message Input and File Upload -->
        <div class="submit-comment-form">
            <p class="deposit-warning"><i class="fas fa-exclamation-triangle"></i> Only deposits paid through FroHub are protected. <a href="/help-centre/" target="_blank">Learn more</a></p>
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
                    <span class="spinner" style="display: none; margin-left: 8px;"></span>
                </button>
            </div>
            <p class="chat-respectful-message">Please be respectful: Keep your messages kind and considerate. Treat others as you would like to be treated.</p>
        </div>

        <!-- Modal Structure -->
        <div id="comment-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <p id="modal-message"></p>
                <button id="modal-close">x</button>
            </div>
        </div>

        <style>
            #comment-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
            }

            .modal-overlay {
                background: rgba(0, 0, 0, 0.6);
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }

            .modal-content {
                background: white;
                padding: 20px;
                max-width: 600px;
                margin: auto;
                border-radius: 0.3rem;
                z-index: 10000;
                position: absolute;
                text-align: center;
                top: 0;
                bottom: 0;
                height: max-content;
                left: 0;
                right: 0;
            }

            #modal-close {
                    position: absolute;
                top: 0.5rem;
                right: 0.7rem;
                font-size: 1.2rem;
                line-height: 1;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {

                function showModal(message) {
                    $('#modal-message').text(message);
                    $('#comment-modal').fadeIn();
                }

                $('#modal-close, .modal-overlay').on('click', function() {
                    $('#comment-modal').fadeOut();
                });

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
                            showModal("Invalid file type. Please select a JPG, PNG, GIF, or WebP image.");
                            this.value = '';
                            preview.hide().attr('src', '');
                            cameraIcon.show();
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function (e) {
                            preview.attr('src', e.target.result).show();
                            cameraIcon.hide();
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.hide().attr('src', '');
                        cameraIcon.show();
                    }
                });

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
                        showModal("Please enter a message or select an image to upload.");
                        return;
                    }

                    if (file) {
                        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
                        const maxSize = 5 * 1024 * 1024;

                        if (!allowedTypes.includes(file.type)) {
                            showModal("Invalid file type. Please select a JPG, PNG, GIF, or WebP image.");
                            return;
                        }

                        if (file.size > maxSize) {
                            showModal("File size exceeds 5MB. Please select a smaller image.");
                            return;
                        }
                    }

                    spinner.show();
                    label.text("Sending...");
                    sendButton.prop('disabled', true);

                    const formData = new FormData();
                    formData.append('action', 'submit_comment');
                    formData.append('post_id', postId);
                    formData.append('comment', message);
                    if (file) {
                        formData.append('comment_image', file);
                    }

                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(data) {
                            if (data.success) {
                                showModal('Comment submitted successfully! Refreshing the page...');
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            } else {
                                showModal("Error: " + data.data);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('Error:', textStatus, errorThrown);
                            showModal("An error occurred. Please try again.");
                        },
                        complete: function() {
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
