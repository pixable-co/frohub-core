import React from 'react';
import { useEffect, useState } from 'react';
import {createData} from "../services/createData.js";

export default function ProceedToPaymentButton({ selectedAddOns, productPrice, productId, selectedServiceType }) {
    const handleProceedToPayment = async () => {
        try {
            const response = await createData('frohub_add_to_cart', {
                productId: productId,
                selectedAddOns: selectedAddOns,
                productPrice: productPrice,
                selectedServiceType: selectedServiceType,
            });

            console.log('AJAX call successful:', response.data.message);
            console.log('Product ID:', response.data.product_id);
            console.log('Selected Add-Ons:', response.data.selected_add_ons);
            console.log('Product Price:', response.data.product_price);
            console.log('Selected Service Type:', response.data.selected_service_type);
        } catch (error) {
            console.error('AJAX call failed:', error);
        }
    };

    return (
        <div>
            <p>Total Price: £{productPrice.toFixed(2)}</p>
            <button onClick={handleProceedToPayment}>
                Proceed to Payment
            </button>
        </div>
    );
}
