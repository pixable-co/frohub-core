document.addEventListener('DOMContentLoaded', function () {
    const imgEl = document.getElementById('modalproductImg');
    const page3 = document.getElementById('gform_page_7_3');
    const rightCol = document.querySelector('.modal-body-right');

    const bookingDate = document.getElementById('bookingDate');
    const bookingAddress = document.getElementById('bookingAddress');
    const feedbackForm = document.querySelector('.feedback-form');

    function updateLayoutForPage3() {
    if (!page3 || !rightCol) return;

    const isVisible = window.getComputedStyle(page3).display !== 'none';

    if (isVisible) {
    imgEl && (imgEl.style.display = 'none');
    bookingDate && (bookingDate.style.display = 'none');
    bookingAddress && (bookingAddress.style.display = 'none');
    feedbackForm && (feedbackForm.style.display = 'block');
    rightCol.classList.add('full-width');
    console.log('Page 3 visible → image hidden, .modal-body-right is 100%');
} else {
    imgEl && (imgEl.style.display = 'block');
    bookingDate && (bookingDate.style.display = 'block');
    bookingAddress && (bookingAddress.style.display = 'block');
    feedbackForm && (feedbackForm.style.display = 'none');
    rightCol.classList.remove('full-width');
    console.log('Page 3 not visible → image shown, .modal-body-right normal');
}
}

    // Watch for Gravity Form page changes
    document.addEventListener('click', function (e) {
    const isNavBtn = e.target.matches('.gform_next_button, .gform_previous_button');
    if (isNavBtn) {
    setTimeout(updateLayoutForPage3, 120);
}
});

    // Initial run
    updateLayoutForPage3();
});