import { useEffect, useRef, useCallback, useState } from "react";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";
import frohubStore from "../../frohubStore.js";

const FrohubCalender = () => {
    const productIdRef = useRef(null);
    const [bookingNotice, setBookingNotice] = useState(null);
    const [maxDate, setMaxDate] = useState(null);
    const selectedAddonsRef = useRef([]);

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

    // ✅ Get selected addon IDs from hidden input
    const getSelectedAddonIds = useCallback(() => {
        // Get addon IDs from the hidden input
        let addonIds = [];
        const hiddenInput = document.getElementById('frohub-selected-addon-ids');

        if (hiddenInput && hiddenInput.value) {
            try {
                addonIds = JSON.parse(hiddenInput.value);
            } catch (e) {
                console.error("Error parsing addon IDs", e);
                addonIds = [];
            }
        }

        return addonIds;
    }, []);

    // ✅ Fetch availability based on date or addon changes
    const fetchAvailability = useCallback(async () => {
        const productId = productIdRef.current;
        if (!productId) return;

        setLoading(true);

        // Get selected addon IDs from hidden input
        const addonIds = getSelectedAddonIds();

        fetchData(
            "frohub/get_availibility",
            (response) => {
                if (response.success) {
                    setAvailabilityData(response.data.availability);
                    setBookingNotice(response.data.booking_notice);
                    setMaxDate(response.data.max_date);
                } else {
                    console.error("Error fetching availability:", response.message);
                }
                setLoading(false);
            },
            {
                product_id: productId,
                date: selectedDate,
                addons_id: addonIds
            }
        );

        resetAddonsChanged();
    }, [selectedDate, setAvailabilityData, setLoading, resetAddonsChanged, getSelectedAddonIds]);

    // ✅ Fetch when date changes or addons change
    useEffect(() => {
        fetchAvailability();
    }, [fetchAvailability, selectedDate, addonsChanged]);

    // ✅ Handle date change
    const handleDateChange = (newDate) => {
        setSelectedDate(newDate);

        const productId = productIdRef.current;
        if (!productId) return;

        setLoading(true);

        // Get selected addon IDs from hidden input
        const addonIds = getSelectedAddonIds();

        fetchData(
            "frohub/get_availibility",
            (response) => {
                if (response.success) {
                    setAvailabilityData(response.data.availability);
                    setBookingNotice(response.data.booking_notice);
                    setMaxDate(response.data.max_date);
                } else {
                    console.error("Error fetching availability:", response.message);
                }
                setLoading(false);
            },
            {
                product_id: productId,
                date: newDate,
                addons_id: addonIds
            }
        );
    };

    return (
        <div className="relative">
            <FhCalender
                data={availabilityData}
                onDateChange={handleDateChange}
                bookingNotice={bookingNotice}
                maxDate={maxDate}
            />
        </div>
    );
};

export default FrohubCalender;
