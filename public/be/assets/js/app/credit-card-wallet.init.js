/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: My Wallet init js
*/

function getColors(cssVars) {
    const colorValues = cssVars.map((variable) =>
        getComputedStyle(document.documentElement).getPropertyValue(variable).trim()
    );
    return colorValues;
}

document.querySelectorAll('input[name="data-theme-colors"]').forEach((radio) => {
    radio.addEventListener("change", function () {
        setTimeout(() => {
            renderCharts(this.value);
        }, 0);
    });
});

var walletOverviewChart = null;
var walletChart = null;

function renderCharts() {
    // ========================
    // Wallet Overview Chart
    // ========================
    var options = {
        chart: {
            type: 'line',
            height: 280,
            toolbar: { show: false }
        },
        colors: getColors(["--pe-light", "--pe-primary"]),
        stroke: {
            curve: 'smooth',
            width: [2, 2],
            dashArray: [5, 0] // dashed for Outcome, solid for Income
        },
        series: [
            {
                name: 'Outcome',
                data: [70, 150, 110, 200, 80, 170, 150, 210, 70, 180]
            },
            {
                name: 'Income',
                data: [100, 200, 160, 250, 180, 100, 210, 250, 160, 250]
            }
        ],
        xaxis: {
            categories: ['02', '04', '08', '12', '16', '20', '22', '24', '28', '30'],
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            min: 0,
            max: 280,
            tickAmount: 4,
            labels: {
                formatter: (val) => `$${val}`
            }
        },
        grid: {
            borderColor: '#f1f3f5',
            strokeDashArray: 4
        },
        markers: {
            hover: { size: 4 }
        },
        legend: { show: false }
    };

    // Destroy old chart & render new one
    if (walletOverviewChart) walletOverviewChart.destroy();
    walletOverviewChart = new ApexCharts(document.querySelector("#walletOverviewChart"), options);
    walletOverviewChart.render();

    // ========================
    // Sparkline Chart
    // ========================
    var wallet_chart = {
        chart: {
            id: 'sparkline1',
            type: 'line',
            height: 80,
            width: 120,
            sparkline: { enabled: true }
        },
        series: [
            {
                name: 'Wallet Trend',
                data: [22, 36, 55, 40, 55, 30, 65, 50, 30, 40]

            }
        ],
        stroke: {
            curve: 'smooth',
            width: 2
        },
        markers: { size: 0 },
        tooltip: {
            fixed: { enabled: true, position: 'right' },
            x: { show: false },
            y: {
                formatter: (val) => `$${val}`
            }
        },
        colors: getColors(["--pe-secondary"]),
    };

    // Destroy old sparkline & render new one
    if (walletChart) walletChart.destroy();
    walletChart = new ApexCharts(document.querySelector("#wallet_chart"), wallet_chart);
    walletChart.render();

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
    new AirDatepicker('#expiryDate', {
        autoClose: false,
        dateFormat: 'dd/MM/yyyy',
        locale: localeEn,
    });
    let cardTypeChoice = document.getElementById('cardType');
    if (cardTypeChoice) {
        const choices = new Choices('#cardType', {
            placeholderValue: 'Select Type',
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            itemSelectText: 'Press to select',
        });
    }
}

// Run on page load
window.addEventListener("DOMContentLoaded", renderCharts);
