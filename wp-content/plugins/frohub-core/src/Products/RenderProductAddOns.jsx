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
        const fetchProductData = async () => {
            try {
                setLoading(true); // ✅ Show skeleton while fetching add-ons
                const response = await fetch(`/wp-json/frohub/v1/product-attributes?product_id=${productId}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Network response was not ok: ${errorText}`);
                }
                const data = await response.json();
                setAddOns(data.add_ons || []);
                setProductPrice(parseFloat(data.product_price) || 0);
                setLoading(false); // ✅ Stop loading after fetch
            } catch (error) {
                setError(error.message);
                setLoading(false);
            }
        };

        if (productId) {
            fetchProductData();
        }
    }, [productId, setProductPrice]);

    const handleSelectAddOn = (addOn) => {
        frohubStore.setState({ loading: true });
        setSelectedAddOns((prevSelectedAddOns) => {
            let updatedAddOns;
            if (prevSelectedAddOns.some(item => item.id === addOn.id)) {
                setProductPrice((prevPrice) => prevPrice - parseFloat(addOn.price));
                updatedAddOns = prevSelectedAddOns.filter((item) => item.id !== addOn.id);
            } else {
                setProductPrice((prevPrice) => prevPrice + parseFloat(addOn.price));
                updatedAddOns = [...prevSelectedAddOns, addOn];
            }

            // Extract selected add-on IDs
            const selectedAddOnIds = updatedAddOns.map(item => item.id);

            // Send selected add-on IDs to the API
            fetchData(
                "frohub/get_availibility",
                (response) => {
                    if (response.success) {
                        const currentAvailabilityData = frohubStore.getState().availabilityData;
                        if (JSON.stringify(currentAvailabilityData) !== JSON.stringify(response.data.availability)) {
                            setAvailabilityData(response.data.availability);
                            frohubStore.setState({ loading: false });
                        }
                    } else {
                        console.error("Error fetching availability:", response.message);
                        frohubStore.setState({ loading: false });
                    }
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
            <div className="mb-3">Select add-ons</div>
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
