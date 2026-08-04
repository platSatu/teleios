/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Editor Js File
*/

document.addEventListener("DOMContentLoaded", function () {
    if (document.getElementById("snowEditor")) {
        const snowEditor = new Quill("#snowEditor", {
            theme: "snow",
            modules: {
                toolbar: true,
            },
            placeholder: "Compose your content here...",
        });
    }

    if (document.getElementById("bubbleEditor")) {
        const bubbleEditor = new Quill("#bubbleEditor", {
            theme: "bubble",
            placeholder: "Compose an epic...",
        });
    }
});

