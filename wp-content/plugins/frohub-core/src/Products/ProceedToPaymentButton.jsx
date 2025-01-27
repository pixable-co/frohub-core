import React from 'react';
import { useEffect, useState } from 'react';

export default function ProceedToPaymentButton({ selectedAddOns, productPrice, productId, selectedServiceType }) {
    const handleProceedToPayment = () => {
        // Call the AJAX function
        jQuery.ajax({
            url: frohubCoreAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'frohub_add_to_cart',
                nonce: frohubCoreAjax.nonce,
                productId: productId,
                selectedAddOns: selectedAddOns,
                productPrice: productPrice,
                selectedServiceType: selectedServiceType
            },
            success: function(response) {
                console.log('AJAX call successful:', response.data.message);
                console.log('Product ID:', response.data.product_id);
                console.log('Selected Add-Ons:', response.data.selected_add_ons);
                console.log('Product Price:', response.data.product_price);
                console.log('Selected Service Type:', response.data.selected_service_type);
            },
            error: function(error) {
                console.log('AJAX call failed:', error);
            }
        });
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
