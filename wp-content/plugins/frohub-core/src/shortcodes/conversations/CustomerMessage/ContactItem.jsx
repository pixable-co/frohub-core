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

    const lastComment = conversation.comments?.[conversation.comments.length - 1]?.content || '';
    const partnerName = conversation.partner_name || `Partner #${conversation.partner_id}`;

    return (
        <div
            className={`flex items-center p-3 cursor-pointer transition-colors ${
                isActive ? 'bg-blue-50 border-r-2 border-blue-500' : 'hover:bg-gray-50'
            } ${isLoading ? 'opacity-50' : ''}`}
            onClick={handleClick}
        >
            <Avatar name={partnerName} />
            <div className="ml-3 flex-1">
                <div className="flex items-center justify-between">
                    <h3 className="font-medium text-gray-900">
                        {partnerName}
                    </h3>
                    <input type="hidden" value={conversation.conversation_id} />
                    <input type="hidden" value={conversation.partner_id} />
                    {!conversation.read_by_partner && (
                        <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                    )}
                </div>
                <p className="text-sm text-gray-500">{conversation.last_activity || 'No recent activity'}</p>
                <p className="text-sm text-gray-400 truncate">
                    {lastComment || 'No messages yet'}
                </p>
            </div>
        </div>
    );
};

export default ContactItem;
