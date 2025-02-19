import React, { useEffect, useState } from "react";
import FhServiceButton from "../common/controls/FhServiceButton.jsx";
import { fetchData } from "../services/fetchData.js";

export default function RenderServiceTypes({ productId, onServiceTypeChange }) {
    const [serviceTypes, setServiceTypes] = useState([]);
    const [selectedService, setSelectedService] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (productId) {
            setLoading(true);
            fetchData(
                "frohub/get_service_type",
                (response) => {
                    console.log("API Response:", response);
                    if (response.success && response.data?.data && Array.isArray(response.data.data)) {
                        setServiceTypes(response.data.data);
                        setSelectedService(response.data.data[0]?.toLowerCase() || null);
                        onServiceTypeChange(response.data.data[0]?.toLowerCase() || null);
                    }
                    setLoading(false);
                },
                { product_id: productId }
            );
        }
    }, [productId]);

    return (
        <div>
            Service Type
            <form>
                <div className="flex gap-4 mt-3 mb-6">
                    {loading
                        ? Array.from({ length: 3 }).map((_, index) => (
                            <FhServiceButton key={index} loading={true} />
                        ))
                        : serviceTypes.map((service) => (
                            <FhServiceButton
                                key={service.toLowerCase()}
                                service={service}
                                selectedService={selectedService}
                                handleSelectService={setSelectedService}
                                loading={false}
                            />
                        ))}
                </div>
            </form>
        </div>
    );
}