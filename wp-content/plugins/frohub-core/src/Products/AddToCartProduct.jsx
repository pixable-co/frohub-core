import { useEffect, useState } from 'react';
import ProceedToPaymentButton from './ProceedToPaymentButton';
import RenderServiceTypes from './RenderServiceTypes';
import RenderProductAddOns from './RenderProductAddOns';

export default function AddToCartProduct() {
    const [productId, setProductId] = useState(null);
    const [selectedAddOns, setSelectedAddOns] = useState([]);
    const [productPrice, setProductPrice] = useState(0);
    const [selectedServiceType, setSelectedServiceType] = useState('');

    const handleServiceTypeChange = (event) => {
        setSelectedServiceType(event.target.value);
        console.log('Selected Service Type:', event.target.value); // Log the selected service type
    };

    return (
        <div>
            <RenderProductAddOns
                productId={productId}
                setProductId={setProductId}
                selectedAddOns={selectedAddOns}
                setSelectedAddOns={setSelectedAddOns}
                setProductPrice={setProductPrice}
            />
            <RenderServiceTypes productId={productId} onServiceTypeChange={handleServiceTypeChange} />
            <ProceedToPaymentButton
                selectedAddOns={selectedAddOns}
                productPrice={productPrice}
                productId={productId}
                selectedServiceType={selectedServiceType}
            />
        </div>
    );
}
