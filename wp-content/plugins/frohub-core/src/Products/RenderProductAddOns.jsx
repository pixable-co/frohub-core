import { useEffect, useState } from 'react';
import { fetchData } from "../services/fetchData.js";
import { Spin } from 'antd';
import frohubStore from "../frohubStore.js";

export default function RenderProductAddOns({ productId, setProductId, selectedAddOns, setSelectedAddOns, setProductPrice }) {
    const { selectedDate, setAvailabilityData } = frohubStore();
    const [addOns, setAddOns] = useState([]);
    const [error, setError] = useState(null);
    const [isFetched, setIsFetched] = useState(false);
    const [isAddonLoading, setIsAddonLoading] = useState(null);

    useEffect(() => {
        const fetchProductId = () => {
            const productId = document.querySelector('.frohub_add_to_cart').dataset.productId;
            setProductId(productId);
        };
        fetchProductId();
    }, [setProductId]);

    useEffect(() => {
        const fetchProductData = async () => {
            try {
                const response = await fetch(`/wp-json/frohub/v1/product-attributes?product_id=${productId}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Network response was not ok: ${errorText}`);
                }
                const data = await response.json();
                setAddOns(data.add_ons || []);
                setProductPrice(parseFloat(data.product_price) || 0);
                setIsFetched(true);
            } catch (error) {
                setError(error.message);
            }
        };

        if (!isFetched && productId) {
            fetchProductData();
        }
    }, [productId, isFetched, setAddOns, setProductPrice]);

    const handleSelectAddOn = (addOn) => {
        setIsAddonLoading(addOn.id);

        // Update selected add-ons first
        setSelectedAddOns((prevSelectedAddOns) => {
            let updatedAddOns;
            if (prevSelectedAddOns.includes(addOn)) {
                setProductPrice((prevPrice) => prevPrice - parseFloat(addOn.price));
                updatedAddOns = prevSelectedAddOns.filter((item) => item !== addOn);
            } else {
                setProductPrice((prevPrice) => prevPrice + parseFloat(addOn.price));
                updatedAddOns = [...prevSelectedAddOns, addOn];
            }

            // Extract all selected add-on IDs
            const selectedAddOnIds = updatedAddOns.map(item => item.id);

            // Send all selected add-on IDs to the API
            fetchData(
                "frohub/get_availibility",
                (response) => {
                    if (response.success) {
                        const currentAvailabilityData = frohubStore.getState().availabilityData;
                        if (JSON.stringify(currentAvailabilityData) !== JSON.stringify(response.data.availability)) {
                            setAvailabilityData(response.data.availability);
                        }
                    } else {
                        console.error("Error fetching availability:", response.message);
                    }
                    setIsAddonLoading(null);
                },
                {
                    product_id: productId,
                    date: selectedDate,
                    addons_id: selectedAddOnIds  // Send all selected add-on IDs
                }
            );

            return updatedAddOns;
        });
    };

    return (
        <div>
            {productId ? (
                <>
                    {error ? (
                        <p>Error: {error}</p>
                    ) : (
                        <>
                            {Array.isArray(addOns) && addOns.length > 0 && (
                                <ul>
                                    {addOns.map((addOn, index) => (
                                        <li key={index}>
                                            <label>
                                                <Spin spinning={isAddonLoading === addOn.id} size="small">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedAddOns.includes(addOn)}
                                                        onChange={() => handleSelectAddOn(addOn)}
                                                        data-add-on-name={addOn.name}
                                                        data-add-on-id={addOn.id}
                                                        data-price={addOn.price}
                                                        data-duration={addOn.duration_minutes}
                                                    />
                                                    {addOn.name} £{addOn.price} <br />
                                                </Spin>
                                            </label>
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
