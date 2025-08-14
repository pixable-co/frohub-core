import React, { useState } from 'react';
import { Button, Modal, List, Progress } from 'antd';
import jsonData from './comments_new.json'; // adjust path if needed
import { fetchData } from '../services/fetchData';

const ImportComments = () => {
    const [visible, setVisible] = useState(false);
    const [progress, setProgress] = useState(0);
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [currentTask, setCurrentTask] = useState('');

    // Batch size - adjust this value based on your server capacity
    const BATCH_SIZE = 5;

    const groupByPost = () => {
        const grouped = {};
        jsonData.comments.forEach(comment => {
            const postId = comment.comment_post_ID;
            if (!grouped[postId]) grouped[postId] = [];
            grouped[postId].push(comment);
        });
        return grouped;
    };

    const resolveUserId = async (email) => {
        return new Promise((resolve) => {
            fetchData('frohub/resolve_user_id_by_email', (res) => {
                if (res.success) resolve(res.data.user_id);
                else resolve(null);
            }, { email });
        });
    };

    const processConversation = async (group) => {
        const first = group[0];
        const postId = first.comment_post_ID;

        const seller = group.find(c => c.user_role === 'seller');
        const customer = group.find(c => c.user_role === 'customer');

        if (!seller || !customer) {
            return { postId, status: 'error', message: 'Missing seller or customer in group' };
        }

        const partnerId = await resolveUserId(seller.comment_author_email);
        const customerId = await resolveUserId(customer.comment_author_email);

        if (!partnerId || !customerId) {
            return { postId, status: 'error', message: 'User(s) not found for partner/customer' };
        }

        return new Promise((resolve) => {
            fetchData('frohub/create_or_update_conversation', async (res) => {
                if (!res.success) {
                    resolve({ postId, status: 'error', message: res.data.message });
                    return;
                }

                const conversationId = res.data.conversation_id;

                // Insert all comments
                for (const item of group) {
                    const senderType = item.user_role === 'seller' ? 'partner' : 'customer';
                    const commentData = {
                        post_id: conversationId,
                        partner_id: partnerId,
                        comment: item.comment_content,
                        comment_date: item.comment_date,
                        sent_from: senderType,
                        author_email: item.comment_author_email,
                    };

                    await new Promise(commentResolve => {
                        fetchData('frohub/import_conversation_comment', () => commentResolve(), commentData);
                    });
                }

                resolve({ postId, status: 'success', message: 'Conversation imported successfully' });
            }, {
                partner_id: partnerId,
                customer_id: customerId,
                external_ref_id: postId
            });
        });
    };

    const processBatch = async (batch, batchNumber, totalBatches) => {
        setCurrentTask(`Processing batch ${batchNumber}/${totalBatches} (${batch.length} conversations)`);

        // Process all conversations in the batch in parallel
        const batchPromises = batch.map(group => processConversation(group));
        const batchResults = await Promise.all(batchPromises);

        return batchResults;
    };

    const handleImport = async () => {
        const grouped = Object.values(groupByPost());
        const totalConversations = grouped.length;
        const totalBatches = Math.ceil(totalConversations / BATCH_SIZE);

        setVisible(true);
        setLoading(true);
        setProgress(0);
        setResults([]);
        setCurrentTask('Starting import...');

        const allResults = [];
        let processedCount = 0;

        try {
            // Process conversations in batches
            for (let i = 0; i < totalBatches; i++) {
                const startIndex = i * BATCH_SIZE;
                const endIndex = Math.min(startIndex + BATCH_SIZE, totalConversations);
                const batch = grouped.slice(startIndex, endIndex);

                const batchResults = await processBatch(batch, i + 1, totalBatches);
                allResults.push(...batchResults);

                processedCount += batch.length;
                setProgress(Math.round((processedCount / totalConversations) * 100));
                setResults([...allResults]); // Update results incrementally

                // Small delay between batches to prevent overwhelming the server
                if (i < totalBatches - 1) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
            }

            setLoading(false);
            setCurrentTask(`Import complete! Processed ${totalConversations} conversations.`);
            Modal.success({ title: 'Import Complete' });

        } catch (error) {
            setLoading(false);
            setCurrentTask('Import failed due to an error.');
            Modal.error({
                title: 'Import Failed',
                content: 'An error occurred during the import process.'
            });
        }
    };

    return (
        <>
            <Button type="primary" onClick={handleImport}>
                Import Comments from JSON
            </Button>

            <Modal open={visible} footer={null} closable={!loading} onCancel={() => !loading && setVisible(false)}>
                <h3>Import Progress</h3>
                <p><b>Task:</b> {currentTask}</p>
                <Progress percent={progress} status={loading ? 'active' : 'normal'} />
                <List
                    dataSource={results}
                    renderItem={(item) => (
                        <List.Item>
                            Post #{item.postId}: <b>{item.status.toUpperCase()}</b> — {item.message}
                        </List.Item>
                    )}
                    style={{ maxHeight: 300, overflowY: 'auto', marginTop: 10 }}
                />
            </Modal>
        </>
    );
};

export default ImportComments;