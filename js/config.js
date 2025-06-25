// js/config.js
/**
 * Config.js
 *
 * This file is part of the Sneat Bootstrap HTML Admin Template.
 * For more information, please visit: https://themeselection.com/item/sneat-bootstrap-html-admin-template/
 *
 * This file contains configuration settings for the template.
 */

'use strict';

// Default configuration for the template.
// You can override these values in your custom scripts.
const config = {
    // Example setting: Enable/disable dark mode by default
    darkMode: false,
    // Example setting: Sidebar collapsed state
    sidebarCollapsed: false,
    // Base URL for API calls or assets if dynamic.
    // In your case, since assets are relative to root, this might not be strictly used
    // by this config.js file, but could be used by other parts of the template's JS.
    // Set to your project's base URL if dynamic routing is used.
    baseUrl: '/',
};

// You can expose config globally if other scripts need it
window.templateConfig = config;

// For the template's own scripts (like main.js, menu.js) they might directly access this.
