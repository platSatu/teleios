/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Apps Email init js
*/

const emailDetails = document.getElementById('emailDetails');
const emailList = document.getElementById('emailList');
const closeReadEmail = document.getElementById('closeReadEmail');
const emailSidebar = document.getElementById('email-sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const emailOverlay = document.getElementById('backdrop-overlay-email');

// Close the email details panel
closeReadEmail?.addEventListener('click', function () {
    emailList?.classList.remove('d-none');
    emailDetails?.classList.add('d-none');
});

// Open email details when an email is clicked
emailList?.querySelectorAll('#mail-list .inside-mail').forEach((item) => {
    item.addEventListener('click', function () {
        emailList.classList.add('d-none');
        emailDetails.classList.remove('d-none');
    });
});

// Initialize Quill editor
if (document.getElementById('snowEditor')) {
    new Quill('#snowEditor', {
        theme: 'snow',
        modules: {
            toolbar: true,
        },
        placeholder: 'Compose your content here...',
    });
}

// Hide sidebar and overlay on backdrop click
emailOverlay?.addEventListener('click', function (e) {
    e.preventDefault();
    emailSidebar?.classList.remove('active');
    emailOverlay?.classList.remove('show');
});

// Show sidebar and overlay on toggle button click
sidebarToggle?.addEventListener('click', function () {
    emailSidebar?.classList.add('active');
    emailOverlay?.classList.add('show');
});

// Remove sidebar and overlay on window resize
window.addEventListener("resize", () => {
    emailSidebar?.classList.remove("active");
    emailOverlay?.classList.remove("show");
});

// Initial state check on load for smaller screens
if (window.innerWidth <= 1199) {
    emailSidebar?.classList.remove("active");
    emailOverlay?.classList.remove("show");
}
