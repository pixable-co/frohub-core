import { useEffect, useState } from "react";
import FhCalender from "../../common/controls/FhCalender.jsx";
import { fetchData } from "../../services/fetchData.js";

const FrohubCalender = () => {
    const [productId, setProductId] = useState(null);
    const [availabilityData, setAvailabilityData] = useState([]); // Store fetched data

    useEffect(() => {
        const productElement = document.querySelector(".frohub_add_to_cart");
        if (productElement) {
            const productId = productElement.dataset.productId;
            setProductId(productId);
        }
    }, []);

    useEffect(() => {
        if (!productId) return;

        fetchData("frohub/get_availibility", (response) => {
            if (response.success) {
                console.log("Availability Data:", response.data.availability);
                setAvailabilityData(response.data.availability); // Store fetched data
            } else {
                console.error("Error fetching availability:", response.message);
            }
        }, { product_id: productId });

    }, [productId]);

    return (
        <div>
            <FhCalender data={availabilityData} /> {/* Pass availability data as props */}
        </div>
    );
};

export default FrohubCalender;
