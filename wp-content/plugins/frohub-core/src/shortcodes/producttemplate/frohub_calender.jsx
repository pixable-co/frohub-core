import { useEffect, useState, useRef, useCallback } from "react";
import { Spin } from "antd";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";

const FrohubCalender = () => {
    const productIdRef = useRef(null); // Use ref to avoid unnecessary re-renders
    const [availabilityData, setAvailabilityData] = useState([]);
    const [selectedDate, setSelectedDate] = useState(null);
    const [loading, setLoading] = useState(false);

    // Set today's date on initial load
    useEffect(() => {
        const today = new Date().toISOString().split("T")[0]; // Format: YYYY-MM-DD
        setSelectedDate(today);
    }, []);

    // Set product ID once (without causing re-render)
    useEffect(() => {
        const productElement = document.querySelector(".frohub_add_to_cart");
        if (productElement) {
            productIdRef.current = productElement.dataset.productId;
        }
    }, []);

    // Fetch availability only when necessary
    const fetchAvailability = useCallback(async () => {
        if (!productIdRef.current || !selectedDate) return;

        setLoading(true);
        fetchData(
            "frohub/get_availibility",
            (response) => {
                if (response.success) {
                    setAvailabilityData((prevData) =>
                        JSON.stringify(prevData) !== JSON.stringify(response.data.availability)
                            ? response.data.availability
                            : prevData
                    );
                } else {
                    console.error("Error fetching availability:", response.message);
                }
                setLoading(false);
            },
            { product_id: productIdRef.current, date: selectedDate }
        );
    }, [selectedDate]); // Dependency array contains only `selectedDate` to avoid unnecessary calls

    useEffect(() => {
        if (selectedDate) {
            fetchAvailability();
        }
    }, [fetchAvailability, selectedDate]); // Ensure it runs when `selectedDate` updates

    return (
        <div className="relative">
            <Spin spinning={loading} size="large">
                <FhCalender data={availabilityData} onDateChange={setSelectedDate} />
            </Spin>
        </div>
    );
};

export default FrohubCalender;
