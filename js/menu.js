// js/menu.js
/**
 * Menu.js
 *
 * This file is part of the Sneat Bootstrap HTML Admin Template.
 * For more information, please visit: https://themeselection.com/item/sneat-bootstrap-html-admin-template/
 *
 * This file handles the sidebar menu functionality, including collapsing and expanding.
 */

'use strict';

(function () {
    const layoutMenu = document.getElementById('layout-menu');
    const layoutMenuToggle = document.querySelector('.layout-menu-toggle');
    const layoutOverlay = document.querySelector('.layout-overlay');

    if (layoutMenu && layoutMenuToggle && layoutOverlay) {
        layoutMenuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            layoutMenu.classList.toggle('open'); // Toggles the 'open' class on the sidebar
            layoutOverlay.classList.toggle('show'); // Toggles the 'show' class on the overlay
        });

        layoutOverlay.addEventListener('click', function () {
            layoutMenu.classList.remove('open');
            layoutOverlay.classList.remove('show');
        });

        // Close sidebar on small screens when a menu item is clicked
        layoutMenu.addEventListener('click', function (e) {
            if (window.innerWidth < 1200) { // Assuming 1200px is the breakpoint for desktop/mobile sidebar
                const targetLink = e.target.closest('.menu-link');
                if (targetLink && !targetLink.closest('.menu-toggle')) { // If it's a link and not a parent menu toggle
                    layoutMenu.classList.remove('open');
                    layoutOverlay.classList.remove('show');
                }
            }
        });
    }

    // Handle Perfect Scrollbar initialization if it's used
    if (typeof PerfectScrollbar !== 'undefined') {
        const ps = new PerfectScrollbar(layoutMenu, {
            wheelPropagation: false,
            suppressScrollX: true // Only allow vertical scrolling
        });
    }

})();
