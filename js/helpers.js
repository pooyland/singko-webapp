// js/helpers.js
/**
 * Helpers.js
 *
 * This file is part of the Sneat Bootstrap HTML Admin Template.
 * For more information, please visit: https://themeselection.com/item/sneat-bootstrap-html-admin-template/
 *
 * This file contains utility functions that might be used across the template.
 */

'use strict';

(function () {
    // Helper function for checking if an element has a specific class
    window.hasClass = function (element, className) {
        if (!element || !className) return false;
        return element.classList.contains(className);
    };

    // Helper function to add/remove classes
    window.toggleClass = function (element, className, force) {
        if (!element || !className) return;
        if (typeof force !== 'undefined') {
            return force ? element.classList.add(className) : element.classList.remove(className);
        }
        return element.classList.toggle(className);
    };

    // Helper for finding the closest parent with a class
    window.findClosestParent = function (elem, className) {
        if (!elem || !className) return null;
        for (; elem && elem !== document; elem = elem.parentNode) {
            if (hasClass(elem, className)) {
                return elem;
            }
        }
        return null;
    };

    // Add other common utility functions here as needed by your template.
    // For example, functions to handle AJAX, animations, etc.
})();
