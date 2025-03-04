import { useState, useEffect } from "react";
import { CheckCircle, XCircle, RefreshCw } from "lucide-react";
import { Skeleton } from "antd";
import frohubStore from "../frohubStore.js";
import { getLocationDataFromCookie } from "../utils/locationUtils.js"; // Import the cookie function
import {fetchData} from "../services/fetchData.js";

const GOOGLE_MAPS_API_KEY = "AIzaSyA_myRdC3Q1OUQBmZ22dxxd3rGtwrVC1sI";

export default function MobileService({ partnerId }) {
    const [postcode, setPostcode] = useState("");
    const [isValid, setIsValid] = useState(false);
    const [travelFee, setTravelFee] = useState(null);
    const [partnerLocation, setPartnerLocation] = useState(null);
    const [loading, setLoading] = useState(false);
    const [loadingPartner, setLoadingPartner] = useState(true);
    const [error, setError] = useState("");
    const [staticLocation, setStaticLocation] = useState(null);

    const { setMobileTravelFee, setReadyForMobile } = frohubStore();

    useEffect(() => {
        // Get static location data from cookie using the imported function
        const getStaticLocation = () => {
            const cookieData = getLocationDataFromCookie();

            // If data exists in cookie, use it
            if (cookieData && cookieData.lat && cookieData.lng) {
                setStaticLocation({
                    latitude: parseFloat(cookieData.lat),
                    longitude: parseFloat(cookieData.lng),
                    name: cookieData.location_name
                });
            }
        };

        getStaticLocation();

        if (partnerId) {
            fetchPartnerLocation();
        }
    }, [partnerId]);

    // Fetch the partner's location and radius pricing
    // Fetch the partner's location and radius pricing using AJAX
    const fetchPartnerLocation = () => {
        setLoadingPartner(true);
        setError("");

        fetchData(
            "frohub/get_mobile_location_data", // Match your AJAX action in PHP
            (response) => {
                console.log(response)
                if (response.success) {
                    setPartnerLocation({
                        latitude: parseFloat(response.data.data.latitude),
                        longitude: parseFloat(response.data.data.longitude),
                        radiusFees: response.data.data.radius_fees.map((fee) => ({
                            radius: parseFloat(fee.radius),
                            price: parseFloat(fee.price),
                        })),
                    });
                } else {
                    setError("Failed to fetch partner location.");
                    console.error("Error fetching location:", response.message);
                }
                setLoadingPartner(false);
            },
            {
                partner_id: partnerId,
            }
        );
    };
    // const fetchPartnerLocation = async () => {
    //     try {
    //         const API_URL = `/wp-json/frohub/v1/get-location-data/${partnerId}`;
    //         const response = await fetch(API_URL);
    //         const data = await response.json();
    //
    //         if (data.success) {
    //             setPartnerLocation({
    //                 latitude: parseFloat(data.data.latitude),
    //                 longitude: parseFloat(data.data.longitude),
    //                 radiusFees: data.data.radius_fees.map((fee) => ({
    //                     radius: parseFloat(fee.radius),
    //                     price: parseFloat(fee.price),
    //                 })),
    //             });
    //         } else {
    //             setError("Failed to fetch partner location.");
    //         }
    //     } catch (err) {
    //         setError("Error fetching location data.");
    //         console.error(err);
    //     } finally {
    //         setLoadingPartner(false);
    //     }
    // };

    useEffect(() => {
        // When both partner location and static location are loaded, calculate fee automatically
        if (partnerLocation && staticLocation && !loadingPartner) {
            calculateTravelFeeForStatic();
        }
    }, [partnerLocation, staticLocation, loadingPartner]);

    // Get latitude & longitude of the postcode using Google Maps API
    const getCoordinatesFromPostcode = async (postcode) => {
        try {
            const response = await fetch(
                `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(postcode)}&key=${GOOGLE_MAPS_API_KEY}`
            );
            const data = await response.json();
            if (data.status === "OK") {
                const location = data.results[0].geometry.location;
                return { latitude: location.lat, longitude: location.lng };
            }
            throw new Error("Invalid postcode");
            frohubStore.setState((state) => ({ readyForMobile: false }));
        } catch (err) {
            console.error("Error fetching coordinates:", err);
            return null;
        }
    };

    // Calculate distance between two coordinates (Haversine formula)
    const calculateDistance = (lat1, lon1, lat2, lon2) => {
        const R = 6371; // Radius of Earth in km
        const dLat = ((lat2 - lat1) * Math.PI) / 180;
        const dLon = ((lon2 - lon1) * Math.PI) / 180;
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos((lat1 * Math.PI) / 180) *
            Math.cos((lat2 * Math.PI) / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c; // Distance in km
    };

    // Calculate fee with static location data
    const calculateTravelFeeForStatic = () => {
        setTravelFee(null);
        setIsValid(false);
        setError("");
        setLoading(true);

        if (!partnerLocation) {
            setError("Partner location not loaded.");
            setLoading(false);
            return;
        }

        const distance = calculateDistance(
            partnerLocation.latitude,
            partnerLocation.longitude,
            staticLocation.latitude,
            staticLocation.longitude
        );

        // Find the applicable price based on radius
        let applicablePrice = null;
        for (const fee of partnerLocation.radiusFees) {
            if (distance <= fee.radius) {
                applicablePrice = fee.price;
                break;
            }
        }

        if (applicablePrice !== null) {
            setIsValid(true);
            setTravelFee(applicablePrice);
            setMobileTravelFee(applicablePrice);
            frohubStore.setState((state) => ({ readyForMobile: true }));
        } else {
            setIsValid(false);
            setError("Sorry, you are outside the service area.");
            setMobileTravelFee(0);
            frohubStore.setState((state) => ({ readyForMobile: false }));
        }

        setLoading(false);
    };

    // Handle postcode validation and price calculation
    const handleCheckPostcode = async (value) => {
        setPostcode(value);

        // If postcode is empty, clear everything and don't show validation messages
        if (!value.trim()) {
            setTravelFee(null);
            setIsValid(false);
            setError("");
            setMobileTravelFee(0);
            setLoading(false);
            frohubStore.setState((state) => ({ readyForMobile: false }));
            return;
        }
        setTravelFee(null);
        setIsValid(false);
        setError("");
        setLoading(true);

        if (!partnerLocation) {
            setError("Partner location not loaded.");
            setLoading(false);
            return;
        }

        const userLocation = await getCoordinatesFromPostcode(value);
        if (!userLocation) {
            setError("Invalid postcode.");
            setLoading(false);
            frohubStore.setState((state) => ({ readyForMobile: false }));
            return;
        }

        const distance = calculateDistance(
            partnerLocation.latitude,
            partnerLocation.longitude,
            userLocation.latitude,
            userLocation.longitude
        );

        // Find the applicable price based on radius
        let applicablePrice = null;
        for (const fee of partnerLocation.radiusFees) {
            if (distance <= fee.radius) {
                applicablePrice = fee.price;
                break;
            }
        }

        if (applicablePrice !== null) {
            setIsValid(true);
            setTravelFee(applicablePrice);
            setMobileTravelFee(applicablePrice);
            frohubStore.setState((state) => ({ readyForMobile: true }));
        } else {
            setIsValid(false);
            setError("Sorry, you are outside the service area.");
            setMobileTravelFee(0);
            frohubStore.setState((state) => ({ readyForMobile: false }));
        }

        setLoading(false);
    };

    // Show Skeleton only while fetching partner location
    if (loadingPartner) {
        return (
            <div className="mt-4 mb-6 p-4 border border-gray-300 rounded-lg bg-white">
                <Skeleton.Button block active className="h-10" />
                <Skeleton.Input active className="w-full h-6 mb-3" />
            </div>
        );
    }

    return (
        <div className="mt-4 mb-6 p-4 border border-gray-300 rounded-lg bg-white">
            {!staticLocation ? (
                // Original UI with postcode input
                <>
                    <p className="text-sm text-gray-700">
                        To check if you are within their mobile service area, enter your postcode.
                    </p>
                    <div className="flex justify-start items-center gap-6 mt-3">
                        <div>
                            <input
                                type="text"
                                placeholder="Enter postcode"
                                value={postcode}
                                onChange={(e) => handleCheckPostcode(e.target.value)}
                                className="w-full px-4 py-2 text-gray-600 border rounded-md bg-gray-100 border-gray-300 focus:ring focus:ring-indigo-300 focus:outline-none"
                            />
                        </div>

                        <div>
                            {/* Loading Indicator for postcode check */}
                            {loading && <p className="text-sm text-gray-500 mt-2">Checking postcode...</p>}

                            {/* Success Message */}
                            {isValid && travelFee !== null && (
                                <div className="flex items-center text-green-600 font-semibold mt-2">
                                    <CheckCircle className="w-5 h-5 mr-1" />
                                    You are inside the service area.
                                </div>
                            )}

                            {/* Error Message */}
                            {error && (
                                <div className="flex items-center text-red-500 font-semibold mt-2">
                                    <XCircle className="w-5 h-5 mr-1" />
                                    {error}
                                </div>
                            )}
                        </div>
                    </div>
                </>
            ) : (
                // Show static location info and results
                <>
                    <div className="font-medium mt-2 mb-3">
                        {staticLocation.name}
                    </div>

                    <div>
                        {/* Loading Indicator for calculation */}
                        {loading && <p className="text-sm text-gray-500 mt-2">Checking availability...</p>}

                        {/* Success Message */}
                        {isValid && travelFee !== null && (
                            <div className="flex items-center text-green-600 font-semibold mt-2">
                                <CheckCircle className="w-5 h-5 mr-1" />
                                You are inside the service area.
                            </div>
                        )}

                        {/* Error Message */}
                        {error && (
                            <div className="flex items-center text-red-500 font-semibold mt-2">
                                <XCircle className="w-5 h-5 mr-1" />
                                {error}
                            </div>
                        )}
                    </div>
                </>
            )}

            {/* Travel Fee Display - Same for both static and dynamic location */}
            {isValid && travelFee !== null && (
                <p className="!mt-4 text-lg font-semibold">
                    Mobile Travel Fee: <span className="text-gray-900">+£{travelFee.toFixed(2)}</span>
                </p>
            )}
            {/* Reset Button to clear location cookie */}
            {staticLocation && (
                <button
                    onClick={() => {
                        // Clear the location cookie
                        document.cookie = "frohub_location=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                        // Reset the state
                        setStaticLocation(null);
                        setTravelFee(null);
                        setIsValid(false);
                    }}
                    className="flex items-center gap-1 text-sm text-red-600 hover:text-red-800 mt-2"
                >
                    <RefreshCw className="w-4 h-4" />
                    Reset Location
                </button>
            )}
        </div>
    );
}