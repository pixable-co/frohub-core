// Changes to ContactItem.jsx to match the screenshot design
import React from 'react';
import Avatar from './Avatar';

const ContactItem = ({ conversation, isActive, onClick, isLoading = false }) => {
    const handleClick = (event) => {
        if (!isLoading) {
            const hiddenInput = event.currentTarget.querySelector('input[type="hidden"]');
            const conversationId = hiddenInput ? hiddenInput.value : null;
            onClick(conversation, conversationId);
        }
    };

    return (
        <div
            className={`contact-list-avatar p-4 border-b border-gray-200 cursor-pointer ${
                isActive ? 'bg-blue-50' : 'hover:bg-gray-50'
            } ${isLoading ? 'opacity-50' : ''}`}
            onClick={handleClick}
        >
            <div className="flex items-center space-x-2">
                <Avatar name={conversation.partner_name || 'P'} />
                <div className="text-gray-700">
                    {conversation.partner_name || `Partner #${conversation.partner_id}`}
                </div>
                <input type="hidden" value={conversation.conversation_id} />
                <input type="hidden" value={conversation.partner_id} />
                {!conversation.read_by_partner && (
                    <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                )}
            </div>
        </div>
    );
};

export default ContactItem;
