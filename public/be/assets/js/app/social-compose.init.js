/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Social Compose init js
*/

// File Upload

document.addEventListener("DOMContentLoaded", function () {
    const myDropzoneMain = document.getElementById('mailAttachment');

    if (myDropzoneMain) {
        Dropzone.autoDiscover = false;

        new Dropzone("#mailAttachment", {
            url: "/file-upload",
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

    if (document.getElementById("mailMessage")) {
        const mailMessage = new Quill("#mailMessage", {
            theme: "bubble",
            placeholder: "Compose an epic...",
        });
    }
});
