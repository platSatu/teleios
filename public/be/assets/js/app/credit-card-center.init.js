/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: credit-card-center init js
*/

var swiper = new Swiper(".credit-card-center", {
    slidesPerView: 1,
    spaceBetween: 20,
    loop: true,
    autoplay: {
        delay: 3000,
        disableOnInteraction: false
    },
    speed: 800,
    breakpoints: {
        640: {
            slidesPerView: 2,
        },
        1025: {
            slidesPerView: 3,
        },
        1441: {
            slidesPerView: 4,
        },
    },
});

function getColors(cssVars) {
    const colorValues = cssVars.map((variable) =>
        getComputedStyle(document.documentElement).getPropertyValue(variable).trim()
    );
    return colorValues;
}

document
    .querySelectorAll('input[name="data-theme-colors"]')
    .forEach((radio) => {
        radio.addEventListener("change", function () {
            setTimeout(() => {
                renderCharts(this.value);
            }, 0);
        });
    });

function renderCharts() {

    Highcharts.chart('cardStatistic', {
        chart: {
            type: 'variablepie'
        },
        title: {
            text: null,
        },
        tooltip: {
            headerFormat: '',
            pointFormat: '<span style="color:{point.color}">\u25CF</span> <b> ' +
                '{point.name}</b><br/>' +
                'Area (square km): <b>{point.y}</b><br/>' +
                'Population density (people per square km): <b>{point.z}</b><br/>'
        },
        colors: getColors(["--pe-primary", "--pe-success", "--pe-secondary", "--pe-warning", "--pe-info"]),
        series: [{
            minPointSize: 10,
            innerSize: '20%',
            zMin: 0,
            name: 'countries',
            borderRadius: 5,
            data: [
                {
                    name: 'Master',
                    y: 25500,
                    z: 120
                },
                {
                    name: 'Visa',
                    y: 19800,
                    z: 100
                },
                {
                    name: 'Amex',
                    y: 8700,
                    z: 45
                },
                {
                    name: 'Rupay',
                    y: 5400,
                    z: 38
                },
                {
                    name: 'Paypal',
                    y: 2900,
                    z: 20
                }
            ],
        }]
    });
}
window.addEventListener("DOMContentLoaded", renderCharts);