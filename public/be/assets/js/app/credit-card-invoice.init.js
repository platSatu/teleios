/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Invoice init js
*/

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

var invoiceOverview = null;

function renderCharts() {
    var options = {
        series: [
            {
                name: 'Males',
                data: [
                    3.5, 4.5, 2.1, 3.5, 1.8, 5, 2, 3, 4.1, 2,
                    4.5, 3.6
                ]
            },
            {
                name: 'Females',
                data: [
                    -3, -2, -2, -3, -1.9, -2.6, -2.2, -1.7, -1, -2.5,
                    -3, -2
                ]
            }
        ],
        chart: {
            type: 'bar',
            height: 330,
            stacked: true
        },
        colors: getColors(["--pe-primary", "--pe-dark"]),
        plotOptions: {
            bar: {
                borderRadius: 5,
                columnWidth: '16%',
                horizontal: false // ✅ Vertical bars
            }
        },
        dataLabels: { enabled: false },
        stroke: {
            width: 1,
            colors: ['#fff']
        },
        grid: {
            yaxis: { lines: { show: false } }
        },
        yaxis: {
            min: -4,
            max: 5,
            tickAmount: 9,
            title: {
                text: 'Percent'
            },
            labels: {
                formatter: function (val) {
                    return val + "%"; // ✅ Shows actual negative and positive values
                }
            }
        },
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        },
        tooltip: {
            shared: false,
            y: {
                formatter: function (val) {
                    return Math.abs(val) + "%";
                }
            }
        },
        title: { show: false }
    };


    invoiceOverview ? invoiceOverview.destroy() : null;
    invoiceOverview = new ApexCharts(document.querySelector("#invoiceOverview"), options);
    invoiceOverview.render();
}
window.addEventListener("DOMContentLoaded", renderCharts);
