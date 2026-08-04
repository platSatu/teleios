/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: room booking init js
*/

document.addEventListener('DOMContentLoaded', function () {
    let roomNumberBookingChoice = document.getElementById('roomNumberBooking');
    if (roomNumberBookingChoice) {
        const choices = new Choices('#roomNumberBooking', {
            placeholderValue: 'Select Type',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let guestsBookingChoice = document.getElementById('guestsBooking');
    if (guestsBookingChoice) {
        const choices = new Choices('#guestsBooking', {
            placeholderValue: 'Select Category',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let statusBookingChoice = document.getElementById('statusBooking');
    if (statusBookingChoice) {
        const choices = new Choices('#statusBooking', {
            placeholderValue: 'Select Category',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let paymentBookingChoice = document.getElementById('paymentBooking');
    if (paymentBookingChoice) {
        const choices = new Choices('#paymentBooking', {
            placeholderValue: 'Select Category',
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
    months: ['January','February','March','April','May','June', 'July','August','September','October','November','December'],
    monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    today: 'Today',
    clear: 'Clear',
    dateFormat: 'mm/dd/yyyy',
    timeFormat: 'hh:ii aa',
    firstDay: 0
}
new AirDatepicker('#checkInBooking', {
      autoClose: false,
    dateFormat: 'dd/MM/yyyy',
    locale: localeEn,
});
new AirDatepicker('#checkOutBooking', {
      autoClose: false,
    dateFormat: 'dd/MM/yyyy',
    locale: localeEn,
});