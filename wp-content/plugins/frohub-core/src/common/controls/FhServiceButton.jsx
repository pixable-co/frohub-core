import React from "react";
import { Skeleton } from "antd";
import { Home, Building, Car } from "lucide-react";

const iconMap = {
    home: <i className="fas fa-home text-[32px]"></i>,
    salon: <i className="fas fa-chair text-[32px]"></i>, // Use `fa-chair`
    mobile: <i className="fas fa-car-side text-[32px]"></i>,
};


const FhServiceButton = ({service, selectedService, handleSelectService, loading }) => {
    if (loading) {
        return (
            <div className="flex items-center justify-between gap-[1rem] w-full p-1 border border-gray-200 rounded-lg">
                <Skeleton.Avatar size={48} shape="circle" active />
                <div className="flex flex-col">
                    <Skeleton.Input style={{ width: 80, marginTop: 8 }} active />
                    <Skeleton.Input style={{ width: 80, marginTop: 4 }} active />
                </div>
            </div>
        );
    }

    if (!service) return null; // Ensure service exists

    const lowerCaseService = service.toLowerCase();
    const isSelected = selectedService?.toLowerCase() === lowerCaseService; // ✅ Case-insensitive check

    return (
        <div
            className={`flex items-center justify-center w-full p-1 border rounded-lg cursor-pointer transition-all duration-200 ${
                isSelected ? "bg-gray-200 border-gray-500" : "border-gray-300"
            }`}
            onClick={() => handleSelectService(lowerCaseService)} // ✅ Ensure service is passed in lowercase
        >
            {/* Hidden Radio Button */}
            <input
                type="radio"
                name="serviceType"
                value={lowerCaseService}
                checked={isSelected}
                onChange={() => handleSelectService(lowerCaseService)}
                className="hidden"
            />

            {/* Label for Selection */}
            <label className="flex items-center gap-[1rem] cursor-pointer w-full h-full p-1 rounded-lg transition-all duration-200">
                {iconMap[lowerCaseService] || <i className="fas fa-home text-[32px]"></i>}
                <div className="flex flex-col">
                    <span className="font-semibold text-lg mt-2">{service}</span>
                    <span className="text-sm text-gray-600">Select {service} service</span>
                </div>
            </label>
        </div>
    );
};

export default FhServiceButton;
