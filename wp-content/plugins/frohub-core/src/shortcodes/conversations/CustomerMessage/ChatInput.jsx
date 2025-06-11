import React, { useState, useRef } from 'react';
import {uploadImageDirect} from "../../../services/uploadImage.js";


const ChatInput = ({ onSendMessage, isLoading = false, disabled = false }) => {
    const [message, setMessage] = useState('');
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [isUploading, setIsUploading] = useState(false);
    const fileInputRef = useRef(null); // Reference for hidden input

    const handleSubmit = async () => {
        if ((!message.trim() && !imageFile) || isLoading || disabled) {
            return;
        }

        let imageUrl = '';

        if (imageFile) {
            setIsUploading(true);
            try {
                imageUrl = await uploadImageDirect(imageFile);
            } catch (error) {
                console.error('Image upload failed:', error);
                alert(error.message || 'Image upload failed');
            } finally {
                setIsUploading(false);
                setImageFile(null);
                setImagePreview(null);
            }
        }

        // Proceed with sending the message (even if image failed to upload)
        if (message.trim() || imageUrl) {
            await onSendMessage(message, imageUrl);
            setMessage('');
        }
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setImageFile(file);
            setImagePreview(URL.createObjectURL(file));
        }
    };

    const handleKeyPress = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit();
        }
    };

    const triggerFileInput = () => {
        if (fileInputRef.current) {
            fileInputRef.current.click();
        }
    };

    return (
        <div className="border-t bg-white p-4">
            <div className="flex items-center space-x-2">
                {/* Hidden File Input */}
                <input
                    type="file"
                    accept="image/*"
                    ref={fileInputRef}
                    style={{ display: 'none' }}
                    onChange={handleFileChange}
                />

                {/* Custom Upload Button */}
                <button
                    type="button"
                    onClick={triggerFileInput}
                    disabled={isUploading || disabled}
                    className="p-2 bg-gray-200 rounded hover:bg-gray-300"
                    title="Attach Image"
                >
                    📎
                </button>

                {/* Text Input */}
                <textarea
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    onKeyPress={handleKeyPress}
                    placeholder="Type your message here..."
                    className="flex-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm resize-none"
                    rows="2"
                    disabled={disabled || isUploading}
                />

                {/* Send Button */}
                <button
                    onClick={handleSubmit}
                    disabled={(!message.trim() && !imageFile) || isLoading || disabled || isUploading}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {isUploading ? 'Uploading...' : isLoading ? 'Sending...' : 'Send'}
                </button>
            </div>

            {/* Image Preview */}
            {imagePreview && (
                <div className="mt-2">
                    <img src={imagePreview} alt="Preview" className="max-h-32 rounded" />
                </div>
            )}
        </div>
    );
};

export default ChatInput;
