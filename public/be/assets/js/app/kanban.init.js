/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Kanban init js
*/

document.addEventListener('DOMContentLoaded', function () {
    let taskPriority = document.getElementById('taskPriority');
    if (taskPriority) {
        const taskPriority = new Choices('#taskPriority', {
            placeholderValue: 'Select Status',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let editTaskPriority = document.getElementById('editTaskPriority');
    if (editTaskPriority) {
        const editTaskPriority = new Choices('#editTaskPriority', {
            placeholderValue: 'Select Status',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let editTaskStatus = document.getElementById('editTaskStatus');
    if (editTaskStatus) {
        const editTaskStatus = new Choices('#editTaskStatus', {
            placeholderValue: 'Select Status',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    if (document.getElementById("snowEditor")) {
        const snowEditor = new Quill("#snowEditor", {
            theme: "snow",
            modules: {
                toolbar: true,
            },
            placeholder: "Compose your content here...",
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
new AirDatepicker('#editTaskDueDate', {
    autoClose: false,
    dateFormat: 'dd/MM/yyyy',
    locale: localeEn,
});

dragula([document.querySelector('#b1'), document.querySelector('#b2'), document.querySelector('#b3'), document.querySelector('#b4'), document.querySelector('#b5')]);