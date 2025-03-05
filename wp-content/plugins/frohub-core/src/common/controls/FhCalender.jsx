import React, { useState, useEffect } from "react";
import dayjs from "dayjs";
import { Skeleton } from "antd";
import frohubStore from "../../frohubStore.js";
import './style.css';

const FhCalender = ({ onDateChange,bookingNotice,initialServiceDuration }) => {
    const { availabilityData, loading } = frohubStore(); // ✅ Get state from Zustand
    const [selectedDate, setSelectedDate] = useState(dayjs());
    const [selectedTime, setSelectedTime] = useState(null);
    const [selectedPrice, setSelectedPrice] = useState(0); // ✅ Track extra charge
    const [selectedDuration, setSelectedDuration] = useState(0); // ✅ Track total duration time
    const [currentMonth, setCurrentMonth] = useState(dayjs());

    useEffect(() => {
        const duration = parseInt(initialServiceDuration, 10) || 0;

        if (duration > 0) {
            const hours = Math.floor(duration / 60); // ✅ Get whole hours
            const minutes = duration % 60; // ✅ Get remaining minutes
            setSelectedDuration(`${hours}h ${minutes}m`);
        } else {
            setSelectedDuration("0h 0m"); // ✅ Default if invalid
        }
    }, [initialServiceDuration]); // ✅ Runs when `initialServiceDuration` updates


    // Extract unique available days from availabilityData
    const getAvailableDays = () => {
        if (!Array.isArray(availabilityData)) return [];
        return [...new Set(availabilityData.map((entry) => entry.day))]; // Extract unique days
    };

    const isAvailableDate = (date) => {
        const availableDays = getAvailableDays();
        const today = dayjs();

        // const bookingNoticeDays = availabilityData?.booking_notice || 0;
        const bookingNoticeDays = bookingNotice;
        const noticeCutoffDate = today.add(bookingNoticeDays, "day"); // ✅ Calculate cutoff date

        // ✅ Check if the day is available AND meets the booking notice requirement
        return availableDays.includes(date.format("dddd")) && date.isAfter(noticeCutoffDate, "day");
    };

    const getDaysInMonth = () => {
        const firstDayOfMonth = currentMonth.startOf("month");
        const daysInMonth = currentMonth.daysInMonth();
        let startingDay = firstDayOfMonth.day(); // Get day index (0 = Sunday, 6 = Saturday)

        // Adjust to start on Monday (if Sunday, set it to 6, otherwise subtract 1)
        startingDay = startingDay === 0 ? 6 : startingDay - 1;

        const calendarDays = [];

        // Fill in days from the previous month
        for (let i = 0; i < startingDay; i++) {
            calendarDays.push({
                date: firstDayOfMonth.subtract(startingDay - i, "day"),
                isCurrentMonth: false,
            });
        }

        // Fill in current month's days
        for (let i = 1; i <= daysInMonth; i++) {
            calendarDays.push({
                date: firstDayOfMonth.add(i - 1, "day"),
                isCurrentMonth: true,
            });
        }

        return calendarDays;
    };

    const handleSelect = (date) => {
        if (!isAvailableDate(date)) return; // Prevent selecting disabled days

        const newDate = dayjs(date);
        setSelectedDate(newDate);
        setSelectedTime(null);
        setSelectedPrice(0); // ✅ Reset extra charge
        // setSelectedDuration(0);
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", "0");

        if (onDateChange) {
            onDateChange(newDate.format("YYYY-MM-DD"));
        }
    };

    const handleTimeSelect = (time, price, duration) => {
        setSelectedTime(time);
        setSelectedPrice(price);
        // setSelectedDuration(duration); // ✅ Store duration
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", price);
    };

    const navigateMonth = (direction) => {
        setCurrentMonth(currentMonth.add(direction, "month"));
    };

    const availableTimeSlots = Array.isArray(availabilityData)
        ? availabilityData
            .filter((entry) => entry.day === selectedDate.format("dddd") && isAvailableDate(selectedDate)) // ✅ Ensure date is valid
            .map((entry) => ({
                time: `${entry.from} - ${entry.to}`,
                price: Number(entry.extra_charge) || 0,
                duration: entry.total_duration_minutes || 0,
            }))
        : [];

    const isToday = (date) => dayjs().format("YYYY-MM-DD") === date.format("YYYY-MM-DD");
    const isSelected = (date) => selectedDate.format("YYYY-MM-DD") === date.format("YYYY-MM-DD");
    const isPastDate = (date) => date.isBefore(dayjs(), "day");

    return (
        <div className="calendar-container">
            <div className="calendar-section custom-calendar">
                <div className="flex items-center justify-between mb-4">
                    <button onClick={() => navigateMonth(-1)} className="p-2 hover:bg-gray-200 rounded-full">&lt;</button>
                    <div className="text-base font-medium">{currentMonth.format("MMMM YYYY")}</div>
                    <button onClick={() => navigateMonth(1)} className="p-2 hover:bg-gray-200 rounded-full">&gt;</button>
                </div>

                {/* Days of the week - Starts from Monday */}
                <div className="grid grid-cols-7 mb-2">
                    {["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"].map((day) => (
                        <div key={day} className="text-xs text-gray-500 text-center py-2">{day}</div>
                    ))}
                </div>

                {/* Calendar Days */}
                <div className="grid grid-cols-7 gap-1">
                    {getDaysInMonth().map((dayObj, idx) => {
                        const isAvailable = isAvailableDate(dayObj.date);
                        const isDisabled = !isAvailable || isPastDate(dayObj.date);

                        return (
                            <button
                                key={idx}
                                onClick={() => isAvailable && handleSelect(dayObj.date)}
                                disabled={isDisabled}
                                className={`
                                    w-10 h-10 flex items-center justify-center rounded-full
                                    text-sm font-medium
                                    ${!dayObj.isCurrentMonth ? "text-gray-400" : ""}
                                    ${isDisabled ? "text-gray-300 cursor-not-allowed" : "hover:bg-orange-300"}
                                    ${isAvailable ? "bg-orange-500 !text-white" : ""}
                                    ${isSelected(dayObj.date) ? "bg-black text-white hover:bg-black" : ""}
                                    ${isToday(dayObj.date) && !isSelected(dayObj.date) ? "border border-black" : ""}
                                `}
                            >
                                {dayObj.date.date()}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Time Slots Section */}
            <div className="timeslots-section">
                <h3 className="selected-date">{selectedDate.format("ddd, MMM D YYYY")}</h3>

                {loading ? (
                    <div className="timeslots-grid">
                        {[...Array(4)].map((_, index) => (
                            <Skeleton.Button key={index} active size="large" block />
                        ))}
                    </div>
                ) : availableTimeSlots.length === 0 ? (
                    <p className="no-slots-message">No available time slots for this day.</p>
                ) : (
                    <div className="timeslots-grid">
                        {availableTimeSlots.map((slot, index) => (
                            <button
                                key={index}
                                className={`timeslot-button ${selectedTime === slot.time ? "selected" : ""}`}
                                onClick={() => handleTimeSelect(slot.time, slot.price, slot.duration)}
                            >
                                <span>{slot.time}</span>
                                {slot.price > 0 && <span className="extra-charge">+£{slot.price.toFixed(2)}</span>}
                            </button>
                        ))}
                    </div>
                )}
                <p className="timezone-info">All times are in London (GMT +01:00)</p>
            </div>

            {/* ✅ Hidden Inputs for Storing Selected Values */}
            <div id="extra-charge-container" data-extra-charge="0" style={{ display: "none" }}></div>
            <input type="hidden" value={selectedDate.format("YYYY-MM-DD")} name="selectedDate" />
            <input type="hidden" value={selectedTime || ""} name="selectedTime" />
            <input type="hidden" value={selectedPrice} name="selectedPrice" />
            <input type="hidden" value={selectedDuration} name="total_duration_time" /> {/* ✅ Store duration */}
        </div>
    );
};

export default FhCalender;
