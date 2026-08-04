/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: restaurant init js
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

var orderOverview = null;
var expenseChart = null;
var revenueChart = null;

function renderCharts() {
    // Total Revenue
    var dineInRevenue = [40, 40, 40, 60, 60, 80, 80, 60, 60, 40, 40, 50, 50, 65, 65, 50, 50, 35, 35, 50, 50, 70, 70, 90, 90, 90, 60, 60, 40, 40, 40];
    var deliveryRevenue = [60, 60, 60, 40, 40, 30, 30, 50, 50, 70, 70, 70, 90, 90, 90, 70, 70, 70, 45, 45, 45, 27, 27, 27, 40, 40, 40, 40, 60, 60, 60];

    var lastValueDineIn = dineInRevenue[dineInRevenue.length - 1];
    var lastValueDelivery = deliveryRevenue[deliveryRevenue.length - 1];

    var options = {
        series: [
            { name: `Dine-in - $${lastValueDineIn}K`, data: dineInRevenue },
            { name: `Delivery - $${lastValueDelivery}K`, data: deliveryRevenue }
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
            width: 1.5,
        },
        colors: getColors(["--pe-primary", "--pe-dark"]),
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
            show: true,
            borderColor: '#eee',
            strokeDashArray: 4, // dashed lines
            xaxis: {
                lines: {
                    show: false // hide X grid lines
                }
            },
            yaxis: {
                lines: {
                    show: true // show Y grid lines
                }
            }
        },
        markers: {
            hover: { size: 5 }
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '13px',
            fontWeight: 500,
            markers: {
                size: 4,
                strokeWidth: 2,
                strokeColors: '#fff',
                hover: { size: 4 }
            },
        },
        xaxis: {
            categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30'],
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
                    return "$" + val + "K (monthly)";
                }
            }
        }
    };

    revenueChart ? revenueChart.destroy() : null;
    revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), options);
    revenueChart.render();

    // Total expense Chart
    const donutOptions = {
        series: [15, 8, 25, 18],
        chart: {
            width: 250,
            height: 250,
            type: 'donut'
        },
        colors: getColors(["--pe-primary", "--pe-secondary", "--pe-success", "--pe-warning"]),
        plotOptions: {
            pie: {
                donut: {
                    size: '75%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '16px',
                            fontWeight: 600,
                            color: '#374151',
                            offsetY: -10
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 700,
                            color: '#111827',
                            offsetY: 10,
                            formatter: function (val) {
                                return '$80,832';
                            }
                        },
                        total: {
                            show: true,
                            label: 'Total Expense',
                            fontSize: '16px',
                            fontWeight: 600,
                            color: '#374151'
                        }
                    }
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: false
        },
        stroke: {
            width: 8,
            colors: ['#ffffff']
        }
    };

    if (expenseChart) expenseChart.destroy();
    expenseChart = new ApexCharts(document.querySelector("#expense-chart"), donutOptions);
    expenseChart.render();

    // Order Overview chart
    var options = {
        series: [{
            name: 'Orders',
            data: [120, 98, 135, 150, 180, 95, 110]
        }],
        chart: {
            height: 323,
            type: 'bar',
            toolbar: {
                show: false
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '35%',
                distributed: true
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            labels: {
                rotate: 0
            },
            tickPlacement: 'on'
        },
        yaxis: {
            title: {
                text: 'Orders'
            }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: "horizontal",
                shadeIntensity: 0.25,
                opacityFrom: 0.85,
                opacityTo: 0.85,
                stops: [50, 0, 100]
            }
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + " orders";
                }
            }
        },
        grid: {
            strokeDashArray: 4
        },
        colors: getColors(["--pe-primary"]),
        legend: {
            show: false // 👈 THIS hides the label row
        }
    };

    orderOverview ? orderOverview.destroy() : null;
    orderOverview = new ApexCharts(document.querySelector("#orderOverview"), options);
    orderOverview.render();
}

// Swiper
var swiper = new Swiper(".social-performance-carousel", {
    slidesPerView: 1,
    spaceBetween: 20,
    loop: true,
    autoplay: {
        delay: 3000,
        disableOnInteraction: false,
    },
    speed: 500
});


// Added better initialization
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        renderCharts();
    }, 250);
});