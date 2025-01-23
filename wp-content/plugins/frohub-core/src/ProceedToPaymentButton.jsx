import React from 'react';
import { useEffect, useState } from 'react';

export default function ProceedToPaymentButton({ selectedAddOns, productPrice }) {
    const handleProceedToPayment = () => {
        // Implement the logic to proceed to payment
        console.log('Proceeding to payment with:', selectedAddOns, productPrice);
    };

    return (
        <button onClick={handleProceedToPayment}>
            Proceed to Payment
        </button>
    );
}
