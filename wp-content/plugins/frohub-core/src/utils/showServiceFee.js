export function showServiceFee() {
    const serviceFee = getCookie("frohub_service_fee"); // ✅ Read service fee from document cookies

    if (!serviceFee) return; // ✅ Exit if there's no service fee

    function insertServiceFee() {
        const orderSummaryTotals = document.querySelector(".wc-block-components-product-metadata");

        if (orderSummaryTotals) {
            console.log("Order Summary Totals found:", orderSummaryTotals);

            // ✅ Create a new service fee row with Tailwind styling
            const serviceFeeElement = document.createElement("div");
            serviceFeeElement.classList.add("frohub-service-fee", "flex", "justify-between", "py-6", "text-sm", "font-medium");

            serviceFeeElement.innerHTML = `
                <div class="bg-orange-500 p-2 rounded-md text-white flex justify-between w-full">
                    <span>Frohub Service Fee:</span>
                    <span class="font-semibold">£${parseFloat(serviceFee).toFixed(2)}</span>
                </div>
            `;

            // ✅ Insert service fee after the order summary
            orderSummaryTotals.insertAdjacentElement("afterend", serviceFeeElement);

            // ✅ Stop observing once inserted
            observer.disconnect();
        }
    }

    // ✅ MutationObserver to detect when WooCommerce loads the totals section
    const observer = new MutationObserver(() => insertServiceFee());
    observer.observe(document.body, { childList: true, subtree: true });

    // ✅ Run immediately in case the element is already available
    insertServiceFee();
}

// ✅ Function to get cookie value
function getCookie(name) {
    const cookies = document.cookie.split("; ");
    for (let cookie of cookies) {
        const [key, value] = cookie.split("=");
        if (key === name) return decodeURIComponent(value);
    }
    return null;
}
