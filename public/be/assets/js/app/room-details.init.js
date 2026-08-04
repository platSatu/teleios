/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: room-details init js
*/

let thumbViewSwiper, thumbsSwiper;

document.addEventListener('DOMContentLoaded', function () {
    thumbsSwiper = new Swiper(".room-swiper", {
        spaceBetween: 16,
        slidesPerView: 4,
        freeMode: true,
        watchSlidesProgress: true,
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
    });

    thumbViewSwiper = new Swiper(".room-view-swiper", {
        spaceBetween: 10,
        loop: true,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        effect: 'fade',
        speed: 1000,  
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        thumbs: {
            swiper: thumbsSwiper,
        },
    });
});

document.addEventListener('DOMContentLoaded', function () {
    let guestsChoice = document.getElementById('guests');
    if (guestsChoice) {
        const choices = new Choices('#guests', {
            placeholderValue: 'Select Guests',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let roomsChoice = document.getElementById('rooms');
    if (roomsChoice) {
        const choices = new Choices('#rooms', {
            placeholderValue: 'Select Rooms',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
});
const localeEn = {
    days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
    months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    today: 'Today',
    clear: 'Clear',
    dateFormat: 'mm/dd/yyyy',
    timeFormat: 'hh:ii aa',
    firstDay: 0
}
new AirDatepicker('#checkin', {
    autoClose: false,
    dateFormat: 'dd/MM/yyyy',
    locale: localeEn,
});
new AirDatepicker('#checkout', {
    autoClose: false,
    dateFormat: 'dd/MM/yyyy',
    locale: localeEn,
});