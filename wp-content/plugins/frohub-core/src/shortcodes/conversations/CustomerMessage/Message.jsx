import React, { useEffect, useState } from 'react';
import Avatar from './Avatar';
import { fetchData } from '../../../services/fetchData';

const Message = ({ comment, conversationId, isLastCustomerMessage, customerImage, partnerImage }) => {
    const sentFrom = comment?.meta_data?.sent_from?.[0] || '';
    const isCustomerMessage = sentFrom !== 'partner';

    const [hasMarkedRead, setHasMarkedRead] = useState(false);

    useEffect(() => {
        if (!hasMarkedRead && isLastCustomerMessage) {
            fetchData(
                'frohub/read_by_customer',
                (res) => {
                    if (res.success) {
                        setHasMarkedRead(true);
                        document.cookie = "unreadConversations=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                    } else {
                        console.warn('Failed to mark as read by customer:', res.message);
                    }
                },
                { conversation_post_id: conversationId } // make sure this matches your PHP handler
            );
        }
    }, [isLastCustomerMessage, conversationId, hasMarkedRead]);

    const formatTimestamp = (date) =>
        new Date(comment.date).toLocaleString('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });

    const createMarkup = (htmlContent) => ({ __html: htmlContent });
    const isPending = comment.status === 'pending';
    const isFailed = comment.status === 'failed';

    const bubbleClass = isCustomerMessage ? 'customer-message' : 'partner-message';

    return (
        <div className={`flex mb-4 ${isCustomerMessage ? 'justify-end' : 'justify-start'}`}>
            {!isCustomerMessage && <Avatar name={comment.author || 'Partner'} size="sm" image={partnerImage} />}
            <div className={`flex flex-col ${isCustomerMessage ? 'items-end' : 'items-start'} max-w-xs`}>
                <div className={`p-3 rounded-lg break-words shadow-sm ${bubbleClass}`}>
                    <div className="text-xs mb-1">
                        {formatTimestamp(comment.date)}
                        {isPending && <span className="ml-1">(Sending...)</span>}
                        {isFailed && <span className="ml-1">(Failed)</span>}
                    </div>
                    <div dangerouslySetInnerHTML={createMarkup(comment.content)} className="text-sm" />
                    {comment.image_url && (
                        <div className="mt-2">
                            <img src={comment.image_url} alt="Attached" className="max-w-full rounded" />
                        </div>
                    )}
                </div>
            </div>
            {isCustomerMessage && <Avatar name="You" size="sm" image={customerImage} />}
        </div>
    );
};

export default Message;
