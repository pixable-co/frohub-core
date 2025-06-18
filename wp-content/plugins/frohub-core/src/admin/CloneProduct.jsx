import React, { useState } from 'react';
import { Button, Modal, message } from 'antd';
import {fetchData} from "../services/fetchData.js";

const CloneProduct = () => {
    const [loading, setLoading] = useState(false);

    const getPostIdFromUrl = () => {
        const params = new URLSearchParams(window.location.search);
        return params.get('post');
    };

    const handleClone = () => {
        const postId = getPostIdFromUrl();

        if (!postId) {
            message.error('Could not determine product ID from URL.');
            return;
        }

        Modal.confirm({
            title: 'Clone this product?',
            content: `Are you sure you want to clone product #${postId}?`,
            okText: 'Yes, Clone',
            cancelText: 'Cancel',
            onOk: () => {
                setLoading(true);

                fetchData('frohub/clone_ecom_product', (res) => {
                    if (res.success) {
                        message.success(res.data.message || 'Product cloned successfully.');
                    } else {
                        message.error(res.data?.message || 'Cloning failed.');
                    }
                    setLoading(false);
                }, {
                    product_id: postId,
                });
            },
        });
    };

    return (
        <Button type="primary" loading={loading} onClick={handleClone}>
            Clone this product
        </Button>
    );
};

export default CloneProduct;