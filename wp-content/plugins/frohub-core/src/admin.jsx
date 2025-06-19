import React from 'react';
import ReactDOM from 'react-dom'; // For React 17
import CloneProduct from './admin/CloneProduct.jsx';

document.addEventListener('DOMContentLoaded', () => {
    // ✅ Correct ID selector usage (no # for getElementById)
    const targetEl = document.getElementById('frohub-admin');

    if (targetEl) {
        const container = document.createElement('span');
        container.id = 'frohub-clone-product-button';
        container.style.marginLeft = '10px';

        targetEl.appendChild(container); // or insertAdjacentElement if needed

        ReactDOM.render(<CloneProduct />, container);
    }
});
