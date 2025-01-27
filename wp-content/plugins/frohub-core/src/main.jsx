import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import AddToCartProduct from './products/AddToCartProduct';

// Find the element with the class 'frohub_add_to_cart'
const element = document.querySelector('.frohub_add_to_cart');

if (element) {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <AddToCartProduct dataKey={key} />
    );
}
