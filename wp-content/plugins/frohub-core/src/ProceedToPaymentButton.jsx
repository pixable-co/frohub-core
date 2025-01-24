import React from 'react';
import { useEffect, useState } from 'react';

export default function ProceedToPaymentButton({ selectedAddOns, productPrice, productId}) {
    const handleProceedToPayment = () => {
        // Implement the logic to proceed to payment
        console.log('Proceeding to payment with:', selectedAddOns, productPrice);

        // Call the AJAX function
        jQuery.ajax({
            url: frohubCoreAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'frohub_add_to_cart',
                nonce: frohubCoreAjax.nonce,
                productId: productId,
                selectedAddOns: selectedAddOns,
                productPrice: productPrice
            },
            success: function(response) {
                console.log('AJAX call successful:', response.data.message);
                console.log('Product ID:', response.data.product_id);
                console.log('Selected Add-Ons:', response.data.selected_add_ons);
                console.log('Product Price:', response.data.product_price);
            },
            error: function(error) {
                console.log('AJAX call failed:', error);
            }
        });
    };

    return (
        <button onClick={handleProceedToPayment}>
            Proceed to Payment
        </button>
    );
}
