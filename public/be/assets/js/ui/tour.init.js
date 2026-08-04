/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Tour init js
*/

document.addEventListener('DOMContentLoaded', function () {
    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            cancelIcon: { enabled: true },
            classes: 'shepherd-theme-arrows',
            scrollTo: { behavior: 'smooth', block: 'center' },
            classPrefix: 'my-tour-'
        }
    });

    tour.addStep({
        id: 'start-tour',
        text: 'Welcome to Mirbal! This template offers advanced features for your admin dashboard.',
        attachTo: { element: '#start-tour', on: 'bottom' }, // fixed selector
        buttons: [{ text: 'Next', action: tour.next }]
    });

    tour.addStep({
        id: 'project-management',
        text: 'This section provides tools for effective project management.',
        attachTo: { element: '.row > div:nth-child(1) i', on: 'bottom' },
        buttons: [
            { text: 'Back', action: tour.back },
            { text: 'Next', action: tour.next }
        ]
    });

    tour.addStep({
        id: 'task-automation',
        text: 'Here, you can streamline your workflow with automation solutions.',
        attachTo: { element: '.row > div:nth-child(2) i', on: 'bottom' },
        buttons: [
            { text: 'Back', action: tour.back },
            { text: 'Next', action: tour.next }
        ]
    });

    tour.addStep({
        id: 'data-analysis',
        text: 'This section helps you turn data into actionable insights.',
        attachTo: { element: '.row > div:nth-child(3) i', on: 'bottom' },
        buttons: [
            { text: 'Back', action: tour.back },
            { text: 'Next', action: tour.next }
        ]
    });

    // This last selector needs to exist too
    tour.addStep({
        id: 'end-tour',
        text: 'Learn more about us and our commitment to excellence.',
        attachTo: { element: '#end-tour', on: 'top' },
        buttons: [
            { text: 'Back', action: tour.back },
            { text: 'Finish', action: tour.complete }
        ]
    });

    tour.start();
});
