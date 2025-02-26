import { useEffect, useRef, useCallback, useState } from "react";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";
import frohubStore from "../../frohubStore.js";

const FrohubCalender = () => {
    const productIdRef = useRef(null);
    const [bookingNotice, setBookingNotice] = useState(null);

    const {
        availabilityData,
        setAvailabilityData,
        selectedDate,
        setSelectedDate,
        loading,
        setLoading,
        addonsChanged,
        setAddonsChanged,
        resetAddonsChanged
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

    // ✅ Fetch availability including initial service duration
    const fetchAvailability = useCallback(async () => {
        const productId = productIdRef.current;
        if (!productId) return;
        if (!addonsChanged && availabilityData.length > 0) return; // ✅ Prevent unnecessary calls

        setLoading(true);
        fetchData(
            "frohub/get_availibility",
            (response) => {
                console.log("Fetch response:", response); // Debugging line
                if (response.success) {
                    setAvailabilityData(response.data.availability);
                    setBookingNotice(response.data.booking_notice);
                } else {
                    console.error("Error fetching availability test:", response);
                }
                setLoading(false);
            },
            { product_id: productId, date: selectedDate }
        );

        resetAddonsChanged(); // ✅ Reset after fetching
    }, [selectedDate, addonsChanged, availabilityData.length, setAvailabilityData, setLoading, resetAddonsChanged]); // ✅ Removed `productIdRef.current`

    useEffect(() => {
        fetchAvailability();
    }, [fetchAvailability]);

    return (
        <div className="relative">
            <FhCalender
                data={availabilityData}
                onDateChange={setSelectedDate}
                bookingNotice={bookingNotice}
            />
        </div>
    );
};

export default FrohubCalender;
