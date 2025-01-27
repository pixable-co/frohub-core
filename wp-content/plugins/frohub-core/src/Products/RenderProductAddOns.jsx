import { useEffect, useState } from 'react';

export default function RenderProductAddOns({ productId, setProductId, selectedAddOns, setSelectedAddOns, setProductPrice }) {
    const [addOns, setAddOns] = useState([]);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchProductId = () => {
            const productId = document.querySelector('.frohub_add_to_cart').dataset.productId;
            setProductId(productId);
        };

        fetchProductId();
    }, [setProductId]);

    useEffect(() => {
        const fetchProductData = async () => {
            if (productId) {
                try {
                    const response = await fetch(`/wp-json/frohub/v1/product-attributes?product_id=${productId}`);
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Network response was not ok: ${errorText}`);
                    }
                    const data = await response.json();
                    console.log(data);
                    setAddOns(data.add_ons || []);
                    setProductPrice(parseFloat(data.product_price) || 0);
                } catch (error) {
                    console.error('Error fetching product data:', error);
                    setError(error.message);
                }
            }
        };

        fetchProductData();
    }, [productId, setAddOns, setProductPrice]);

    const handleSelectAddOn = (addOn) => {
        setSelectedAddOns((prevSelectedAddOns) => {
            if (prevSelectedAddOns.includes(addOn)) {
                setProductPrice((prevPrice) => prevPrice - parseFloat(addOn.price));
                return prevSelectedAddOns.filter((item) => item !== addOn);
            } else {
                setProductPrice((prevPrice) => prevPrice + parseFloat(addOn.price));
                return [...prevSelectedAddOns, addOn];
            }
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