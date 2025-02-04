import React, { useEffect, useState } from 'react';
import { createData } from "../services/createData.js";

export default function ProceedToPaymentButton({ selectedAddOns, productPrice, productId, selectedServiceType }) {
    const [totalPrice, setTotalPrice] = useState(productPrice);

    useEffect(() => {
        // Function to get the extra charge from the data attribute
        const getExtraCharge = () => {
            const container = document.getElementById('extra-charge-container');
            if (container) {
                const extraCharge = parseFloat(container.getAttribute('data-extra-charge')) || 0;
                setTotalPrice(productPrice + extraCharge);
            }
        };

        // Initial check
        getExtraCharge();

        // Set up a MutationObserver to watch for changes to the data-extra-charge attribute
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-extra-charge') {
                    getExtraCharge();
                }
            });
        });

        const container = document.getElementById('extra-charge-container');
        if (container) {
            observer.observe(container, {
                attributes: true,
                attributeFilter: ['data-extra-charge']
            });
        }

        // Cleanup observer on component unmount
        return () => observer.disconnect();
    }, [productPrice]);

    const handleProceedToPayment = async () => {
        const selectedDate = document.querySelector('input[name="selectedDate"]')?.value || "";
        const selectedTime = document.querySelector('input[name="selectedTime"]')?.value || "";

        console.log('Selected Date:', selectedDate);

        try {
            const response = await createData('frohub_add_to_cart', {
                productId: productId,
                selectedAddOns: selectedAddOns,
                productPrice: totalPrice, // Using the updated total price
                selectedServiceType: selectedServiceType,
                selectedDate: selectedDate,
                selectedTime: selectedTime,
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
            <p>Total Price: £{totalPrice.toFixed(2)}</p>
            <button onClick={handleProceedToPayment}>
                Proceed to Payment
            </button>
        </div>
    );
}