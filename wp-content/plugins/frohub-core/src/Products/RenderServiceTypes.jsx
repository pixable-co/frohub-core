import React, { useEffect, useState } from "react";
import FhServiceButton from "../common/controls/FhServiceButton.jsx";
import { fetchData } from "../services/fetchData.js";
import MobileService from "./MobileService.jsx";
import frohubStore from "../frohubStore.js";

export default function RenderServiceTypes({ productId, partnerId, onServiceTypeChange }) {
    const [serviceTypes, setServiceTypes] = useState([]);
    const [selectedService, setSelectedService] = useState(null);
    const [loading, setLoading] = useState(true);
    const { setTotalService } = frohubStore(); // <- this is essential

    useEffect(() => {
        if (productId) {
            setLoading(true);
            fetchData(
                "frohub/get_service_type",
                (response) => {
                    if (response.success && response.data?.data && Array.isArray(response.data.data)) {
                        const firstService = response.data.data[0]?.toLowerCase() || null;
                        setServiceTypes(response.data.data);
                        setTotalService(response.data.data.length);
                        setSelectedService(firstService);
                        onServiceTypeChange(firstService); // ✅ Notify parent component
                    }
                    setLoading(false);
                },
                { product_id: productId }
            );
        }
    }, [productId]);

    const handleSelectService = (service) => {
        const lowerCasedService = service.toLowerCase();
        setSelectedService(lowerCasedService);
        onServiceTypeChange(lowerCasedService); // ✅ Notify parent when changed
    };

    return (
        <>
            <div>
                Service Type
                <form>
                    <div className="grid sm:grid-cols-1 md:grid-cols-3 gap-4 mt-3 mb-6">
                        {loading
                            ? Array.from({ length: 3 }).map((_, index) => (
                                <FhServiceButton key={index} loading={true} />
                            ))
                            : serviceTypes.map((service) => (
                                <FhServiceButton
                                    key={service.toLowerCase()}
                                    service={service}
                                    selectedService={selectedService}
                                    handleSelectService={handleSelectService} // ✅ Pass updated handler
                                    loading={false}
                                />
                            ))}
                    </div>
                </form>
            </div>

            {/* ✅ Show Mobile Service only if "mobile" is selected */}
            {selectedService === "mobile" && <MobileService partnerId={partnerId} />}
        </>
    );
}