/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Blog Create init js
*/

document.addEventListener('DOMContentLoaded', function () {
    let blogCategory = document.getElementById('blogCategory');
    if (blogCategory) {
        const blogCategory = new Choices('#blogCategory', {
            placeholderValue: 'Select Category',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let multipleChoice = document.getElementById('choices-default-multiple');
    if (multipleChoice) {
        const multipleChoices = new Choices('#choices-default-multiple', {
            placeholderValue: '',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
            shouldSort: false,
        });
        multipleChoice.selectedIndex = 0;
        multipleChoices.setChoiceByValue(multipleChoice.options[0].value);
    }
    let publisgChoice = document.getElementById('PublishStatus');
    if (publisgChoice) {
        const publisgChoice = new Choices('#PublishStatus', {
            placeholderValue: 'Select Status',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
    let visibilityChoice = document.getElementById('BlogStatus');
    if (visibilityChoice) {
        const visibilityChoice = new Choices('#BlogStatus', {
            placeholderValue: 'Select Visibility',
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
    new AirDatepicker('#PublishDate', {
        autoClose: false,
        dateFormat: 'dd/MM/yyyy',
        locale: localeEn,
    });
    const blogImageDropzone = document.getElementById('blogImage');

    if (blogImageDropzone) {
        Dropzone.autoDiscover = false;

        new Dropzone("#blogImage", {
            url: "/file-upload", // Replace with your actual upload URL
            maxFilesize: 2, // MB
            acceptedFiles: ".jpg,.jpeg,.png,.gif",
            init: function () {
                this.on("success", function (file, response) {
                    console.log("File uploaded successfully:", response);
                });

                this.on("error", function (file, errorMessage) {
                    console.error("File upload error:", errorMessage);
                });
            }
        });
    }
});