import React, { useState } from "react";
import { Calendar } from "antd";
import dayjs from "dayjs";

const FhCalendar = () => {
    const [selectedDate, setSelectedDate] = useState(dayjs());
    const [selectedTime, setSelectedTime] = useState(null); // Allow only one selection

    // Available time slots
    const timeSlots = [
        { time: "10:00", price: 0 },
        { time: "14:00", price: 0 },
        { time: "15:00", price: 0 },
        { time: "16:00", price: 0 },
        { time: "17:00", price: 20 }, // Extra charge
    ];

    // Handle date selection
    const handleSelect = (date) => {
        setSelectedDate(date);
        setSelectedTime(null); // Reset selected time when changing date
    };

    // Handle single time selection
    const handleTimeSelect = (time) => {
        setSelectedTime(time); // Only allow one selected time
    };

    return (
        <div className="flex flex-col md:flex-row gap-6 p-6 bg-white rounded-lg shadow-md">
            {/* Calendar Section */}
            <div className="border p-4 rounded-lg w-full md:w-1/2">
                <Calendar
                    fullscreen={false}
                    value={selectedDate}
                    onSelect={handleSelect}
                    disabledDate={(date) => date.isBefore(dayjs(), "day")} // Disable past dates
                />
            </div>

            {/* Time Slots Section */}
            <div className="w-full md:w-1/3">
                <h3 className="text-lg font-medium mb-2 underline">
                    {selectedDate.format("ddd, MMM D YYYY")}
                </h3>
                <div className="space-y-2">
                    {timeSlots.map((slot) => (
                        <button
                            key={slot.time}
                            className={`w-full border p-3 rounded-lg text-left transition cursor-pointer ${
                                selectedTime === slot.time
                                    ? "border-black bg-gray-200 shadow-md"
                                    : "border-gray-300 hover:bg-gray-50"
                            }`}
                            onClick={() => handleTimeSelect(slot.time)}
                        >
                            {slot.time} {slot.price > 0 && <span className="text-gray-500">+£{slot.price.toFixed(2)}</span>}
                        </button>
                    ))}
                </div>
                <p className="text-sm text-gray-500 mt-2">All times are in London (GMT +01:00)</p>
            </div>
        </div>
    );
};

export default FhCalendar;
