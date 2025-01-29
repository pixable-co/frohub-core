import React, { useState } from "react";
import dayjs from "dayjs";
import './style.css'

const FhCalender = ({ data = [] }) => {
    const [selectedDate, setSelectedDate] = useState(dayjs());
    const [selectedTime, setSelectedTime] = useState(null);
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
        setSelectedDate(date);
        setSelectedTime(null);
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", "0");
    };

    const handleTimeSelect = (time, price) => {
        setSelectedTime(time);
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", price);
    };

    const navigateMonth = (direction) => {
        setCurrentMonth(currentMonth.add(direction, "month"));
    };

    const availableTimeSlots = Array.isArray(data)
        ? data
            .filter((entry) => entry.day === selectedDate.format("dddd"))
            .map((entry) => ({
                time: `${entry.from} - ${entry.to}`, // Now correctly showing "From - To"
                price: Number(entry.extra_charge) || 0,
            }))
        : [];

    const isToday = (date) => dayjs().format("YYYY-MM-DD") === date.format("YYYY-MM-DD");
    const isSelected = (date) => selectedDate.format("YYYY-MM-DD") === date.format("YYYY-MM-DD");
    const isPastDate = (date) => date.isBefore(dayjs(), "day");

    return (
        <div className="calendar-container">
            {/* Calendar Section */}
            <div className="calendar-section custom-calendar">
                <div className="flex items-center justify-between mb-4">
                    <button
                        onClick={() => navigateMonth(-1)}
                        className="p-2 hover:bg-gray-200 rounded-full"
                    >
                        &lt;
                    </button>
                    <h2 className="text-base font-medium">{currentMonth.format("MMMM YYYY")}</h2>
                    <button
                        onClick={() => navigateMonth(1)}
                        className="p-2 hover:bg-gray-200 rounded-full"
                    >
                        &gt;
                    </button>
                </div>

                {/* Days of the Week */}
                <div className="grid grid-cols-7 mb-2">
                    {["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"].map((day) => (
                        <div key={day} className="text-xs text-gray-500 text-center py-2">
                            {day}
                        </div>
                    ))}
                </div>

                {/* Calendar Grid */}
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

            {/* Time Slots Section */}
            <div className="timeslots-section">
                <h3 className="selected-date">{selectedDate.format("ddd, MMM D YYYY")}</h3>

                {availableTimeSlots.length > 0 ? (
                    <div className="timeslots-grid">
                        {availableTimeSlots.map((slot, index) => (
                            <button
                                key={index}
                                className={`timeslot-button ${
                                    selectedTime === slot.time ? "selected" : ""
                                }`}
                                onClick={() => handleTimeSelect(slot.time, slot.price)}
                            >
                                <span>{slot.time}</span> {/* Shows "From - To" time */}
                                {slot.price > 0 && <span className="extra-charge">+£{slot.price.toFixed(2)}</span>}
                            </button>
                        ))}
                    </div>
                ) : (
                    <p className="no-slots">No available slots for this date.</p>
                )}
                <p className="timezone-info">All times are in London (GMT +01:00)</p>
            </div>

            {/* Hidden div to store extra charge */}
            <div id="extra-charge-container" data-extra-charge="0" style={{ display: "none" }}></div>
        </div>
    );
};

export default FhCalender;
