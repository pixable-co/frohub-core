import { useEffect, useState } from 'react';
import { Spin } from 'antd';
import { Check } from 'lucide-react'; // ✅ Import check icon
import frohubStore from "../../frohubStore.js";
import {fetchData} from "../../services/fetchData.js";
import { createData } from "../../services/createData.js";
import { toastNotification } from "../../utils/toastNotification.js";

export default function RequestBookButton() {
    const { selectedAddOns, productPrice, productId, selectedServiceType, mobileTravelFee, initialServiceDuration } = frohubStore(); // ✅ Get mobileTravelFee from Zustand
    const [totalPrice, setTotalPrice] = useState(productPrice);
    const [loading, setLoading] = useState(false);
    const [booked, setBooked] = useState(false);
    const [serviceDuration, setServiceDuration] = useState("0h 0m"); // ✅ Track duration in hours & minutes

    const depositDueToday = totalPrice * 0.30;

    useEffect(() => {
        const fetchServiceDuration = () => {
            if (!productId) return;

            fetchData(
                "frohub/get_duration",
                (response) => {
                    if (response.success) {
                        const hours = response.data.duration_hours || 0;
                        const minutes = response.data.duration_minutes || 0;

                        // ✅ Update service duration display
                        setServiceDuration(`${hours}h ${minutes}m`);
                    } else {
                        console.error("Error fetching service duration:", response.message);
                    }
                },
                { product_id: productId }
            );
        };

        fetchServiceDuration();
    }, [productId]); // ✅ Runs when `productId` changes

    useEffect(() => {
        const getExtraCharge = () => {
            const container = document.getElementById('extra-charge-container');
            if (container) {
                const extraCharge = parseFloat(container.getAttribute('data-extra-charge')) || 0;
                setTotalPrice(productPrice + extraCharge + (mobileTravelFee || 0)); // ✅ Include mobileTravelFee
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
    }, [productPrice, mobileTravelFee]); // ✅ Include mobileTravelFee dependency

    useEffect(() => {
        const getServiceDuration = () => {
            const durationInput = document.querySelector('input[name="total_duration_time"]');
            if (durationInput) {
                const totalMinutes = parseInt(durationInput.value, 10) || 0;
                const hours = Math.floor(totalMinutes / 60);
                const minutes = totalMinutes % 60;
                setServiceDuration(`${hours}h ${minutes}m`); // ✅ Convert to hours & minutes
            }
            else {
                const hours = Math.floor(initialServiceDuration / 60);
                const minutes = initialServiceDuration % 60;
                setServiceDuration(`${hours}h ${minutes}m`);
            }
        };

        getServiceDuration();
        const durationObserver = new MutationObserver(getServiceDuration);

        const durationInput = document.querySelector('input[name="total_duration_time"]');
        if (durationInput) {
            durationObserver.observe(durationInput, { attributes: true, attributeFilter: ['value'] });
        }

        return () => durationObserver.disconnect();
    }, []);

    const handleProceedToPayment = async () => {
        const selectedDate = document.querySelector('input[name="selectedDate"]')?.value || "";
        const selectedTime = document.querySelector('input[name="selectedTime"]')?.value || "";
        const selectedTimeButton = document.querySelector(".timeslot-button.selected");

        if (!selectedDate || !selectedTimeButton) {
            toastNotification('error', 'Missing Information', 'Please select a service type and date & time before proceeding.');
            return;
        }

        setLoading(true);

        const depositDue = totalPrice - depositDueToday;
        const serviceFee = depositDueToday * 0.03;

        document.cookie = `frohub_service_fee=${serviceFee}; path=/; max-age=86400`;

        try {
            const response = await createData('frohub_add_to_cart', {
                productId,
                selectedAddOns,
                depositDue,
                depositDueToday,
                serviceFee,
                productPrice: depositDueToday,
                totalPrice,
                selectedServiceType,
                selectedDate,
                selectedTime,
            });

            // toastNotification('success', `Success`, `The requested service has been added to the cart successfully`);
            setBooked(true);

            // ✅ Redirect to checkout page
            window.location.href = "/checkout";
        } catch (error) {
            console.error('AJAX call failed:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed bottom-0 left-0 w-full bg-white shadow-md py-4 px-6 z-50">
            <div className="w-4/6 mx-auto flex flex-col items-center">
                <div className="w-full flex justify-between items-center">
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

                        {/* ✅ Show Mobile Travel Fee only if greater than 0 */}
                        {mobileTravelFee > 0 && (
                            <p className="text-gray-600 text-sm">
                                Mobile Travel Fee: <span className="font-medium text-black">£{mobileTravelFee.toFixed(2)}</span>
                            </p>
                        )}

                        <p className="text-gray-600 text-sm">Deposit due today: <span className="font-medium text-black">£{depositDueToday}</span></p>
                        <p className="text-gray-600 text-sm">Service duration: <span className="font-medium">{serviceDuration}</span></p>
                        <input name="service-duration" type="hidden" value={serviceDuration} />
                    </div>

                    <button
                        onClick={handleProceedToPayment}
                        disabled={loading || booked}
                        className={`bg-gray-300 text-black font-medium px-6 py-2 rounded-full transition flex items-center justify-center ${loading || booked ? 'cursor-not-allowed opacity-75' : 'hover:bg-gray-400'}`}
                        style={{ minWidth: "150px", height: "40px" }}
                    >
                        {loading ? (
                            <Spin size="small" className="mr-2" />
                        ) : booked ? (
                            <Spin size="small" className="mr-2" />
                        ) : (
                            "Request to Book"
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
