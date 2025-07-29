import { useEffect, useState } from 'react';
import { fetchData } from "../services/fetchData.js";
import { Skeleton } from 'antd';
import frohubStore from "../frohubStore.js";
import FhCheckButton from "../common/controls/FhCheckButton.jsx";

export default function RenderProductAddOns({ productId, setProductId, selectedAddOns, setSelectedAddOns, setProductPrice }) {
    const { selectedDate, setAvailabilityData } = frohubStore();
    const [addOns, setAddOns] = useState([]);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true); // ✅ Static loading state only during fetch

    useEffect(() => {
        const fetchProductId = () => {
            const productElement = document.querySelector('.frohub_add_to_cart');
            if (productElement) {
                setProductId(productElement.dataset.productId);
            }
        };
        fetchProductId();
    }, [setProductId]);

    useEffect(() => {
        const fetchProductData = () => {
            setLoading(true); // ✅ Show skeleton while fetching add-ons

            fetchData(
                "frohub/get_addons",
                (response) => {
                    if (response.success) {
                        setAddOns(response.data.add_ons || []);
                        setProductPrice(parseFloat(response.data.product_price) || 0);
                    } else {
                        console.error("Error fetching add-ons:", response.message);
                        setError(response.message);
                    }
                    setLoading(false); // ✅ Stop loading after fetch
                },
                {
                    product_id: productId,
                }
            );
        };

        if (productId) {
            fetchProductData();
        }
    }, [productId, setProductPrice]);

    // Update hidden input whenever selectedAddOns changes
    useEffect(() => {
        // Extract selected add-on IDs
        const selectedAddOnIds = selectedAddOns.map(item => item.id);

        // Update hidden input with selected addon IDs
        const hiddenInput = document.getElementById('frohub-selected-addon-ids');
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(selectedAddOnIds);
        }
    }, [selectedAddOns]);

    const handleSelectAddOn = (addOn) => {
        frohubStore.setState({ loading: true });
        const addonMinutes = parseInt(addOn.duration_minutes, 10) || 0;
        setSelectedAddOns((prevSelectedAddOns) => {
            let updatedAddOns;
            if (prevSelectedAddOns.some(item => item.id === addOn.id)) {
                setProductPrice((prevPrice) => prevPrice - parseFloat(addOn.price));
                // When removing an addon, subtract its time from the total
                frohubStore.setState(prevState => ({
                    addonTotalTime: (prevState.addonTotalTime || 0) - addonMinutes
                }));
                updatedAddOns = prevSelectedAddOns.filter((item) => item.id !== addOn.id);
            } else {
                setProductPrice((prevPrice) => prevPrice + parseFloat(addOn.price));
                frohubStore.setState(prevState => ({
                    addonTotalTime: (prevState.addonTotalTime || 0) + addonMinutes
                }));
                updatedAddOns = [...prevSelectedAddOns, addOn];
            }

            // Extract selected add-on IDs
            const selectedAddOnIds = updatedAddOns.map(item => item.id);


            // Send selected add-on IDs to the API
            fetchData(
                "frohub/get_availibility",
                (response) => {
                    if (response.success) {
                        setAvailabilityData(response.data.availability);
                    } else {
                        console.error("Error fetching availability:", response.message);
                    }
                    frohubStore.setState({ loading: false });
                    // if (response.success) {
                    //     const currentAvailabilityData = frohubStore.getState().availabilityData;
                    //     if (JSON.stringify(currentAvailabilityData) !== JSON.stringify(response.data.availability)) {
                    //         setAvailabilityData(response.data.availability);
                    //         frohubStore.setState({ loading: false });
                    //     }
                    // } else {
                    //     console.error("Error fetching availability:", response.message);
                    //     frohubStore.setState({ loading: false });
                    // }
                },
                {
                    product_id: productId,
                    date: selectedDate,
                    addons_id: selectedAddOnIds,
                }
            );

            return updatedAddOns;
        });
    };

    return (
        <div>
            {/* Hidden input to store selected addon IDs */}
            <input
                type="hidden"
                id="frohub-selected-addon-ids"
                name="frohub-selected-addon-ids"
                value={JSON.stringify(selectedAddOns.map(item => item.id))}
            />

            {loading ? (
                <div className="flex gap-2 !mb-3">
                    {Array.from({ length: 3 }).map((_, index) => (
                        <Skeleton.Button key={index} active style={{ width: 150, height: 40, borderRadius: 20 }} />
                    ))}
                </div>
            ) : productId ? (
                <>
                    {error ? (
                        <p>Error: {error}</p>
                    ) : (
                        <>
                            {Array.isArray(addOns) && addOns.length > 0 && (
                                <>
                                <div className="mb-3">Select add-ons</div>
                                <ul className="flex justify-start items-start gap-2 !list-none !p-0 !m-0 !mb-3">
                                    {addOns.map((addOn) => (
                                        <li key={addOn.id}>
                                            <FhCheckButton
                                                addOn={addOn}
                                                selectedAddOns={selectedAddOns}
                                                handleSelectAddOn={handleSelectAddOn}
                                            />
                                        </li>
                                    ))}
                                </ul>
                                </>
                            )}
                        </>
                    )}
                </>
            ) : (
                <p>Loading add-ons...</p>
            )}
        </div>
    );
}
