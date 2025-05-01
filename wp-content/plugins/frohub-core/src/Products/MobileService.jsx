import React, { useState, useEffect, useRef } from "react";
import { CheckCircle, XCircle, RefreshCw } from "lucide-react";
import { Skeleton } from "antd";
import frohubStore from "../frohubStore.js";
import { getLocationDataFromCookie } from "../utils/locationUtils.js";
import {fetchData} from "../services/fetchData.js";
import {toastNotification} from "../utils/toastNotification.js";

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
    const { setMobileTravelFee, setReadyForMobile, totalService } = frohubStore();

    // New state for location suggestions
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const suggestionsRef = useRef(null);

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

        // Add click outside listener to close suggestions
        const handleClickOutside = (event) => {
            if (suggestionsRef.current && !suggestionsRef.current.contains(event.target)) {
                setShowSuggestions(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, [partnerId]);

    // Fetch the partner's location and radius pricing
    const fetchPartnerLocation = () => {
        setLoadingPartner(true);
        setError("");

        fetchData(
            "frohub/get_mobile_location_data",
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

    useEffect(() => {
        // When both partner location and static location are loaded, calculate fee automatically
        if (partnerLocation && staticLocation && !loadingPartner) {
            calculateTravelFeeForStatic();
        }
    }, [partnerLocation, staticLocation, loadingPartner]);

    // New function to fetch location suggestions
    const fetchLocationSuggestions = async (input) => {
        if (!input || input.length < 3) {
            setSuggestions([]);
            return;
        }

        try {
            const response = await fetch(
                `https://maps.googleapis.com/maps/api/place/autocomplete/json?input=${encodeURIComponent(input)}&types=geocode&components=country:uk&key=${GOOGLE_MAPS_API_KEY}`
            );
            const data = await response.json();

            if (data.status === "OK") {
                setSuggestions(data.predictions.map(prediction => ({
                    id: prediction.place_id,
                    description: prediction.description
                })));
                setShowSuggestions(true);
            } else {
                setSuggestions([]);
            }
        } catch (err) {
            console.error("Error fetching location suggestions:", err);
            setSuggestions([]);
        }
    };

    // Debounce search input
    useEffect(() => {
        const timer = setTimeout(() => {
            fetchLocationSuggestions(postcode);
        }, 300);

        return () => clearTimeout(timer);
    }, [postcode]);

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

    // Get details from place ID
    const getDetailsFromPlaceId = async (placeId) => {
        try {
            const response = await fetch(
                `https://maps.googleapis.com/maps/api/place/details/json?place_id=${placeId}&fields=geometry,formatted_address&key=${GOOGLE_MAPS_API_KEY}`
            );
            const data = await response.json();

            if (data.status === "OK") {
                const location = data.result.geometry.location;
                return {
                    latitude: location.lat,
                    longitude: location.lng,
                    address: data.result.formatted_address
                };
            }
            throw new Error("Invalid location");
        } catch (err) {
            console.error("Error fetching place details:", err);
            return null;
        }
    };

    // Handle suggestion selection
    const handleSelectSuggestion = async (suggestion) => {
        setPostcode(suggestion.description);
        setShowSuggestions(false);

        const locationDetails = await getDetailsFromPlaceId(suggestion.id);
        if (locationDetails) {
            handleCheckLocation(locationDetails);
        }
    };

    // Calculate distance between two coordinates (Haversine formula)
    const calculateDistance = (lat1, lon1, lat2, lon2) => {
        const R = 3958.8; // Radius of Earth in miles
        const dLat = ((lat2 - lat1) * Math.PI) / 180;
        const dLon = ((lon2 - lon1) * Math.PI) / 180;
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos((lat1 * Math.PI) / 180) *
            Math.cos((lat2 * Math.PI) / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c; // Distance in miles
    };

    // Calculate fee with static location data
    const calculateTravelFeeForStatic = () => {
        setTravelFee(null);
        setIsValid(false);
        setError("");
        setLoading(true);

        if (!partnerLocation) {
            setError("partner location not loaded.");
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
            if (totalService < 1) {
                setError("Oops! They don't cover your area. Try another stylist nearby.");
            }
            else {
                setError("Oops! Mobile's not available in your area, but you can still book at their home or salon");
            }
            setMobileTravelFee(0);
            frohubStore.setState((state) => ({ readyForMobile: false }));
        }

        setLoading(false);
    };

    // Handle location validation and price calculation
    const handleCheckLocation = async (locationData) => {
        const { setErrorMessage } = frohubStore.getState();

        setTravelFee(null);
        setIsValid(false);
        setError("");
        setLoading(true);

        if (!partnerLocation) {
            setError("partner location not loaded.");
            setErrorMessage("partner location not loaded.");
            setLoading(false);
            return;
        }

        const distance = calculateDistance(
            partnerLocation.latitude,
            partnerLocation.longitude,
            locationData.latitude,
            locationData.longitude
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
            if (totalService < 1) {
                setError("Oops! They don't cover your area. Try another stylist nearby.");
                setErrorMessage("Oops! They don't cover your area. Try another stylist nearby.");
            }
            else {
                setError("Oops! Mobile's not available in your area, but you can still book at their home or salon");
                setErrorMessage("Oops! Mobile's not available in your area, but you can still book at their home or salon");
            }
            setMobileTravelFee(0);
            frohubStore.setState((state) => ({ readyForMobile: false }));
        }

        setLoading(false);
    };

    // Handle postcode validation and price calculation
    const handleCheckPostcode = async (value) => {
        setPostcode(value);
        const { setErrorMessage } = frohubStore.getState();

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
                // UI with location search and suggestions
                <>
                    <p className="text-sm text-gray-700">
                        To check if you are within their mobile service area, enter your location or postcode.
                    </p>
                    <div className="flex justify-start items-center gap-6 mt-3">
                        <div className="relative w-full" ref={suggestionsRef}>
                            <input
                                type="text"
                                placeholder="Enter location or postcode"
                                value={postcode}
                                onChange={(e) => handleCheckPostcode(e.target.value)}
                                className="w-full px-4 py-2 text-gray-600 border rounded-md bg-gray-100 border-gray-300 focus:ring focus:ring-indigo-300 focus:outline-none"
                            />

                            {/* Location suggestions dropdown */}
                            {showSuggestions && suggestions.length > 0 && (
                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                    {suggestions.map((suggestion) => (
                                        <div
                                            key={suggestion.id}
                                            className="px-4 py-2 cursor-pointer hover:bg-gray-100 text-sm"
                                            onClick={() => handleSelectSuggestion(suggestion)}
                                        >
                                            {suggestion.description}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            {/* Loading Indicator for location check */}
                            {loading && <p className="text-sm text-gray-500 mt-2">Checking location...</p>}

                            {/* Success Message */}
                            {isValid && travelFee !== null && (
                                <div className="flex items-center text-green-600 font-semibold mt-2">
                                    <CheckCircle className="w-5 h-5 mr-1" />
                                    Yasss! You're in the service area - this stylist can come to you!
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
                <>
                    <p className="!mt-4 text-lg font-semibold">
                        Mobile Travel Fee: <span className="text-gray-900">+£{travelFee.toFixed(2)}</span>
                    </p>
                    <input type="hidden" name="travelFee" value={travelFee.toFixed(2)} />
                </>
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