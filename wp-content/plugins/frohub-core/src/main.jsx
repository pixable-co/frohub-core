import RequestBookButton from './shortcodes/Product/request_book_button';
import FrohubCalender from './shortcodes/producttemplate/frohub_calender';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import AddToCartProduct from './products/AddToCartProduct';
import {storeLocationData, getLocationDataFromCookie} from "./utils/locationUtils.js";

// ✅ Run the function on page load
storeLocationData();

// ✅ Log stored location data for testing
const storedLocationData = getLocationDataFromCookie();
if (storedLocationData) {
    console.log("Stored Location Data:", storedLocationData);
}

// Find the element with the class 'frohub_add_to_cart'
const element = document.querySelector('.frohub_add_to_cart');

if (element) {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <AddToCartProduct dataKey={key} />
    );
}


const frohubCalenderElements = document.querySelectorAll('.frohub_calender');
frohubCalenderElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <FrohubCalender dataKey={key} />
    );
});

const requestBookButtonElements = document.querySelectorAll('.request_book_button');
requestBookButtonElements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <RequestBookButton dataKey={key} />
    );
});