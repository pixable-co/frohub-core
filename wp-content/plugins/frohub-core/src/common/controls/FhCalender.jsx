import React, { useState, useEffect } from "react";
import dayjs from "dayjs";
import isBetween from "dayjs/plugin/isBetween";
dayjs.extend(isBetween);
import { Skeleton } from "antd";
import frohubStore from "../../frohubStore.js";
import './style.css';

const FhCalender = ({ onDateChange, bookingNotice, initialServiceDuration, maxDate, unavailableDates, unavailableTimes, nextAvailableDate, allBookings }) => {
    const { availabilityData, loading } = frohubStore();
    const [selectedDate, setSelectedDate] = useState(() => dayjs(nextAvailableDate));
    const [selectedTime, setSelectedTime] = useState(null);
    const [selectedPrice, setSelectedPrice] = useState(0);
    const [selectedDuration, setSelectedDuration] = useState(0);
    const [currentMonth, setCurrentMonth] = useState(dayjs());

    useEffect(() => {
        if (nextAvailableDate) {
            setSelectedDate(dayjs(nextAvailableDate));
        }
    }, [nextAvailableDate]);

    useEffect(() => {
        const duration = parseInt(initialServiceDuration, 10) || 0;
        const hours = Math.floor(duration / 60);
        const minutes = duration % 60;
        setSelectedDuration(`${hours}h ${minutes}m`);
    }, [initialServiceDuration]);

    const isAvailableDate = (date) => {
        const formattedDate = date.format("YYYY-MM-DD");

        const possibleSlots = availabilityData.filter(entry => entry.date === formattedDate);
        if (possibleSlots.length === 0) return false;

        const bookedSlotsForDate = allBookings[formattedDate] || [];

        const freeSlots = possibleSlots.filter(entry => {
            const slotTime = `${entry.from} - ${entry.to}`;

            const isBooked = bookedSlotsForDate.includes(slotTime);
            if (isBooked) return false;

            const slotStart = dayjs(`${formattedDate} ${entry.from}`, "YYYY-MM-DD HH:mm");
            const slotEnd = dayjs(`${formattedDate} ${entry.to}`, "YYYY-MM-DD HH:mm");

            const isBlocked = unavailableTimes.some(({ start, end }) => {
                if (!start || !end) return false;
                return (
                    slotStart.isBetween(start, end, null, "[]") ||
                    slotEnd.isBetween(start, end, null, "[]") ||
                    (slotStart.isBefore(start) && slotEnd.isAfter(end))
                );
            });

            return !isBlocked;
        });

        console.log(`📅 Date: ${formattedDate} - Possible Slots: ${possibleSlots.length}, Free Slots: ${freeSlots.length}`);

        if (freeSlots.length === 0) return false;

        const today = dayjs();
        const noticeCutoffDate = today.add(bookingNotice, "day");
        if (!date.isAfter(noticeCutoffDate, "day")) return false;

        if (maxDate && !date.isBefore(dayjs(maxDate).add(1, "day"))) return false;

        return true;
    };

    const getDaysInMonth = () => {
        const firstDayOfMonth = currentMonth.startOf("month");
        const daysInMonth = currentMonth.daysInMonth();
        let startingDay = firstDayOfMonth.day();
        startingDay = startingDay === 0 ? 6 : startingDay - 1;

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
        if (!isAvailableDate(date)) return;

        const newDate = dayjs(date);
        setSelectedDate(newDate);
        setSelectedTime(null);
        setSelectedPrice(0);
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", "0");

        if (onDateChange) {
            onDateChange(newDate.format("YYYY-MM-DD"));
        }
    };

    const handleTimeSelect = (time, price, duration) => {
        setSelectedTime(time);
        setSelectedPrice(price);
        document.getElementById("extra-charge-container").setAttribute("data-extra-charge", price);
    };

    const navigateMonth = (direction) => {
        setCurrentMonth(currentMonth.add(direction, "month"));
    };

    const availableTimeSlots = Array.isArray(availabilityData)
        ? availabilityData
            .filter(entry => {
                const formattedDate = selectedDate.format("YYYY-MM-DD");
                if (entry.date !== formattedDate) return false;

                const slotTime = `${entry.from} - ${entry.to}`;
                const bookedSlotsForDate = allBookings[formattedDate] || [];
                // if (bookedSlotsForDate.includes(slotTime)) return false;

                const overlapsBooking = bookedSlotsForDate.some(timeStr => {
                    if (!timeStr || !timeStr.includes(" - ")) return false;

                    const [bookedFrom, bookedTo] = timeStr.split(" - ");
                    const bookedStart = dayjs(`${formattedDate} ${bookedFrom}`, "YYYY-MM-DD HH:mm");
                    const bookedEnd = dayjs(`${formattedDate} ${bookedTo}`, "YYYY-MM-DD HH:mm");

                    const slotStart = dayjs(`${formattedDate} ${entry.from}`, "YYYY-MM-DD HH:mm");
                    const slotEnd = dayjs(`${formattedDate} ${entry.to}`, "YYYY-MM-DD HH:mm");

                    return slotStart.isBefore(bookedEnd) && slotEnd.isAfter(bookedStart);
                });
                if (overlapsBooking) return false;

                const slotStart = dayjs(`${formattedDate} ${entry.from}`, "YYYY-MM-DD HH:mm");
                const slotEnd = dayjs(`${formattedDate} ${entry.to}`, "YYYY-MM-DD HH:mm");

                const isBlocked = unavailableTimes.some(({ start, end }) => {
                    if (!start || !end) return false;
                    return (
                        slotStart.isBetween(start, end, null, "[]") ||
                        slotEnd.isBetween(start, end, null, "[]") ||
                        (slotStart.isBefore(start) && slotEnd.isAfter(end))
                    );
                });

                return !isBlocked;
            })
            .map(entry => ({
                time: `${entry.from} - ${entry.to}`,
                price: Number(entry.extra_charge) || 0,
                duration: entry.total_duration_minutes || 0,
            }))
        : [];

    console.log(unavailableTimes);

    // console.log(`🟢 Selected Date: ${selectedDate.format("YYYY-MM-DD")} - Available Time Slots:`, availableTimeSlots);

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
                    {["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"].map(day => (
                        <div key={day} className="text-xs text-gray-500 text-center py-2">{day}</div>
                    ))}
                </div>

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

            <div className="timeslots-section">
                {loading ? (
                    <>
                        <h3 className="selected-date">
                            {dayjs(selectedDate).isValid() ? selectedDate.format("ddd, MMM D YYYY") : "Getting Available Date..."}
                        </h3>
                        <div className="timeslots-grid">
                            {[...Array(4)].map((_, index) => (
                                <Skeleton.Button key={index} active size="large" block />
                            ))}
                        </div>
                    </>
                ) : availableTimeSlots.length === 0 ? (
                    <h3 className="selected-date">Sorry, no available time for this service</h3>
                ) : (
                    <>
                        <h3 className="selected-date">{selectedDate.format("ddd, MMM D YYYY")}</h3>
                        <div className="timeslots-grid scrollable-timeslots">
                            {availableTimeSlots.map((slot, index) => (
                                <button
                                    key={index}
                                    className={`timeslot-button ${selectedTime === slot.time ? "selected" : ""}`}
                                    onClick={() => handleTimeSelect(slot.time, slot.price, slot.duration)}
                                >
                                    {/*<span>{slot.time}</span>*/}
                                    <span>{slot.time.split(" - ")[0]}</span>
                                    {slot.price > 0 && <span className="extra-charge">+£{slot.price.toFixed(2)}</span>}
                                </button>
                            ))}
                        </div>
                    </>
                )}
                <p className="timezone-info">All times are in London (GMT +01:00)</p>
            </div>

            <div id="extra-charge-container" data-extra-charge="0" style={{ display: "none" }}></div>
            <input type="hidden" value={selectedDate.format("YYYY-MM-DD")} name="selectedDate" />
            <input type="hidden" value={selectedTime || ""} name="selectedTime" />
            <input type="hidden" name="selectedPrice" value={selectedPrice} />
            <input type="hidden" value={selectedDuration} name="total_duration_time" />
        </div>
    );
};

export default FhCalender;
