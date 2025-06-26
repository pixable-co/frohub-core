import React, { useState } from 'react';
import { Button, Modal, Progress, List } from 'antd';
import { fetchData } from '../services/fetchData.js';

const MigrateComments = () => {
    const [visible, setVisible] = useState(false);
    const [loading, setLoading] = useState(false);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [results, setResults] = useState([]);

    // Static array of test partner IDs
    const testPartnerIds = [29004, 6250]; // Replace with real partner IDs from your site

    const handleStartMigration = () => {
        setVisible(true);
        setLoading(true);
        setResults([]);
        migrateNext(0, []);
    };

    const migrateNext = (index, currentResults) => {
        if (index >= testPartnerIds.length) {
            setLoading(false);
            setResults(currentResults);
            Modal.success({
                title: 'Conversation Migration Complete',
                content: `${currentResults.filter(r => r.status === 'success').length} of ${testPartnerIds.length} conversations created.`,
            });
            return;
        }

        const partnerId = testPartnerIds[index];
        setCurrentIndex(index);

        fetchData(
            'frohub/create_conversation',
            (res) => {
                const newResults = [...currentResults];
                newResults.push({
                    partnerId,
                    status: res.success ? 'success' : 'error',
                    message: res?.data?.message || res?.data?.error || 'Unknown error',
                });
                setResults(newResults);
                migrateNext(index + 1, newResults);
            },
            {
                partner_id: partnerId,
                partner_client_post_id: partnerId,
                message: `Test conversation with partner ${partnerId}`
            }
        );
    };

    return (
        <>
            <Button type="primary" onClick={handleStartMigration}>
                Migrate All Comments to Conversations (Test)
            </Button>

            <Modal
                title="Migrating Conversations"
                open={visible}
                closable={!loading}
                footer={null}
                onCancel={() => !loading && setVisible(false)}
            >
                <Progress
                    percent={Math.round((currentIndex / testPartnerIds.length) * 100)}
                    status={loading ? 'active' : 'normal'}
                    style={{ marginBottom: 20 }}
                />

                <div style={{ maxHeight: '300px', overflowY: 'auto', marginBottom: 20 }}>
                    <List
                        size="small"
                        bordered
                        dataSource={results}
                        renderItem={(item) => (
                            <List.Item>
                                Partner #{item.partnerId}: <b>{item.status.toUpperCase()}</b> — {item.message}
                            </List.Item>
                        )}
                    />
                </div>

                {!loading && (
                    <Button type="default" block onClick={() => setVisible(false)}>
                        Close
                    </Button>
                )}
            </Modal>
        </>
    );
};

export default MigrateComments;
