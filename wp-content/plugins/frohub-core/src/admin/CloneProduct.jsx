import React, { useState } from 'react';
import { Button, Modal, Progress, List, message } from 'antd';
import { fetchData } from '../services/fetchData.js';

const CloneProduct = () => {
    const [loading, setLoading] = useState(false);
    const [cloningModalVisible, setCloningModalVisible] = useState(false);
    const [results, setResults] = useState([]);
    const [currentIndex, setCurrentIndex] = useState(0);

    const productIds = Array.isArray(frohub_settings?.product_ids) ? frohub_settings.product_ids : [];

    const handleBatchClone = () => {
        if (!productIds.length) {
            Modal.error({ title: 'No product IDs available.' });
            return;
        }

        setLoading(true);
        setCloningModalVisible(true);
        setResults([]);
        cloneNextProduct(0, []);
    };

    const cloneNextProduct = (index, currentResults) => {
        if (index >= productIds.length) {
            setLoading(false);
            setResults(currentResults);

            // ✅ Show confirmation after all cloning is done
            Modal.success({
                title: 'Cloning Complete',
                content: `${currentResults.filter(r => r.status === 'success').length} of ${productIds.length} products cloned successfully.`,
            });

            return;
        }

        const productId = productIds[index];
        setCurrentIndex(index);

        fetchData(
            'frohub/clone_ecom_product',
            (res) => {
                const newResults = [...currentResults];
                newResults.push({
                    productId,
                    status: res.success ? 'success' : 'error',
                    message: res?.data?.message || res?.data?.error || 'Unknown error',
                });

                setResults(newResults);
                cloneNextProduct(index + 1, newResults);
            },
            {
                product_id: productId,
            }
        );
    };

    return (
        <>
            <div>
                <h4>Clone Product to Partner Portal</h4>
                <Button type="primary" loading={loading} onClick={handleBatchClone}>
                    Clone all products
                </Button>
            </div>

            <Modal
                title="Cloning Products"
                open={cloningModalVisible}
                closable={!loading}
                footer={null}
                onCancel={() => !loading && setCloningModalVisible(false)}
            >
                <Progress
                    percent={Math.round((currentIndex / productIds.length) * 100)}
                    status={loading ? 'active' : 'normal'}
                    style={{ marginBottom: 20 }}
                />

                {/* ✅ Scrollable List Container */}
                <div style={{ maxHeight: '300px', overflowY: 'auto', marginBottom: 20 }}>
                    <List
                        size="small"
                        bordered
                        dataSource={results}
                        renderItem={(item) => (
                            <List.Item>
                                Product #{item.productId}: <b>{item.status.toUpperCase()}</b> — {item.message}
                            </List.Item>
                        )}
                    />
                </div>

                {!loading && (
                    <Button type="default" block onClick={() => setCloningModalVisible(false)}>
                        Close
                    </Button>
                )}
            </Modal>
        </>
    );
};

export default CloneProduct;
