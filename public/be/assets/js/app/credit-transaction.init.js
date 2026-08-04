/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: credit-card-center init js
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

var revenueChart = null;
var taxreverseChart = null;
var revenuestatisticsChart = null;

function renderCharts() {
    // Income % Expense chart
    var revenue_chart = {
        series: [
            {
                data: [
                    {
                        x: new Date(1538802000000),
                        y: [6612, 6624.12, 6608.43, 6622.95],
                    },
                    {
                        x: new Date(1538803800000),
                        y: [6623.91, 6623.91, 6615, 6615.67],
                    },
                    {
                        x: new Date(1538805600000),
                        y: [6618.69, 6618.74, 6610, 6610.4],
                    },
                    {
                        x: new Date(1538807400000),
                        y: [6611, 6622.78, 6610.4, 6614.9],
                    },
                    {
                        x: new Date(1538809200000),
                        y: [6614.9, 6626.2, 6613.33, 6623.45],
                    },
                    {
                        x: new Date(1538811000000),
                        y: [6623.48, 6627, 6618.38, 6620.35],
                    },
                    {
                        x: new Date(1538812800000),
                        y: [6619.43, 6620.35, 6610.05, 6615.53],
                    },
                    {
                        x: new Date(1538814600000),
                        y: [6615.53, 6617.93, 6610, 6615.19],
                    },
                    {
                        x: new Date(1538816400000),
                        y: [6615.19, 6621.6, 6608.2, 6620],
                    },
                    {
                        x: new Date(1538818200000),
                        y: [6619.54, 6625.17, 6614.15, 6620],
                    },
                    {
                        x: new Date(1538820000000),
                        y: [6620.33, 6634.15, 6617.24, 6624.61],
                    },
                    {
                        x: new Date(1538821800000),
                        y: [6625.95, 6626, 6611.66, 6617.58],
                    },
                    {
                        x: new Date(1538823600000),
                        y: [6619, 6625.97, 6595.27, 6598.86],
                    },
                    {
                        x: new Date(1538825400000),
                        y: [6598.86, 6598.88, 6570, 6587.16],
                    },
                    {
                        x: new Date(1538827200000),
                        y: [6588.86, 6600, 6580, 6593.4],
                    },
                    {
                        x: new Date(1538829000000),
                        y: [6593.99, 6598.89, 6585, 6587.81],
                    },
                    {
                        x: new Date(1538830800000),
                        y: [6587.81, 6592.73, 6567.14, 6578],
                    },
                    {
                        x: new Date(1538832600000),
                        y: [6578.35, 6581.72, 6567.39, 6579],
                    },
                    {
                        x: new Date(1538834400000),
                        y: [6579.38, 6580.92, 6566.77, 6575.96],
                    },
                    {
                        x: new Date(1538836200000),
                        y: [6575.96, 6589, 6571.77, 6588.92],
                    },
                    {
                        x: new Date(1538838000000),
                        y: [6588.92, 6594, 6577.55, 6589.22],
                    },
                    {
                        x: new Date(1538839800000),
                        y: [6589.3, 6598.89, 6589.1, 6596.08],
                    },
                    {
                        x: new Date(1538841600000),
                        y: [6597.5, 6600, 6588.39, 6596.25],
                    },
                    {
                        x: new Date(1538843400000),
                        y: [6598.03, 6600, 6588.73, 6595.97],
                    },
                    {
                        x: new Date(1538845200000),
                        y: [6595.97, 6602.01, 6588.17, 6602],
                    },
                    {
                        x: new Date(1538847000000),
                        y: [6602, 6607, 6596.51, 6599.95],
                    },
                    {
                        x: new Date(1538848800000),
                        y: [6600.63, 6601.21, 6590.39, 6591.02],
                    },
                    {
                        x: new Date(1538850600000),
                        y: [6591.02, 6603.08, 6591, 6591],
                    },
                    {
                        x: new Date(1538852400000),
                        y: [6591, 6601.32, 6585, 6592],
                    },
                    {
                        x: new Date(1538854200000),
                        y: [6593.13, 6596.01, 6590, 6593.34],
                    },
                    {
                        x: new Date(1538856000000),
                        y: [6593.34, 6604.76, 6582.63, 6593.86],
                    },
                    {
                        x: new Date(1538857800000),
                        y: [6593.86, 6604.28, 6586.57, 6600.01],
                    },
                    {
                        x: new Date(1538859600000),
                        y: [6601.81, 6603.21, 6592.78, 6596.25],
                    },
                    {
                        x: new Date(1538861400000),
                        y: [6596.25, 6604.2, 6590, 6602.99],
                    },
                ],
            },
        ],
        chart: {
            type: "candlestick",
            height: 300,
            toolbar: {
                show: false // ✅ THIS REMOVES THE TOOLBAR
            }
        },
        xaxis: {
            type: "datetime",
        },
        colors: getColors(["--pe-primary", "--pe-success"]),
        yaxis: {
            tooltip: {
                enabled: true,
            },
        },
    };
    revenueChart ? revenueChart.destroy() : null;
    revenueChart = new ApexCharts(document.querySelector("#revenue_chart"), revenue_chart);
    revenueChart.render();

    // Savings Chart
    var options = {
        series: [
            {
                name: "Tax Reversed",
                data: [1200, 900, 1500, 1100, 800, 1300, 1000, 1100, 900, 1500, 1100, 800] // Jan - Jun sample
            }
        ],
        chart: {
            type: "bar",
            height: 280,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                borderRadius: 2,
                columnWidth: "35%"
            }
        },
        colors: getColors(["--pe-primary"]),
        dataLabels: { enabled: false },
        fill: {
            type: "gradient",
            gradient: {
                shade: "light",
                type: "vertical",   // vertical gradient
                shadeIntensity: 0.0,
                gradientToColors: getColors(["--pe-dark"]), // ✅ End color
                inverseColors: false,
                opacityFrom: 0.95,
                opacityTo: 0.95,
                stops: [0, 100]
            }
        },
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            labels: { style: { colors: "#888" } }
        },
        yaxis: {
            show: false
        },
        grid: {
            strokeDashArray: 3,
            borderColor: "#eee"
        },
        tooltip: {
            y: {
                formatter: (val) => "₹" + val
            }
        }
    };

    taxreverseChart ? taxreverseChart.destroy() : null;
    taxreverseChart = new ApexCharts(document.querySelector("#tax_reverse_chart"), options);
    taxreverseChart.render();

    // Revenue Statistics Chart
    var options = {
        series: [44, 55, 41, 17, 15],
        chart: {
            height: 200,
            type: 'donut',
            dropShadow: {
                enabled: true,
                color: '#111',
                top: -1,
                left: 3,
                blur: 3,
                opacity: 0.5
            }
        },
        stroke: {
            width: 0,
        },
        colors: getColors(["--pe-secondary", "--pe-primary", "--pe-success", "--pe-warning", "--pe-info"]),
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        total: {
                            showAlways: true,
                            show: true,
                            label: "Total",
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            }
                        }
                    }
                }
            }
        },
        labels: [
            "Visa",
            "MasterCard",
            "Amex",
            "Rupay",
            "Paypal",
        ],
        dataLabels: {
            dropShadow: {
                blur: 3,
                opacity: 1
            }
        },
        fill: {
            type: 'pattern',
            opacity: 1,
            pattern: {
                enabled: true,
                style: ['verticalLines', 'squares', 'horizontalLines', 'circles', 'slantedLines'],
            },
        },
        states: {
            hover: {
                filter: 'none'
            }
        },
        legend: {
            show: false   // ✅ Hides the right-side legend completely
        },
        tooltip: {
            y: {
                formatter: (val) => `${val} Movies`
            }
        },
        theme: {
            palette: 'palette2'
        },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                }
            }
        }]
    };

    revenuestatisticsChart ? revenuestatisticsChart.destroy() : null;
    revenuestatisticsChart = new ApexCharts(document.querySelector("#revenue_statistics_chart"), options);
    revenuestatisticsChart.render();

}
window.addEventListener("DOMContentLoaded", renderCharts);


