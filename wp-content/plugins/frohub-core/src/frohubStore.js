import { create } from "zustand";

const frohubStore = create((set) => ({
    // ✅ Keep your existing state
    availabilityData: [],
    loading: false,
    selectedDate: null,
    productId: null,
    selectedAddOnId: null,

    // ✅ Add new state values
    selectedAddOns: [],
    productPrice: 0,
    selectedServiceType: '',

    // ✅ Keep existing actions
    setAvailabilityData: (data) => set({ availabilityData: data, loading: false }),
    setLoading: (loading) => set({ loading }),
    setSelectedDate: (date) => set({ selectedDate: date }),
    setProductId: (id) => set({ productId: id }),
    setSelectedAddOnId: (addonId) => set({ selectedAddOnId: addonId }),

    // ✅ Add new actions
    setSelectedAddOns: (addOns) => set({ selectedAddOns: addOns }),
    setProductPrice: (price) => set({ productPrice: price }),
    setSelectedServiceType: (serviceType) => set({ selectedServiceType: serviceType }),
}));

export default frohubStore;