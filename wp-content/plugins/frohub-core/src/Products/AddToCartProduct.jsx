import { useEffect, useState } from 'react';
import frohubStore from "../frohubStore.js";
import ProceedToPaymentButton from './ProceedToPaymentButton';
import RenderServiceTypes from './RenderServiceTypes';
import RenderProductAddOns from './RenderProductAddOns';

export default function AddToCartProduct() {
    // ✅ Keep using local state
    const [partnerId, setPartnerId] = useState(null);
    const [productId, setProductId] = useState(null);
    const [selectedAddOns, setSelectedAddOns] = useState([]);
    const [productPrice, setProductPrice] = useState(0);
    const [selectedServiceType, setSelectedServiceType] = useState('');

    // ✅ Sync Zustand state when local state changes
    const {
        setProductId: setGlobalProductId,
        setSelectedAddOns: setGlobalSelectedAddOns,
        setProductPrice: setGlobalProductPrice,
        setSelectedServiceType: setGlobalSelectedServiceType
    } = frohubStore();

    useEffect(() => {
        const partnerElement = document.querySelector(".frohub_add_to_cart");
        if (partnerElement) {
            const partnerIdValue = partnerElement.getAttribute("data-partner-id");
            if (partnerIdValue) {
                setPartnerId(partnerIdValue);
            }
        }
    }, []);

    // ✅ Sync `productId` state
    useEffect(() => {
        setGlobalProductId(productId);
    }, [productId, setGlobalProductId]);

    // ✅ Sync `selectedAddOns` state
    useEffect(() => {
        setGlobalSelectedAddOns(selectedAddOns);
    }, [selectedAddOns, setGlobalSelectedAddOns]);

    // ✅ Sync `productPrice` state
    useEffect(() => {
        setGlobalProductPrice(productPrice);
    }, [productPrice, setGlobalProductPrice]);

    // ✅ Sync `selectedServiceType` state
    useEffect(() => {
        setGlobalSelectedServiceType(selectedServiceType);
    }, [selectedServiceType, setGlobalSelectedServiceType]);

    const handleServiceTypeChange = (serviceType) => {
        setSelectedServiceType(serviceType);
        // console.log('Selected Service Type:', serviceType);
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
            <RenderServiceTypes productId={productId} partnerId={partnerId} onServiceTypeChange={handleServiceTypeChange} />
        </div>
    );
}
