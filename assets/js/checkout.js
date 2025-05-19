jQuery(document).ready(function ($) {

    // Listen for changes to the country dropdowns
    $('#billing_country, #shipping_country').on('change', function () {
        // Add a small delay before refresh to allow WooCommerce to update any internal state
        setTimeout(function () {
            window.location.reload();
        }, 3000);
    });

    $('#billing_city, #shipping_city, #billing_area, #shipping_area').on('change', function () {
        const city = $('#billing_city, #shipping_city').val();

        $('body').trigger('update_checkout');
    });
});