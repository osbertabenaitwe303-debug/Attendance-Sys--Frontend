/**
 * Dynamic API Base URL Configuration for ST.LUKE Digital Attendance System
 * Automatically switches between local backend and Render production backend.
 */
const API_CONFIG = {
    BASE_URL: (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/ST.LUKE-backend'
        : 'https://st-luke-backend.onrender.com'
};

if (typeof window !== 'undefined') {
    window.API_CONFIG = API_CONFIG;
}
