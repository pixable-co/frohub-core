import { useEffect, useState } from "react";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";

const FrohubCalender = () => {
    const [productId, setProductId] = useState(null);
    const [availabilityData, setAvailabilityData] = useState([]);
    const [selectedDate, setSelectedDate] = useState(null);

    useEffect(() => {
        const productElement = document.querySelector(".frohub_add_to_cart");
        if (productElement) {
            const productId = productElement.dataset.productId;
            setProductId(productId);
        }
    }, []);

    useEffect(() => {
        if (!productId || !selectedDate) return;

        fetchData(
            "frohub/get_availibility",
            (response) => {
                if (response.success) {
                    // console.log("Availability Data:", response.data.availability);
                    // console.log("Booked:", response.data.booked_slots);
                    setAvailabilityData(response.data.availability);
                } else {
                    console.error("Error fetching availability:", response.message);
                }
            },
            { product_id: productId, date: selectedDate } // Include date
        );
    }, [productId, selectedDate]);

    return (
        <div>
            <FhCalender data={availabilityData} onDateChange={setSelectedDate} />
        </div>
    );
};

export default FrohubCalender;
