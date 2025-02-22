import { create } from "zustand";

const frohubStore = create((set) => ({
    // ✅ Existing states
    availabilityData: [],
    loading: false,
    selectedDate: null,
    productId: null,
    selectedAddOnId: null,
    selectedAddOns: [],
    productPrice: 0,
    selectedServiceType: '',
    addonsChanged: false,

    // ✅ New state for Mobile Travel Fee
    mobileTravelFee: 0,

    // ✅ Existing actions
    setAvailabilityData: (data) => set({ availabilityData: data, loading: false }),
    setLoading: (loading) => set({ loading }),
    setSelectedDate: (date) => set({ selectedDate: date }),
    setProductId: (id) => set({ productId: id }),
    setSelectedAddOnId: (addonId) => set({ selectedAddOnId: addonId }),
    setSelectedAddOns: (addOns) => set({ selectedAddOns: addOns }),
    setProductPrice: (price) => set({ productPrice: price }),
    setSelectedServiceType: (serviceType) => set({ selectedServiceType: serviceType }),

    // ✅ New action to set Mobile Travel Fee
    setMobileTravelFee: (fee) => set({ mobileTravelFee: fee }),
    setAddonsChanged: () => set({ addonsChanged: true }),

    // ✅ Reset addonsChanged after fetch
    resetAddonsChanged: () => set({ addonsChanged: false }),
}));

export default frohubStore;
