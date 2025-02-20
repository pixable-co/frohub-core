// ✅ Function to store location data in a cookie
export function storeLocationData() {
    const urlParams = new URLSearchParams(window.location.search);
    const radius = urlParams.get("radius");
    const locationName = urlParams.get("location_name");
    const lat = urlParams.get("lat");
    const lng = urlParams.get("lng");

    if (radius && locationName && lat && lng) {
        const locationData = {
            radius: radius,
            location_name: decodeURIComponent(locationName),
            lat: lat,
            lng: lng
        };

        // ✅ Store in a single cookie (no expiration date)
        document.cookie = `locationData=${encodeURIComponent(JSON.stringify(locationData))}; path=/`;
    }
}

// ✅ Function to retrieve location data from the cookie
export function getLocationDataFromCookie() {
    const match = document.cookie.match(new RegExp("(^| )locationData=([^;]+)"));
    return match ? JSON.parse(decodeURIComponent(match[2])) : null;
}

// ✅ Function to clear the location data cookie
export function clearLocationData() {
    document.cookie = "locationData=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
}
