import React, { useState } from 'react';
import { Button, Modal, List, Progress } from 'antd';
import jsonData from './comments.json'; // adjust path if needed
import { fetchData } from '../services/fetchData';

const ImportComments = () => {
    const [visible, setVisible] = useState(false);
    const [progress, setProgress] = useState(0);
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [currentTask, setCurrentTask] = useState('');

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

    const createOrUpdate = async (commentGroups, index, allResults) => {
        if (index >= commentGroups.length) {
            setLoading(false);
            Modal.success({ title: 'Import Complete' });
            setResults(allResults);
            return;
        }

        const group = commentGroups[index];
        const first = group[0];
        const postId = first.comment_post_ID;

        setCurrentTask(`Processing Conversation for Post ID: ${postId}`);

        const seller = group.find(c => c.user_role === 'seller');
        const customer = group.find(c => c.user_role === 'customer');

        if (!seller || !customer) {
            allResults.push({ postId, status: 'error', message: 'Missing seller or customer in group' });
            setProgress(Math.round((index + 1) / commentGroups.length * 100));
            return createOrUpdate(commentGroups, index + 1, allResults);
        }

        const partnerId = await resolveUserId(seller.comment_author_email);
        const customerId = await resolveUserId(customer.comment_author_email);

        if (!partnerId || !customerId) {
            allResults.push({ postId, status: 'error', message: 'User(s) not found for partner/customer' });
            setProgress(Math.round((index + 1) / commentGroups.length * 100));
            return createOrUpdate(commentGroups, index + 1, allResults);
        }

        fetchData('frohub/create_or_update_conversation', async (res) => {
            if (!res.success) {
                allResults.push({ postId, status: 'error', message: res.data.message });
                setProgress(Math.round((index + 1) / commentGroups.length * 100));
                return createOrUpdate(commentGroups, index + 1, allResults);
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

                await new Promise(resolve => {
                    fetchData('frohub/import_conversation_comment', () => resolve(), commentData);
                });
            }

            allResults.push({ postId, status: 'success', message: 'Conversation imported successfully' });
            setProgress(Math.round((index + 1) / commentGroups.length * 100));
            return createOrUpdate(commentGroups, index + 1, allResults);
        }, {
            partner_id: partnerId,
            customer_id: customerId,
            external_ref_id: postId
        });
    };

    const handleImport = () => {
        const grouped = Object.values(groupByPost());
        setVisible(true);
        setLoading(true);
        setProgress(0);
        setResults([]);
        setCurrentTask('Starting import...');
        createOrUpdate(grouped, 0, []);
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
