import { useEffect, useRef, useCallback, useState } from "react";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";
import frohubStore from "../../frohubStore.js";
import dayjs from "dayjs";
import customParseFormat from "dayjs/plugin/customParseFormat";
import isBetween from "dayjs/plugin/isBetween";

dayjs.extend(customParseFormat);  // ✅ Allows custom date parsing
dayjs.extend(isBetween);  // ✅ Enables checking if a time falls between two others

const FrohubCalender = () => {
    const productIdRef = useRef(null);
    const [bookingNotice, setBookingNotice] = useState(null);
    const [maxDate, setMaxDate] = useState(null);
    const [unavailableDates, setUnavailableDates] = useState([]);
    const [unavailableTimes, setUnavailableTimes] = useState([]);
    const [nextAvailableDate, setNextAvailableDate] = useState(null);
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
                    setNextAvailableDate(response.data.next_available_date);
                    setAvailabilityData(response.data.availability);
                    setBookingNotice(response.data.booking_notice);
                    setMaxDate(response.data.max_date);
                    setUnavailableDates(response.data.unavailable_dates || []);

                    const parseDate = (dateString) => {
                        if (!dateString) return null;
                        // ✅ Trim spaces & normalize formats
                        let cleanedDate = dateString.trim();

                        // ✅ Handle AM/PM format correctly
                        cleanedDate = cleanedDate
                            .replace(/\b12:00 am\b/i, "00:00") // Midnight fix
                            .replace(/\b12:00 pm\b/i, "12:00") // Noon fix
                            .replace(/\b(\d):(\d{2}) (am|pm)\b/i, "0$1:$2 $3") // Ensure single-digit hours have leading zeros
                            .replace(/\b(\d{1,2}):(\d{2}) ([ap]m)\b/i, (match, h, m, ampm) => { // Ensure double-digit hours
                                let hour = parseInt(h, 10);
                                if (ampm.toLowerCase() === "pm" && hour !== 12) hour += 12;
                                if (ampm.toLowerCase() === "am" && hour === 12) hour = 0;
                                return `${hour.toString().padStart(2, "0")}:${m}`;
                            });

                        // ✅ Define multiple formats to check
                        const formats = [
                            "DD/MM/YYYY HH:mm",  // 24-hour format
                            "DD/MM/YYYY hh:mm A", // 12-hour format
                            "DD/MM/YYYY h:mm A",  // Single-digit hour 12-hour format
                            "YYYY-MM-DD HH:mm",   // ISO format
                            "YYYY-MM-DD",         // Date-only format
                            "DD/MM/YYYY"          // Date-only format
                        ];

                        let parsedDate = null;
                        for (let format of formats) {
                            parsedDate = dayjs(cleanedDate, format, true);
                            if (parsedDate.isValid()) {
                                return parsedDate;
                            }
                        }

                        return null;
                    };

                    const formattedUnavailableTimes = (response.data.unavailable_dates || []).map(({ start_date, end_date }) => {
                        const start = parseDate(start_date);
                        const end = parseDate(end_date);

                        if (!start || !end) {
                            return null; // Skip invalid data
                        }

                        return { start, end };
                    }).filter(Boolean); // ✅ Remove null values
                    setUnavailableTimes(formattedUnavailableTimes);
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

        // const productId = productIdRef.current;
        // if (!productId) return;
        //
        // setLoading(true);
        //
        // // Get selected addon IDs from hidden input
        // const addonIds = getSelectedAddonIds();
        //
        // fetchData(
        //     "frohub/get_availibility",
        //     (response) => {
        //         if (response.success) {
        //             setAvailabilityData(response.data.availability);
        //             setBookingNotice(response.data.booking_notice);
        //             setMaxDate(response.data.max_date);
        //         } else {
        //             console.error("Error fetching availability:", response.message);
        //         }
        //         setLoading(false);
        //     },
        //     {
        //         product_id: productId,
        //         date: newDate,
        //         addons_id: addonIds
        //     }
        // );
    };

    return (
        <div className="relative">
            <FhCalender
                data={availabilityData}
                onDateChange={handleDateChange}
                bookingNotice={bookingNotice}
                maxDate={maxDate}
                unavailableDates={unavailableDates}
                unavailableTimes={unavailableTimes}
                nextAvailableDate={nextAvailableDate}
            />
        </div>
    );
};

export default FrohubCalender;
