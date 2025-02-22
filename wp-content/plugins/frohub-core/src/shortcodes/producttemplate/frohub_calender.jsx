import { useEffect, useRef, useCallback } from "react";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";
import frohubStore from "../../frohubStore.js";

const FrohubCalender = () => {
    const productIdRef = useRef(null);
    const {
        availabilityData,
        setAvailabilityData,
        selectedDate,
        setSelectedDate,
        loading,
        setLoading,
        addonsChanged,
        setAddonsChanged,
        resetAddonsChanged // ✅ New function to reset addonsChanged
    } = frohubStore();

    // ✅ Set today's date on first load
    useEffect(() => {
        const today = new Date().toISOString().split("T")[0];
        setSelectedDate(today);
    }, [setSelectedDate]);

    // ✅ Get product ID once
    useEffect(() => {
        const productElement = document.querySelector(".frohub_add_to_cart");
        if (productElement) {
            productIdRef.current = productElement.dataset.productId;
        }
    }, []);

    // ✅ Fetch availability only on first load OR when addons change
    const fetchAvailability = useCallback(async () => {
        if (!productIdRef.current) return;
        if (!addonsChanged && availabilityData.length > 0) return; // ✅ Prevent unnecessary calls

        setLoading(true);
        fetchData(
            "frohub/get_availibility",
            (response) => {
                if (response.success) {
                    setAvailabilityData(response.data.availability);
                } else {
                    console.error("Error fetching availability:", response.message);
                }
                setLoading(false);
            },
            { product_id: productIdRef.current, date: selectedDate }
        );

        resetAddonsChanged(); // ✅ Reset after fetching
    }, [productIdRef.current, selectedDate, addonsChanged, availabilityData.length, setAvailabilityData, setLoading, resetAddonsChanged]);

    useEffect(() => {
        fetchAvailability();
    }, [fetchAvailability]);

    return (
        <div className="relative">
            <FhCalender data={availabilityData} onDateChange={setSelectedDate} />
        </div>
    );
};

export default FrohubCalender;
