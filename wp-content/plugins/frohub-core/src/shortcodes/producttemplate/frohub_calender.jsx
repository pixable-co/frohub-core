import { useEffect, useRef, useCallback } from "react";
import { Spin } from "antd";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";
import frohubStore from "../../frohubStore.js";

const FrohubCalender = () => {
    const productIdRef = useRef(null); // Use ref to avoid unnecessary re-renders

    // Zustand store hooks
    const {
        availabilityData,
        setAvailabilityData,
        selectedDate,
        setSelectedDate,
        loading,
        setLoading
    } = frohubStore();

    // Set today's date on initial load
    useEffect(() => {
        const today = new Date().toISOString().split("T")[0];
        setSelectedDate(today);
    }, [setSelectedDate]);

    // Set product ID once (without causing re-render)
    useEffect(() => {
        const productElement = document.querySelector(".frohub_add_to_cart");
        if (productElement) {
            productIdRef.current = productElement.dataset.productId;
        }
    }, []);

    // Fetch availability when selectedDate changes
    const fetchAvailability = useCallback(async () => {
        if (!productIdRef.current || !selectedDate) return;

        setLoading(true); // Set loading true before fetching data
        fetchData(
            "frohub/get_availibility",
            (response) => {
                if (response.success) {
                    setAvailabilityData(response.data.availability);
                } else {
                    console.error("Error fetching availability:", response.message);
                }
                setLoading(false); // Set loading false after response
            },
            { product_id: productIdRef.current, date: selectedDate }
        );
    }, [selectedDate, setAvailabilityData, setLoading]);

    useEffect(() => {
        if (selectedDate) {
            fetchAvailability();
        }
    }, [fetchAvailability, selectedDate]);

    return (
        <div className="relative">
            <Spin spinning={loading} size="large">
                <FhCalender data={availabilityData} onDateChange={setSelectedDate} />
            </Spin>
        </div>
    );
};

export default FrohubCalender;