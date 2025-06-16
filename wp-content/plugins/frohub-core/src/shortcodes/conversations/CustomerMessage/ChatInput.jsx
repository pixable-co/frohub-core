import React, { useState, useRef } from 'react';
import { uploadImageDirect } from "../../../services/uploadImage.js";

const ChatInput = ({ onSendMessage, isLoading = false, disabled = false }) => {
    const [message, setMessage] = useState('');
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [isUploading, setIsUploading] = useState(false);
    const fileInputRef = useRef(null);

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
        <div className="border-t bg-white p-3">
            <div className="flex">
                <input
                    type="file"
                    accept="image/*"
                    ref={fileInputRef}
                    style={{ display: 'none' }}
                    onChange={handleFileChange}
                />

                <button
                    onClick={triggerFileInput}
                    type="button"
                    className="ml-2 px-3 py-2 text-gray-500 hover:text-gray-700"
                    disabled={disabled || isUploading}
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                </button>

                <input
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    onKeyPress={handleKeyPress}
                    placeholder="Type your message..."
                    className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm"
                    disabled={disabled || isUploading}
                />

                <button
                    onClick={handleSubmit}
                    disabled={(!message.trim() && !imageFile) || isLoading || disabled || isUploading}
                    className="ml-2 bg-gray-200 text-gray-700 px-3 py-1 rounded-md text-sm"
                >
                    Send Message
                </button>
            </div>

            {imagePreview && (
                <div className="mt-2 ml-10">
                    <div className="relative inline-block">
                        <img src={imagePreview} alt="Preview" className="max-h-32 rounded" />
                        <button
                            onClick={() => {
                                setImageFile(null);
                                setImagePreview(null);
                            }}
                            className="absolute top-1 right-1 bg-gray-800 bg-opacity-50 rounded-full p-1 text-white hover:bg-opacity-70"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}

            <div className="text-xs text-gray-400 mt-1 ml-10">
                Please be respectful: Keep your messages kind and considerate. Treat others as you would like to be treated. Report any abusive messages here.
            </div>

            {isUploading && (
                <div className="text-xs text-blue-500 mt-1 ml-10">
                    Uploading image...
                </div>
            )}
        </div>
    );
};

export default ChatInput;
