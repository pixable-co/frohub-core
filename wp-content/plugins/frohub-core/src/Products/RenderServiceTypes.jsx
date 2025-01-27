import React, { useEffect, useState, useRef } from 'react';

export default function RenderServiceTypes({ productId, onServiceTypeChange }) {
    const [serviceTypes, setServiceTypes] = useState([]);
    const isFetchedRef = useRef(false);

    useEffect(() => {
        if (!isFetchedRef.current && productId) {
            fetch(`/wp-json/frohub/v1/product-service-type?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data); // Log the API response
                    if (data.success) {
                        setServiceTypes(data.data);
                    }
                    isFetchedRef.current = true;
                });
        }
    }, [productId]);

    return (
        <div>
            <form>
                {Array.isArray(serviceTypes) && serviceTypes.length > 0 ? (
                    serviceTypes.map((type, index) => (
                        <div key={index}>
                            <input
                                type="radio"
                                id={`serviceType${index}`}
                                name="serviceType"
                                value={type}
                                onChange={onServiceTypeChange}
                                defaultChecked={index === 0} // Select the first service type by default
                            />
                            <label htmlFor={`serviceType${index}`}>{type}</label>
                        </div>
                    ))
                ) : (
                    <p>No service types available.</p>
                )}
            </form>
        </div>
    );
}
