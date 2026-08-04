/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Calendar init js - FIXED VERSION with Solid Colors
*/

document.addEventListener('DOMContentLoaded', function () {
    let today = new Date();
    let y = today.getFullYear();
    let m = today.getMonth();

    let calendarEl = document.getElementById('calendar');
    let eventTitleInput = document.getElementById('inputExample');
    let eventStartDateInput = document.getElementById('eventStartDate');
    let eventEndDateInput = document.getElementById('eventEndDate');
    let eventDescriptionInput = document.getElementById('description');
    let eventLabelSelect = document.getElementById('form-select-01');

    // ✅ Sample events
    let events = [
        { id: "1", title: "World Braille Day", start: new Date(y, m, 2), className: "bg-success border-0", allDay: true, extendedProps: { label: "Holiday", description: "Celebrating the importance of Braille." } },
        { id: "2", title: "Team Meeting", start: new Date(y, m, 4), className: "bg-primary border-0", allDay: true, extendedProps: { label: "Meeting", description: "Weekly team sync-up meeting." } },
        { id: "3", title: "All Day Event", start: new Date(y, m, 12), className: "bg-secondary border-0", allDay: true, extendedProps: { label: "Meeting", description: "General all-day event." } },
        { id: "5", title: "Kids' Sports Day", start: new Date(y, m, 15), className: "bg-warning border-0", allDay: true, extendedProps: { label: "Fun", description: "A fun sports event for kids." } },
        { id: "7", title: "Repeating Event", start: new Date(y, m, 24), end: new Date(y, m, 27), className: "bg-primary border-0", allDay: true, extendedProps: { label: "Deadline", description: "A repeating event over multiple days." } },
        { id: "8", title: "Project Deadline", start: new Date(y, m, 26), className: "bg-warning border-0", allDay: true, extendedProps: { label: "Deadline", description: "Final submission deadline for project." } },
        { id: "9", title: "National Holiday", start: new Date(y, m, 29), className: "bg-success border-0", allDay: true, extendedProps: { label: "Holiday", description: "Public national holiday." } },
        { id: "12", title: "Training Session", start: new Date(y, m, 8), end: new Date(y, m, 10), className: "bg-primary border-0", allDay: true, extendedProps: { label: "Meeting", description: "Employee training and skill development." } },
        { id: "13", title: "Camping Trip", start: new Date(y, m, 21), className: "bg-secondary border-0", allDay: true, extendedProps: { label: "Holiday", description: "Outdoor camping with friends and family." } },
    ];

    // ✅ Initialize FullCalendar
    let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        editable: true,
        events: events,

        eventClick: function (info) {
            let event = info.event;

            // Populate modal for editing
            eventTitleInput.value = event.title || "";
            eventDescriptionInput.value = event.extendedProps.description || "";

            // Populate label
            if (eventLabelSelect) {
                let className = event.classNames[0]?.replace(' border-0', '') || 'bg-primary';
                eventLabelSelect.value = className;
            }

            // Populate dates in AirDatepicker-friendly format
            if (event.start) eventStartDatepicker.selectDate(event.start);
            if (event.end) {
                eventEndDatepicker.selectDate(event.end);
            } else {
                eventEndDatepicker.clear();
            }

            document.getElementById('eventId').value = event.id || "";

            let modal = new bootstrap.Modal(document.getElementById('exampleModalToggle'));
            modal.show();
        },

        dateClick: function (info) {
            // Reset fields for new event
            eventTitleInput.value = "";
            eventDescriptionInput.value = "";
            document.getElementById('eventId').value = "";
            eventLabelSelect.value = "bg-primary";

            // Set date in picker
            eventStartDatepicker.selectDate(new Date(info.dateStr));
            eventEndDatepicker.selectDate(new Date(info.dateStr));

            let modal = new bootstrap.Modal(document.getElementById('exampleModalToggle'));
            modal.show();
        }
    });

    calendar.render();

    // ✅ Form submission
    document.getElementById('form-event').addEventListener('submit', function (e) {
        e.preventDefault();

        let eventId = document.getElementById('eventId').value;
        let title = eventTitleInput.value.trim();
        let start = eventStartDateInput.value;
        let end = eventEndDateInput.value || start;
        let description = eventDescriptionInput.value;

        let selectedOption = eventLabelSelect.options[eventLabelSelect.selectedIndex];
        let labelClass = selectedOption.value;
        let labelText = selectedOption.text;

        if (!title) {
            document.getElementById('titleErr').innerText = "Event title is required!";
            return;
        } else {
            document.getElementById('titleErr').innerText = "";
        }

        let startDate = new Date(start);
        let endDate = new Date(end);

        let event = calendar.getEventById(eventId);
        if (event) {
            // Editing existing
            event.setProp('title', title);
            event.setStart(startDate);
            event.setEnd(endDate);
            event.setExtendedProp('description', description);
            event.setExtendedProp('label', labelText);
            event.setProp('classNames', [labelClass + ' border-0']);
        } else {
            // Creating new
            let newEventId = 'event_' + Date.now();
            calendar.addEvent({
                id: newEventId,
                title: title,
                start: startDate,
                end: endDate,
                allDay: true,
                className: labelClass + ' border-0',
                extendedProps: {
                    label: labelText,
                    description: description
                }
            });
        }

        document.getElementById('form-event').reset();
        eventStartDatepicker.clear();
        eventEndDatepicker.clear();

        let modalElement = document.getElementById('exampleModalToggle');
        let modalInstance = bootstrap.Modal.getInstance(modalElement);
        modalInstance.hide();
    });

    // ✅ Locale for AirDatepicker
    const localeEn = {
        days: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        months: ['January', 'February', 'March', 'April', 'May', 'June', 'July',
            'August', 'September', 'October', 'November', 'December'],
        today: 'Today',
        clear: 'Clear',
    };

    // ✅ Initialize AirDatepicker for From & To dates
    let inlinePicker = new AirDatepicker('#inline-picker', {
        autoClose: true,
        dateFormat: 'yyyy-MM-dd',
        locale: localeEn
    });
    let inlinePicker2 = new AirDatepicker('#inline-picker-2', {
        autoClose: true,
        dateFormat: 'yyyy-MM-dd',
        locale: localeEn
    });

    // ✅ Initialize AirDatepicker for From & To dates
    let eventStartDatepicker = new AirDatepicker('#eventStartDate', {
        autoClose: true,
        dateFormat: 'yyyy-MM-dd',
        locale: localeEn
    });

    let eventEndDatepicker = new AirDatepicker('#eventEndDate', {
        autoClose: true,
        dateFormat: 'yyyy-MM-dd',
        locale: localeEn
    });

    // ✅ Initialize Choices.js for Label select
    new Choices('#form-select-01', {
        searchEnabled: false,
        itemSelectText: '',
    });
    var swiper = new Swiper(".myScheduleSwiper", {
        spaceBetween: 30,
        centeredSlides: true,
        loop: true,
        autoplay: {
            delay: 2500,
            disableOnInteraction: false,
        },
    });

});
