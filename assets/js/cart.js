/**
 * Darb Assabil cart page JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Remove elements from the DOM by selector
     * @param {string} selector - jQuery selector for elements to remove
     * @param {boolean} hide - If true, hide instead of remove (optional)
     */
    function removeElement(selector, hide = false) {
        if ($(selector).length) {
            console.log('Removing element(s): ' + selector);
            if (hide) {
                $(selector).hide();
            } else {
                $(selector).remove();
            }
            return true;
        } else {
            console.log('Element not found: ' + selector);
            return false;
        }
    }
    
    // Check if we're on the cart page
    if ($('body').hasClass('woocommerce-cart') || window.location.href.indexOf('/cart') > -1) {
        console.log('Darb Assabil: Cart page detected, removing shipping elements');
        
        // Remove the shipping row from the cart totals table
        removeElement('tr.woocommerce-shipping-totals.shipping');
        
        // Also remove any shipping calculator forms
        removeElement('.woocommerce-shipping-calculator');
        
        // If the shipping destination paragraph exists elsewhere, remove it too
        removeElement('.woocommerce-shipping-destination');
    }
    
    // Add country change event listener on checkout page
    if ($('body').hasClass('woocommerce-checkout') || window.location.href.indexOf('/checkout/') > -1) {
        // Listen for changes to the country dropdowns
        $('#billing_country, #shipping_country').on('change', function() {
            console.log('Country changed - refreshing page');
            // Add a small delay before refresh to allow WooCommerce to update any internal state
            setTimeout(function() {
                window.location.reload();
            }, 300);
        });
    }
}); 