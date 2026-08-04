/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: POS System details init js
*/

var swiper = new Swiper(".order-center", {
    slidesPerView: 1,
    spaceBetween: 20,
    loop: true,
    autoplay: {
        delay: 3000, 
        disableOnInteraction: false 
    },
    breakpoints: {
        640: {
            slidesPerView: 2,
        },
        1441: {
            slidesPerView: 3,
        },
    },
});