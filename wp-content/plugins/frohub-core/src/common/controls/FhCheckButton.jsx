import React from "react";
import { Skeleton } from "antd";

const FhCheckButton = ({ addOn, selectedAddOns, handleSelectAddOn, loading }) => {
    // Show Skeleton when loading
    if (loading) {
        return (
            <div className="px-4 py-2 border rounded-full">
                <Skeleton.Input style={{ width: 150 }} active />
            </div>
        );
    }

    if (!addOn) return null; // Ensure addOn exists

    return (
        <label
            className={`inline-flex items-center cursor-pointer px-4 py-2 border rounded-full ${
                selectedAddOns.includes(addOn) ? "bg-gray-300 border-gray-500" : "border-gray-300"
            }`}
        >
            <input
                type="checkbox"
                className="hidden"
                checked={selectedAddOns.includes(addOn)}
                onChange={() => handleSelectAddOn(addOn)}
                data-add-on-name={addOn.name}
                data-add-on-id={addOn.id}
                data-price={addOn.price}
                data-duration={addOn.duration_minutes}
            />
            <span className="text-gray-700 text-sm">
                {addOn.name} + £{addOn.price} ({addOn.duration_minutes} mins)
            </span>
        </label>
    );
};

export default FhCheckButton;