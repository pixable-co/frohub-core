document.addEventListener('DOMContentLoaded', function () {
    const imgEl = document.getElementById('modalproductImg');

    function toggleImageVisibility() {
    const activePage = document.querySelector('.gform_page.gform_page_active');
    if (!activePage || !imgEl) return;

    // Get page number from class like 'gform_page_3'
    const match = activePage.className.match(/gform_page_(\d+)/);
    const pageNumber = match ? parseInt(match[1]) : null;

    if (pageNumber === 3) {
    imgEl.style.display = 'none';
} else {
    imgEl.style.display = 'block';
}
}

    // Run once on load
    toggleImageVisibility();

    // Re-run on every page change
    document.addEventListener('gform_page_loaded', function (event, formId, currentPage) {
    if (formId === 7) {
    toggleImageVisibility();
}
});
});