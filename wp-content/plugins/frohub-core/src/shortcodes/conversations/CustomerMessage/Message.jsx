import React from 'react';
import Avatar from './Avatar';

const Message = ({ comment }) => {
    const sentFrom = comment?.meta_data?.sent_from?.[0] || '';
    const isCustomerMessage = sentFrom !== 'partner';

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
            {!isCustomerMessage && <Avatar name={comment.author || 'Partner'} size="sm" />}
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
            {isCustomerMessage && <Avatar name="You" size="sm" />}
        </div>
    );
};

export default Message;