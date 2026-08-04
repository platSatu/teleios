/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: restaurant order details init js
*/

// Leaflet Map

document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('leaflet_map').setView([51.505, -0.09], 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    L.marker([51.505, -0.09]).addTo(map)
        .bindPopup("<b>New York!</b><br>This is your location.")
        .openPopup();
});