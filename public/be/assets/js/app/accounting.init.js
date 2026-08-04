/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: accounting init js
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

var comparisonChart = null;

function renderCharts() {
    var chartDataBasic = [80, 80, 65, 65, 50, 50, 70, 70, 40, 40, 20, 20];
    var chartDataPro = [30, 30, 40, 40, 65, 65, 40, 40, 50, 50, 75, 75];

    var lastValueBasic = chartDataBasic[chartDataBasic.length - 1];
    var lastValuePro = chartDataPro[chartDataPro.length - 1];

    var options = {
        series: [
            { name: `Basic Revenue - $${lastValueBasic}K`, data: chartDataBasic },
            { name: `Pro Revenue - $${lastValuePro}K`, data: chartDataPro }
        ],
        chart: {
            type: 'area',
            height: 320,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'Poppins, sans-serif'
        },
        stroke: {
            curve: 'smooth',
            width: 2
        },
        markers: {
            size: 4,
            strokeWidth: 2,
            strokeColors: '#fff',
            hover: { size: 8 }
        },
        colors: getColors(["--pe-primary", "--pe-secondary"]),
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: "vertical",
                shadeIntensity: 0.2,
                opacityFrom: 0.25,
                opacityTo: 0.05,
                stops: [0, 100]
            }
        },
        dataLabels: { enabled: false },
        grid: {
            borderColor: '#eee',
            strokeDashArray: 4
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '13px',
            fontWeight: 500,
            markers: { width: 10, height: 10, radius: 12 }
        },
        xaxis: {
            categories: [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ],
            labels: {
                style: {
                    colors: '#8e8da4',
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 5,
            labels: {
                style: {
                    colors: '#8e8da4',
                    fontSize: '12px'
                }
            }
        },
        tooltip: {
            shared: true,
            intersect: false,
            theme: 'light',
            y: {
                formatter: function (val) {
                    return "$" + val + "K";
                }
            }
        }
    };
    
    comparisonChart ? comparisonChart.destroy() : null;
    comparisonChart = new ApexCharts(document.querySelector("#comparison_chart"), options);
    comparisonChart.render();
}
window.addEventListener("DOMContentLoaded", renderCharts);