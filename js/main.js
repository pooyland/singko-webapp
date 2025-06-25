// js/main.js
/**
 * Main.js
 *
 * This file is part of the Sneat Bootstrap HTML Admin Template.
 * For more information, please visit: https://themeselection.com/item/sneat-bootstrap-html-admin-template/
 *
 * This file contains the main JavaScript logic for the template, typically initializing components.
 */

'use strict';

(function () {
    // Initialize Bootstrap tooltips, if any
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Bootstrap popovers, if any
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Handle any custom UI elements or interactions here.
    // For example, if you have any custom components that need JavaScript initialization.

    // Example: Print a message to console on DOM ready
    console.log('Sneat template main.js loaded and ready!');

    // You can add more global event listeners or setup here.
    document.addEventListener('DOMContentLoaded', function() {
        // Any DOM-dependent initialization that needs to happen after the DOM is fully loaded.
        // For instance, dynamic content loading, form enhancements, etc.
    });

})();
