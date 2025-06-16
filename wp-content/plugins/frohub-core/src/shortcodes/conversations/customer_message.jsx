import React, { useState, useEffect, useRef } from 'react';
import { fetchData } from '../../services/fetchData';
import ContactItem from "./CustomerMessage/ContactItem.jsx";
import ChatInput from './CustomerMessage/ChatInput';
import Message from './CustomerMessage/Message';
import Avatar from './CustomerMessage/Avatar';

const CustomerMessage = ({ dataKey, currentUserPartnerPostId }) => {
    const [conversations, setConversations] = useState([]);
    const [comments, setComments] = useState([]);
    const [activeConversation, setActiveConversation] = useState(null);
    const [activeConversationId, setActiveConversationId] = useState(null);
    const [userPartnerId, setUserPartnerId] = useState(currentUserPartnerPostId);
    const [loading, setLoading] = useState({ conversations: false, comments: false, sending: false });
    const [error, setError] = useState(null);

    const conversationIntervalRef = useRef(null);
    const lastCommentTimestampRef = useRef(null);
    const [autoReplySent, setAutoReplySent] = useState(false);
    const autoReplyMessage = ""; // Optional: put default reply here

    const urlParams = typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search)
        : null;

    const urlCustomerId = urlParams?.get('customer_id');
    const urlConversationId = urlParams?.get('conversation_id');

    useEffect(() => {
        loadConversations();
    }, []);

    useEffect(() => {
        if (activeConversation) {
            loadComments(activeConversation.client_id, true);
            startConversationPolling();
        } else {
            stopConversationPolling();
        }

        return () => stopConversationPolling();
    }, [activeConversation]);

    useEffect(() => {
        const chatContainer = document.getElementById('chat-messages-container');
        if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
    }, [comments]);

    const startConversationPolling = () => {
        stopConversationPolling();
        conversationIntervalRef.current = setInterval(() => {
            if (activeConversation) {
                loadComments(activeConversation.client_id, false);
            }
        }, 5000);
    };

    const stopConversationPolling = () => {
        if (conversationIntervalRef.current) {
            clearInterval(conversationIntervalRef.current);
            conversationIntervalRef.current = null;
        }
    };

    const loadConversations = () => {
        setLoading(prev => ({ ...prev, conversations: true }));
        setError(null);

        fetchData('frohub/user_conversations', (response) => {
            if (response.success) {
                const data = response.data?.data || [];

                const normalizedConversations = data.map(conv => ({
                    ...conv,
                    client_id: conv.conversation_id,
                    comments: Array.isArray(conv.comments) ? conv.comments : []
                }));

                setConversations(normalizedConversations);

                if (normalizedConversations.length > 0) {
                    let selected = null;

                    if (urlConversationId) {
                        selected = normalizedConversations.find(
                            c => String(c.conversation_id) === String(urlConversationId)
                        );
                    } else if (urlCustomerId) {
                        selected = normalizedConversations.find(
                            c => String(c.customer_id) === String(urlCustomerId)
                        );
                    }

                    if (!selected && !activeConversation) {
                        selected = normalizedConversations[0];
                    }

                    if (selected) {
                        setActiveConversation(selected);
                        setActiveConversationId(selected.conversation_id);
                    }
                }
            } else {
                setError('Failed to load conversations: ' + (response.message || 'Unknown error'));
                setConversations([]);
            }

            setLoading(prev => ({ ...prev, conversations: false }));
        });
    };

    const loadComments = (conversationPostId, showLoading = true) => {
        if (showLoading) setLoading(prev => ({ ...prev, comments: true }));
        setError(null);

        fetchData('frohub/get_conversation_comments', (response) => {
            if (response.success) {
                const data = response.data || {};
                const commentsData = data.comments || [];
                const partnerIdFromResponse = data.user_partner_id;

                if (Array.isArray(commentsData)) {
                    if (showLoading || comments.length === 0) {
                        setComments(commentsData);
                        if (commentsData.length > 0) {
                            const latestComment = commentsData[commentsData.length - 1];
                            lastCommentTimestampRef.current = new Date(latestComment.date).getTime();
                        }
                    } else {
                        const lastTimestamp = lastCommentTimestampRef.current;
                        const newComments = commentsData.filter(comment => {
                            const commentTimestamp = new Date(comment.date).getTime();
                            return !lastTimestamp || commentTimestamp > lastTimestamp;
                        });

                        if (newComments.length > 0) {
                            const latestNew = newComments[newComments.length - 1];
                            lastCommentTimestampRef.current = new Date(latestNew.date).getTime();

                            setComments(prevComments => {
                                const cleanedComments = prevComments.filter(c =>
                                    !c.comment_id.toString().startsWith('temp_') || c.status === 'failed'
                                );
                                const existingIds = new Set(cleanedComments.map(c => c.comment_id));
                                const uniqueNewComments = newComments.filter(c => !existingIds.has(c.comment_id));
                                return [...cleanedComments, ...uniqueNewComments];
                            });

                            const newIncoming = newComments.find(c =>
                                c?.meta_data?.sent_from?.[0] !== 'partner'
                            );

                            if (newIncoming && autoReplyMessage && !autoReplySent) {
                                setAutoReplySent(true);
                                handleSendMessage(autoReplyMessage);
                            }
                        }
                    }
                }

                if (partnerIdFromResponse) setUserPartnerId(partnerIdFromResponse);
                setConversations(prev => prev.map(conv =>
                    conv.client_id === conversationPostId
                        ? { ...conv, read_by_partner: true }
                        : conv
                ));
            } else {
                if (showLoading) {
                    setError('Failed to load comments: ' + (response.data?.message || 'Unknown error'));
                    setComments([]);
                }
            }

            if (showLoading) setLoading(prev => ({ ...prev, comments: false }));
        }, { post_id: conversationPostId });
    };

    const handleConversationSelect = (conversation, conversationId) => {
        setActiveConversation(conversation);
        setActiveConversationId(conversationId);
        setComments([]);
        setAutoReplySent(false);
        lastCommentTimestampRef.current = null;
    };

    const handleSendMessage = async (content, imageUrl = '') => {
        if (!activeConversation || (!content.trim() && !imageUrl)) return;

        const tempMessage = {
            comment_id: `temp_${Date.now()}`,
            content,
            author: 'You',
            partner_id: currentUserPartnerPostId,
            date: new Date().toISOString(),
            image_url: imageUrl,
            status: 'pending'
        };

        setComments(prev => [...prev, tempMessage]);
        setLoading(prev => ({ ...prev, sending: true }));
        setError(null);

        const postId = activeConversationId || activeConversation.client_id;

        fetchData('frohub/send_customer_message', (response) => {
            if (response.success) {
                setComments(prev => prev.filter(comment => comment.comment_id !== tempMessage.comment_id));
                loadComments(activeConversation.client_id, false);
                setConversations(prev => prev.map(conv =>
                    conv.client_id === activeConversation.client_id
                        ? { ...conv, last_message: content, last_activity: new Date().toISOString(), read_by_partner: true }
                        : conv
                ));
            } else {
                setComments(prev => prev.map(comment =>
                    comment.comment_id === tempMessage.comment_id
                        ? { ...comment, status: 'failed' }
                        : comment
                ));
                setError('Failed to send message: ' + (response.message || 'Unknown error'));
            }
            setLoading(prev => ({ ...prev, sending: false }));
        }, {
            post_id: postId,
            conversation_id: activeConversationId,
            partner_id: currentUserPartnerPostId,
            comment: content,
            image_url: imageUrl
        });
    };

    return (
        <div className="flex h-screen bg-white">
            <div className="w-64 border-r border-gray-200">
                <div className="p-4 border-b border-gray-200">
                    <h2 className="text-lg font-semibold">Messages</h2>
                </div>
                <div className="overflow-y-auto">
                    {loading.conversations ? (
                        <div className="p-4 text-gray-500">Loading conversations...</div>
                    ) : (
                        conversations.map(conversation => (
                            <ContactItem
                                key={conversation.client_id}
                                conversation={conversation}
                                isActive={activeConversation?.client_id === conversation.client_id}
                                onClick={handleConversationSelect}
                            />
                        ))
                    )}
                    {!loading.conversations && conversations.length === 0 && (
                        <div className="p-4 text-center text-gray-500">No conversations found</div>
                    )}
                </div>
            </div>

            <div className="flex-1 flex flex-col">
                {activeConversation ? (
                    <>
                        <div className="p-4 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="text-sm text-gray-500">New Booking Requested</div>
                                    <div className="font-medium">23rd June 2024 at 15:00</div>
                                    <div className="text-sm text-gray-500">Service Name</div>
                                </div>
                                <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                    <span className="text-gray-500">+</span>
                                </div>
                            </div>
                        </div>

                        <div id="chat-messages-container" className="flex-1 overflow-y-auto p-4 bg-gray-50">
                            {loading.comments ? (
                                <div className="flex justify-center items-center h-full text-gray-500">Loading messages...</div>
                            ) : (
                                <>
                                    {comments.map(comment => (
                                        <Message key={comment.comment_id} comment={comment} />
                                    ))}
                                    {comments.length === 0 && (
                                        <div className="text-center text-gray-500 mt-8">No messages yet. Start a conversation!</div>
                                    )}
                                </>
                            )}
                        </div>

                        <ChatInput onSendMessage={handleSendMessage} isLoading={loading.sending} disabled={loading.comments} />
                    </>
                ) : (
                    <div className="flex-1 flex items-center justify-center text-gray-500">
                        <div className="text-center">
                            <h3 className="text-lg font-medium mb-2">Select a conversation</h3>
                            <p>Choose a conversation from the sidebar to start messaging</p>
                        </div>
                    </div>
                )}
            </div>

            {error && (
                <div className="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50 max-w-md">
                    <div className="flex items-start">
                        <div className="flex-1">
                            <strong className="font-medium">Error:</strong>
                            <div className="mt-1 text-sm">{error}</div>
                        </div>
                        <button onClick={() => setError(null)} className="ml-2 text-red-700 hover:text-red-900">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default CustomerMessage;
