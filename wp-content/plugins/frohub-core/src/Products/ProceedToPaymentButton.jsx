import { useEffect, useState } from 'react';
import frohubStore from "../frohubStore.js";
import { createData } from "../services/createData.js";

export default function RequestBookButton() {
    const { selectedAddOns, productPrice, productId, selectedServiceType } = frohubStore();
    const [totalPrice, setTotalPrice] = useState(productPrice);
    const depositDueToday = (totalPrice * 0.33).toFixed(2); // Assuming 33% deposit required
    const serviceDuration = 4; // Static duration (change if needed)

    useEffect(() => {
        const getExtraCharge = () => {
            const container = document.getElementById('extra-charge-container');
            if (container) {
                const extraCharge = parseFloat(container.getAttribute('data-extra-charge')) || 0;
                setTotalPrice(productPrice + extraCharge);
            }
        };

        getExtraCharge();

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-extra-charge') {
                    getExtraCharge();
                }
            });
        });

        const container = document.getElementById('extra-charge-container');
        if (container) {
            observer.observe(container, { attributes: true, attributeFilter: ['data-extra-charge'] });
        }

        return () => observer.disconnect();
    }, [productPrice]);

    const handleProceedToPayment = async () => {
        const selectedDate = document.querySelector('input[name="selectedDate"]')?.value || "";
        const selectedTime = document.querySelector('input[name="selectedTime"]')?.value || "";
        const extraCharge = document.querySelector('input[name="selectedPrice"]')?.value || "";

        try {
            const response = await createData('frohub_add_to_cart', {
                productId,
                selectedAddOns,
                productPrice: totalPrice,
                selectedServiceType,
                selectedDate,
                selectedTime,
                extraCharge
            });

            console.log('AJAX call successful:', response.data.message);
        } catch (error) {
            console.error('AJAX call failed:', error);
        }
    };

    return (
        <div className="fixed bottom-0 left-0 w-full bg-white shadow-md py-4 px-6 flex justify-between items-center z-50">
            <div className="flex items-center gap-2 text-gray-700 text-sm">
                <span className="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-black mr-1" viewBox="0 0 24 24" fill="currentColor">
                        <path fillRule="evenodd" d="M12 2a10 10 0 1 1-10 10A10 10 0 0 1 12 2zm0 18a8 8 0 1 0-8-8 8 8 0 0 0 8 8zm-1-13a1 1 0 0 1 2 0v4a1 1 0 0 1-2 0zm1 6a1.25 1.25 0 1 1-1.25 1.25A1.25 1.25 0 0 1 12 13z" clipRule="evenodd" />
                    </svg>
                    <span>All deposits paid through FroHub are protected. <a href="#" className="text-black font-medium">Learn More</a></span>
                </span>
            </div>

            <div className="text-right">
                <p className="text-gray-900 font-semibold">Total price: <span className="text-black">£{totalPrice.toFixed(2)}</span></p>
                <p className="text-gray-600 text-sm">Deposit due today: <span className="font-medium text-black">£{depositDueToday}</span></p>
                <p className="text-gray-600 text-sm">Service duration: <span className="font-medium">{serviceDuration} hours</span></p>
            </div>

            <button
                onClick={handleProceedToPayment}
                className="bg-gray-300 text-black font-medium px-6 py-2 rounded-full hover:bg-gray-400 transition"
            >
                Request to Book
            </button>
        </div>
    );
}
