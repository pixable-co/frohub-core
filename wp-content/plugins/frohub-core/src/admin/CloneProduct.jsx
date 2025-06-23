import React, { useState } from 'react';
import { Button, Modal, Progress, List } from 'antd';
import { fetchData } from '../services/fetchData.js';

const CloneProduct = () => {
    const [loading, setLoading] = useState(false);
    const [cloningModalVisible, setCloningModalVisible] = useState(false);
    const [results, setResults] = useState([]);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [mode, setMode] = useState('products'); // 'products' or 'orders'

    const productIds = Array.isArray(frohub_settings?.product_ids) ? frohub_settings.product_ids : [];
    const orderIds = Array.isArray(frohub_settings?.order_ids) ? frohub_settings.order_ids : [];

    const handleBatchClone = (type) => {
        const ids = type === 'products' ? productIds : orderIds;

        if (!ids.length) {
            Modal.error({ title: `No ${type} IDs available.` });
            return;
        }

        setMode(type);
        setLoading(true);
        setCloningModalVisible(true);
        setResults([]);
        cloneNext(type, 0, []);
    };

    const cloneNext = (type, index, currentResults) => {
        const ids = type === 'products' ? productIds : orderIds;

        if (index >= ids.length) {
            setLoading(false);
            setResults(currentResults);

            Modal.success({
                title: `Cloning ${type} Complete`,
                content: `${currentResults.filter(r => r.status === 'success').length} of ${ids.length} ${type} cloned successfully.`,
            });
            return;
        }

        const id = ids[index];
        setCurrentIndex(index);

        const endpoint = type === 'products' ? 'frohub/clone_ecom_product' : 'frohub/clone_ecom_order';
        const payloadKey = type === 'products' ? 'product_id' : 'order_id';

        fetchData(
            endpoint,
            (res) => {
                const newResults = [...currentResults];

                newResults.push({
                    productId: id,
                    status: res.success ? 'success' : 'error',
                    message: res?.data?.message || res?.data?.error || 'Unknown error',
                });

                setResults(newResults);
                cloneNext(type, index + 1, newResults);
            },
            { [payloadKey]: id }
        );
    };

    return (
        <>
            <div style={{ marginBottom: 20 }}>
                <h4>Clone Product to Partner Portal</h4>
                <Button type="primary" loading={loading} onClick={() => handleBatchClone('products')}>
                    Clone all Products
                </Button>
            </div>

            <div>
                <h4>Clone Orders to Partner Portal</h4>
                <Button type="primary" loading={loading} onClick={() => handleBatchClone('orders')}>
                    Clone All Orders
                </Button>
            </div>

            <Modal
                title={`Cloning ${mode === 'products' ? 'Products' : 'Orders'}`}
                open={cloningModalVisible}
                closable={!loading}
                footer={null}
                onCancel={() => !loading && setCloningModalVisible(false)}
            >
                <Progress
                    percent={Math.round((currentIndex / (mode === 'products' ? productIds.length : orderIds.length)) * 100)}
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
                                {mode === 'products' ? 'Product' : 'Order'} #{item.productId}: <b>{item.status.toUpperCase()}</b> — {item.message}
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
