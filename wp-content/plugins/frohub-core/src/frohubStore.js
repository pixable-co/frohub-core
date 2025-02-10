import { create } from "zustand";

const frohubStore = create((set) => ({
    availabilityData: [],
    loading: false,
    selectedDate: null,
    productId: null,
    selectedAddOnId: null,

    // Actions
    setAvailabilityData: (data) => set({ availabilityData: data }),
    setLoading: (loading) => set({ loading }),
    setSelectedDate: (date) => set({ selectedDate: date }),
    setProductId: (id) => set({ productId: id }),
    setSelectedAddOnId: (addonId) => set({ selectedAddOnId: addonId }),
}));

export default frohubStore;