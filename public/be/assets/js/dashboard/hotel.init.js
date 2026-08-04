/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Hotel init js
*/

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
new AirDatepicker('#hotelSchedule', {
    autoClose: false,
    dateFormat: 'dd/MM/yyyy',
    inline: true,
    locale: localeEn,
    selectedDates: [new Date('2025-06-19')]
});

document.addEventListener("DOMContentLoaded", function () {
    const today = new Date();
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const formattedDate = today.toLocaleDateString('en-US', options); // Example: Jul 28, 2025
    document.getElementById('todayDate').innerHTML += formattedDate;
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

var revenueChart = null;
var hotelOccupancyChart = null;
var radarmultiplechart = null;
var hotelStatsChart = null;

function renderCharts() {

    // Revenue Analytics
    var options = {
        chart: {
            type: 'bar',
            height: 240,
            toolbar: {
                show: false
            },
            sparkline: {
                enabled: false
            }
        },
        colors: getColors(["--pe-primary"]),
        plotOptions: {
            bar: {
                columnWidth: '35%',
                borderRadius: 2,
                endingShape: 'rounded'
            }
        },
        dataLabels: { enabled: false },
        series: [{
            name: "Revenue",
            data: [22000, 18000, 25000, 19000, 27000, 30000, 28000]  // Jan to Jul
        }],
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            axisBorder: {
                show: true,
                color: '#ced4da'
            },
            axisTicks: {
                show: true,
                color: '#ced4da'
            },
            labels: {
                style: {
                    colors: '#6c757d',
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            show: false
        },
        grid: {
            show: false
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return "₹" + val.toLocaleString();
                }
            }
        }
    };

    revenueChart ? revenueChart.destroy() : null;
    revenueChart = new ApexCharts(document.querySelector("#revenueChart"), options);
    revenueChart.render();

    // Hotel Occupancy
    var options = {
        chart: {
            type: 'line',
            height: 340,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        series: [
            {
                name: 'Booking',
                data: [180, 250, 200, 310, 250, 200, 300, 170, 270]
            },
            {
                name: 'Last month',
                data: [90, 150, 260, 190, 140, 300, 230, 270, 110]
            }
        ],
        xaxis: {
            categories: [
                'Standard',
                'Deluxe',
                'Suite',
                'Banquet',
                'Dining',
                'Spa',
                'Pool',
                'Conference',
                'Gym',
            ],
            labels: {
                style: {
                    colors: '#888',
                    fontSize: '12px'
                }
            }
        },
        stroke: {
            curve: 'smooth',
            width: 2
        },
        markers: {
           show: false,
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (val) => `${val} Bookings`
            }
        },
        fill: {
            opacity: [1, 0.2],
        },
        colors: getColors(["--pe-primary", "--pe-dark"]),
        grid: {
            borderColor: '#f1f1f1',
            row: { opacity: 0 },
            strokeDashArray: 4
        }
    };

    hotelOccupancyChart ? hotelOccupancyChart.destroy() : null;
    hotelOccupancyChart = new ApexCharts(document.querySelector("#hotelOccupancyChart"), options);
    hotelOccupancyChart.render();

    // Weekly Visitors
    var radar_multiple_chart = {
        series: [
            {
                name: "Series 1",
                data: [60, 40, 50, 70, 90, 30], // Updated data
            },
            {
                name: "Series 2",
                data: [30, 20, 60, 90, 40, 70], // Updated data
            },
            {
                name: "Series 3",
                data: [50, 80, 70, 40, 60, 20], // Updated data
            },
        ],
        chart: {
            height: 230,
            width: 230,
            type: "radar",
            toolbar: {
                show: false, // Hide the toolbar
            },
        },
        stroke: {
            width: 2,
        },
        fill: {
            opacity: 0.1,
        },
        colors: getColors(["--pe-secondary", "--pe-success", "--pe-primary"]),
        markers: {
            size: 0,
        },
        legend: {
            show: false // Optional: hide legend if not needed
        },
        yaxis: {
            show: false, // Hide the y-axis
        },
        xaxis: {
            categories: ["2011", "2012", "2013", "2014", "2015", "2016"],
        },
        grid: {
            padding: {
                top: -60,
                bottom: -30,
                left: -10,
                right: -10,
            }
        }
    };
    radarmultiplechart ? radarmultiplechart.destroy() : null;
    radarmultiplechart = new ApexCharts(
        document.querySelector("#radar_multiple_chart"),
        radar_multiple_chart
    );
    radarmultiplechart.render();

    // Customer Satisfaction Chart
    Highcharts.chart('satisfactionChart', {
        chart: {
            type: 'variablepie',
            height: 300,
            spacing: [0, 0, 0, 0], // Remove outer padding
            margin: [0, 0, 0, 0],  // Remove margin
            backgroundColor: null
        },
        title: {
            text: null
        },
        tooltip: {
            headerFormat: '',
            pointFormat: `
            <span style="color:{point.color}">\u25CF</span> 
            <b>{point.name}</b><br/>
            Respondents: <b>{point.y}</b><br/>
            Avg Score: <b>{point.z}</b>
        `
        },
        colors: getColors(["--pe-primary", "--pe-success", "--pe-secondary", "--pe-warning", "--pe-info"]),
        series: [{
            minPointSize: 10,
            innerSize: '50%',
            zMin: 0,
            name: 'Satisfaction',
            borderRadius: 6,
            data: [
                { name: 'Very Satisfied', y: 200, z: 9.8 },
                { name: 'Satisfied', y: 160, z: 8.2 },
                { name: 'Neutral', y: 120, z: 5.5 },
                { name: 'Dissatisfied', y: 70, z: 3.1 },
                { name: 'Very Dissatisfied', y: 30, z: 1.8 }
            ]
        }],
        plotOptions: {
            variablepie: {
                size: '65%',
                center: ['50%', '30%']
            }
        },
        credits: {
            enabled: false
        }
    });

    // Reservation Statics
    var options = {
        series: [
            {
                name: "Reservations",
                type: "column",
                data: [5, 7, 4, 6, 8, 3, 9, 6, 7, 10]
            },
            {
                name: "Revenue ($)",
                type: "line",
                data: [1500, 2000, 1200, 1800, 2400, 900, 2700, 2000, 2300, 3000]
            }
        ],
        chart: {
            height: 310,
            type: "line",
            stacked: false,
            toolbar: {
                show: false
            }
        },
        stroke: {
            width: [0, 2]
        },
        plotOptions: {
            bar: {
                columnWidth: '40%',
                borderRadius: 6,
            }
        },
        xaxis: {
            categories: [
                "Jul 1", "Jul 2", "Jul 3", "Jul 4", "Jul 5",
                "Jul 6", "Jul 7", "Jul 8", "Jul 9", "Jul 10"
            ]
        },
        yaxis: [
            {
                title: {
                    show: false,
                }
            },
            {
                opposite: true,
                title: {
                    show: false,
                }
            }
        ],
        colors: getColors(["--pe-primary", "--pe-dark"]),
        tooltip: {
            shared: true,
            intersect: false
        },
        markers: {
            size: 4
        },
        grid: {
            borderColor: 'transparent'
        }
    };

    hotelStatsChart ? hotelStatsChart.destroy() : null;
    hotelStatsChart = new ApexCharts(document.querySelector("#hotelStatsChart"), options);
    hotelStatsChart.render();

}
window.addEventListener("DOMContentLoaded", renderCharts);

// Swiper
var swiper = new Swiper(".order-center", {
    slidesPerView: 1,
    spaceBetween: 20,
    loop: true,
    // autoplay: {
    //     delay: 3000,
    //     disableOnInteraction: false,
    // },
    speed: 800
});

// Added better initialization
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        renderCharts();
    }, 250);
});
