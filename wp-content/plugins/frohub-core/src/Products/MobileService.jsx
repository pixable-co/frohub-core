import React, { useState, useEffect, useRef } from "react";
import { CheckCircle, XCircle, RefreshCw } from "lucide-react";
import { Skeleton } from "antd";
import frohubStore from "../frohubStore.js";
import { getLocationDataFromCookie } from "../utils/locationUtils.js";
import { fetchData } from "../services/fetchData.js";
import { toastNotification } from "../utils/toastNotification.js";

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

    // Reference for the input element
    const autocompleteInputRef = useRef(null);
    // Reference to store the Google Places Autocomplete instance
    const autocompleteRef = useRef(null);
    // Store selected place data
    const [selectedPlace, setSelectedPlace] = useState(null);

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

    useEffect(() => {
        // Initialize Google Places Autocomplete
        const initializeAutocomplete = () => {
            if (window.google && window.google.maps && window.google.maps.places &&
                autocompleteInputRef.current && !autocompleteRef.current) {
                const options = {
                    componentRestrictions: { country: "uk" },
                    types: ["geocode"]
                };

                autocompleteRef.current = new window.google.maps.places.Autocomplete(
                    autocompleteInputRef.current,
                    options
                );

                // Add listener for place selection
                autocompleteRef.current.addListener("place_changed", () => {
                    const place = autocompleteRef.current.getPlace();

                    if (!place.geometry) {
                        console.error("No geometry for this place");
                        return;
                    }

                    setPostcode(place.formatted_address);

                    const locationData = {
                        latitude: place.geometry.location.lat(),
                        longitude: place.geometry.location.lng(),
                        address: place.formatted_address
                    };

                    setSelectedPlace(locationData);
                });
            }
        };

        initializeAutocomplete();

        // Check every 500ms if Google Maps has loaded
        const interval = setInterval(() => {
            if (!autocompleteRef.current) {
                initializeAutocomplete();
            }
        }, 500);

        return () => {
            clearInterval(interval);
            // Clean up the autocomplete when component unmounts
            if (autocompleteRef.current) {
                window.google.maps.event.clearInstanceListeners(autocompleteRef.current);
                autocompleteRef.current = null;
            }
        };
    }, []);

    // Process selected place when both place and partner location are available
    useEffect(() => {
        if (selectedPlace && partnerLocation) {
            handleCheckLocation(selectedPlace);
        }
    }, [selectedPlace, partnerLocation]);

    // Fetch the partner's location and radius pricing
    const fetchPartnerLocation = () => {
        setLoadingPartner(true);
        setError("");

        fetchData(
            "frohub/get_mobile_location_data",
            (response) => {
                console.log("Partner location response:", response);

                if (response.success) {
                    // Check different possible data structures
                    let locationData;
                    let radiusFees = [];

                    // Handle different response structures
                    if (response.data && response.data.data) {
                        locationData = response.data.data;
                    } else if (response.data) {
                        locationData = response.data;
                    } else {
                        locationData = response;
                    }

                    // Handle different radius fees structures
                    if (locationData.radius_fees) {
                        radiusFees = locationData.radius_fees;
                    } else if (locationData.radiusFees) {
                        radiusFees = locationData.radiusFees;
                    } else if (response.radius_fees) {
                        radiusFees = response.radius_fees;
                    }

                    // Make sure we have valid latitude and longitude
                    const latitude = parseFloat(locationData.latitude || locationData.lat || 0);
                    const longitude = parseFloat(locationData.longitude || locationData.lng || 0);

                    if (latitude && longitude && radiusFees && radiusFees.length > 0) {
                        const partnerLocationData = {
                            latitude: latitude,
                            longitude: longitude,
                            radiusFees: radiusFees.map((fee) => ({
                                radius: parseFloat(fee.radius || fee.distance || 0),
                                price: parseFloat(fee.price || fee.fee || 0),
                            })),
                        };

                        console.log("Setting partner location:", partnerLocationData);
                        setPartnerLocation(partnerLocationData);
                    } else {
                        console.error("Invalid location data structure:", locationData);
                        setError("Failed to parse partner location data.");
                    }
                } else {
                    setError("Failed to fetch partner location.");
                    console.error("Error fetching location:", response.message || "Unknown error");
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

        console.log("Distance calculated:", distance, "miles");
        console.log("Partner radius fees:", partnerLocation.radiusFees);

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
            frohubStore.setState({ readyForMobile: true });
        } else {
            setIsValid(false);
            if (totalService < 1) {
                setError("Oops! They don't cover your area. Try another stylist nearby.");
            }
            else {
                setError("Oops! Mobile's not available in your area, but you can still book at their home or salon");
            }
            setMobileTravelFee(0);
            frohubStore.setState({ readyForMobile: false });
        }

        setLoading(false);
    };

    // Handle location validation and price calculation
    const handleCheckLocation = (locationData) => {
        console.log("Checking location:", locationData);
        console.log("Partner location available:", partnerLocation);

        setTravelFee(null);
        setIsValid(false);
        setError("");
        setLoading(true);

        if (!partnerLocation) {
            console.error("Partner location not loaded when checking location");
            setError("Partner location not loaded. Please try again.");
            setLoading(false);
            return;
        }

        const distance = calculateDistance(
            partnerLocation.latitude,
            partnerLocation.longitude,
            locationData.latitude,
            locationData.longitude
        );

        console.log("Distance calculated:", distance, "miles");
        console.log("Partner radius fees:", partnerLocation.radiusFees);

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
            frohubStore.setState({ readyForMobile: true });
        } else {
            setIsValid(false);
            if (totalService < 1) {
                setError("Oops! They don't cover your area. Try another stylist nearby.");
            }
            else {
                setError("Oops! Mobile's not available in your area, but you can still book at their home or salon");
            }
            setMobileTravelFee(0);
            frohubStore.setState({ readyForMobile: false });
        }

        setLoading(false);
    };

    // Handle postcode input change
    const handlePostcodeChange = (e) => {
        setPostcode(e.target.value);
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
        <div className="sm:w-full md:w-5/6 mt-4 mb-6 p-4 border border-gray-300 rounded-xl bg-[#F5F5F5]">
            {!staticLocation ? (
                // UI with Google Places Autocomplete
                <>
                    <p className="text-sm text-gray-700">
                        To check if you are within their mobile service area, enter your location or postcode.
                    </p>
                    <div className="flex flex-col md:flex-row justify-start items-center gap-6 mt-3">
                        <div className="sm:w-full md:w-[35%]">
                            <input
                                ref={autocompleteInputRef}
                                type="text"
                                placeholder="Enter Location Or Postcode"
                                value={postcode}
                                onChange={handlePostcodeChange}
                                className="us-field-style_2"
                            />
                        </div>

                        <div>
                            {/* Loading Indicator for location check */}
                            {loading && <p className="text-sm text-gray-500 mt-2">Checking location...</p>}

                            {/* Success Message */}
                            {isValid && travelFee !== null && (
                                <div className="flex text-sm font-semibold mt-2">
                                    <CheckCircle className="w-5 h-5 mr-1"/>
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
                    <input
                        type="hidden"
                        name="postCode"
                        value={selectedPlace?.address || staticLocation?.name || postcode}
                    />
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