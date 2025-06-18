import React from 'react';
import ReactDOM from 'react-dom'; // For React 17
import CloneProduct from './admin/CloneProduct.jsx';

document.addEventListener('DOMContentLoaded', () => {
    // ✅ Check both post type and action=edit in URL
    const isProduct = frohub_settings?.post_type === 'product';
    const isEditAction = new URLSearchParams(window.location.search).get('action') === 'edit';

    if (!isProduct || !isEditAction) {
        return; // ❌ Do nothing if not editing a product
    }

    const addNewProductBtn = document.querySelector('.page-title-action');

    if (addNewProductBtn) {
        const container = document.createElement('span');
        container.id = 'frohub-clone-product-button';
        container.style.marginLeft = '10px';

        addNewProductBtn.insertAdjacentElement('afterend', container);
        ReactDOM.render(<CloneProduct />, container);
    }
});
