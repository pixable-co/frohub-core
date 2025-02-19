import React, { useState } from "react";
import dayjs from "dayjs";
import { Skeleton } from "antd";
import frohubStore from "../../frohubStore.js";
import './style.css';

const FhCalender = ({ onDateChange }) => {
    const { availabilityData, loading } = frohubStore(); // ✅ Get state from Zustand
    const [selectedDate, setSelectedDate] = useState(dayjs());
    const [selectedTime, setSelectedTime] = useState(null);
    const [selectedPrice, setSelectedPrice] = useState(0); // ✅ Track extra charge
    const [selectedDuration, setSelectedDuration] = useState(0); // ✅ Track total duration time
    const [currentMonth, setCurrentMonth] = useState(dayjs());

    const getDaysInMonth = () => {
        const firstDayOfMonth = currentMonth.startOf("month");
        const daysInMonth = currentMonth.daysInMonth();
        const startingDay = firstDayOfMonth.day();

        const calendarDays = [];

        for (let i = 0; i < startingDay; i++) {
            calendarDays.push({
                date: firstDayOfMonth.subtract(startingDay - i, "day"),
                isCurrentMonth: false,
            });
        }

        for (let i = 1; i <= daysInMonth; i++) {
            calendarDays.push({
                date: firstDayOfMonth.add(i - 1, "day"),
                isCurrentMonth: true,
            });
        }

        return calendarDays;
    };

    const handleSelect = (date) => {
        const newDate = dayjs(date);
        setSelectedDate(newDate);
        setSelectedTime(null);
        setSelectedPrice(0); // ✅ Reset extra charge
        setSelectedDuration(0);
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", "0");

        if (onDateChange) {
            onDateChange(newDate.format("YYYY-MM-DD"));
        }
    };

    const handleTimeSelect = (time, price, duration) => {
        setSelectedTime(time);
        setSelectedPrice(price);
        setSelectedDuration(duration); // ✅ Store duration
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", price);
    };

    const navigateMonth = (direction) => {
        setCurrentMonth(currentMonth.add(direction, "month"));
    };

    const availableTimeSlots = Array.isArray(availabilityData)
        ? availabilityData
            .filter((entry) => entry.day === selectedDate.format("dddd"))
            .map((entry) => ({
                time: `${entry.from} - ${entry.to}`,
                price: Number(entry.extra_charge) || 0,
                duration: entry.total_duration_minutes || 0, // ✅ Get total duration time
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

                <div className="grid grid-cols-7 mb-2">
                    {["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"].map((day) => (
                        <div key={day} className="text-xs text-gray-500 text-center py-2">{day}</div>
                    ))}
                </div>

                <div className="grid grid-cols-7 gap-1">
                    {getDaysInMonth().map((dayObj, idx) => (
                        <button
                            key={idx}
                            onClick={() => !isPastDate(dayObj.date) && handleSelect(dayObj.date)}
                            disabled={isPastDate(dayObj.date)}
                            className={`
                                w-10 h-10 flex items-center justify-center rounded-full
                                text-sm font-medium
                                ${!dayObj.isCurrentMonth ? "text-gray-400" : ""}
                                ${isPastDate(dayObj.date) ? "text-gray-300 cursor-not-allowed" : "hover:bg-gray-200"}
                                ${isSelected(dayObj.date) ? "bg-black text-white hover:bg-black" : ""}
                                ${isToday(dayObj.date) && !isSelected(dayObj.date) ? "border border-black" : ""}
                            `}
                        >
                            {dayObj.date.date()}
                        </button>
                    ))}
                </div>
            </div>

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
