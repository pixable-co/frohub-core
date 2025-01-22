import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import RenderProductAddOns from './RenderProductAddOns';

const renderProductAddOnsElements = document.querySelectorAll('.render_product_add_ons');
renderProductAddOnsElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <RenderProductAddOns dataKey={key} />
    );
});