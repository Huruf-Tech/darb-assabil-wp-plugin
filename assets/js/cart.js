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
            if (hide) {
                $(selector).hide();
            } else {
                $(selector).remove();
            }
            return true;
        } else {
            return false;
        }
    }
    
    // Check if we're on the cart page
    if ($('body').hasClass('woocommerce-cart') || window.location.href.indexOf('/cart') > -1) {
        
        // Remove the shipping row from the cart totals table
        removeElement('tr.woocommerce-shipping-totals.shipping');
        
        // Also remove any shipping calculator forms
        removeElement('.woocommerce-shipping-calculator');
        
        // If the shipping destination paragraph exists elsewhere, remove it too
        removeElement('.woocommerce-shipping-destination');
    }
}); 