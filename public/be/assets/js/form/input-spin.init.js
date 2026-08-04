/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Input Spin js
*/

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".qty-input").forEach(function (qtyContainer) {
        const input = qtyContainer.querySelector("input[type='number']");
        const minusBtn = qtyContainer.querySelector("[data-action='minus']");
        const plusBtn = qtyContainer.querySelector("[data-action='plus']");

        minusBtn.addEventListener("click", function () {
            let currentValue = parseInt(input.value, 10) || 0;
            let min = parseInt(input.min, 10) || 0;
            if (currentValue > min) {
                input.value = currentValue - 1;
                input.dispatchEvent(new Event("change")); // optional, triggers change events
            }
        });

        plusBtn.addEventListener("click", function () {
            let currentValue = parseInt(input.value, 10) || 0;
            let max = parseInt(input.max, 10) || 100;
            if (currentValue < max) {
                input.value = currentValue + 1;
                input.dispatchEvent(new Event("change"));
            }
        });
    });
});