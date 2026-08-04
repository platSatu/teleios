/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Credit Card init js
*/

function getColors(cssVars) {
    const colorValues = cssVars.map((variable) =>
        getComputedStyle(document.documentElement).getPropertyValue(variable).trim()
    );
    return colorValues;
}
function getRgbaColors(cssVars) {
    return cssVars.map((item) => {
        // Split by comma, trim spaces
        const isArray = item.split(',');
        if (isArray.length > 1) {
            const [varName, alpha] = item.split(',').map(s => s.trim());
            // Get the RGB value from the CSS variable
            const rgb = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
            // If rgb is empty, fallback to a default
            if (!rgb) return `rgba(0,0,0,${alpha || 1})`;
            // Compose the rgba string
            return `rgba(${rgb},${alpha || 1})`;
        } else {
            return getComputedStyle(document.documentElement).getPropertyValue(item).trim();
        }
    });
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

var spendingAnalytics = null;
var allExpenses = null;

function renderCharts() {

    // Weekly Expenses
    var optionsBar = {
        chart: {
            type: 'bar',
            height: 360,
            width: '100%',
            stacked: true,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '18%',
            }
        },
        fill: {
            opacity: [1, 0.1],
        },
        colors: getColors(["--pe-primary", "--pe-dark"]),
        dataLabels: {
            enabled: false
        },
        series: [
            {
                name: "Spending",
                data: [22, 35, 50, 43, 60, 58, 39, 70, 65, 72, 48, 56, 40, 36, 66, 78, 54, 49, 80, 60, 75, 30, 20, 65, 34, 68, 59, 73, 62, 51]
            },
            {
                name: "Reference",
                data: Array(30).fill(100)
            }
        ],
        xaxis: {
            categories: Array.from({ length: 30 }, (_, i) => `${i + 1}`),
            labels: {
                show: true,
                offsetY: 5,
                style: {
                    fontSize: '13px',
                    fontWeight: 500,
                },
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 4,
            labels: {
                style: {
                    fontSize: '13px',
                    fontWeight: 500,
                },
                formatter: val => `$${val.toFixed(0)}` // ✅ Add "$"
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        grid: {
            show: false
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: val => `$${val}` // ✅ Add "$" in tooltip
            }
        },
        legend: {
            show: false
        }
    };

    spendingAnalytics ? spendingAnalytics.destroy() : null;
    spendingAnalytics = new ApexCharts(document.querySelector('#spending-analytics'), optionsBar);
    spendingAnalytics.render();

    // All Expenses
    var options = {
        series: [25, 40, 35, 20],
        labels: ['Housing', 'Groceries', 'Utilities', 'Other'],
        chart: {
            width: 315,
            type: 'donut',
        },
        dataLabels: {
            enabled: false
        },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
                legend: {
                    show: false
                }
            }
        }],
        colors: getRgbaColors(["--pe-primary-rgb, 1", "--pe-primary-rgb, 0.8", "--pe-primary-rgb, 0.4", "--pe-primary-rgb, 0.2"]),
        legend: {
            position: 'right',
            offsetY: 0,
            height: 230,
        }
    };

    allExpenses ? allExpenses.destroy() : null;
    allExpenses = new ApexCharts(document.querySelector("#allExpenses"), options);
    allExpenses.render();

}
window.addEventListener("DOMContentLoaded", renderCharts);

// Credicard Swiper
var swiper3 = new Swiper(".CrediCards", {
    grabCursor: true,
    loop: true,
    autoplay: {
        delay: 3000,
        disableOnInteraction: false,
    },
    speed: 500,
    effect: "creative",
    center: true,
    creativeEffect: {
        prev: {
            shadow: false,
            translate: ["0%", 0, -1],
        },
        next: {
            translate: ["100%", 0, 0],
        },
    },
});

// Added better initialization
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        renderCharts();
    }, 250);
});