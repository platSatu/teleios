/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Social Media init js
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
var spark1 = null;
var spark2 = null;
var spark3 = null;
var spark4 = null;

function renderCharts() {

    // Followers Growth
    var [primaryColor, lightColor] = getColors(["--pe-primary", "--pe-dark"]);

    if (revenueChart) revenueChart.destroy();

    revenueChart = new ApexCharts(document.querySelector("#instagramFollowerChart"), {
        chart: {
            height: 280,
            type: 'line',
            toolbar: { show: false },
            dropShadow: {
                enabled: true,
                enabledOnSeries: [0],
                color: primaryColor,
                top: 16,
                left: 0,
                blur: 6,
                opacity: 0.5
            }
        },
        grid: {
            padding: { top: 0, right: 0, bottom: 0, left: 0 },
            borderColor: '#eee',
        },
        series: [
            {
                name: 'Follower Growth',
                type: 'line',
                data: [65, 48, 72, 56, 85, 40, 70, 50, 65, 45, 80]
            },
            {
                name: 'Posts',
                type: 'bar',
                data: [25, 20, 15, 35, 30, 20, 25, 15, 30, 20, 25]
            }
        ],
        stroke: {
            width: [2, 0],
            curve: 'smooth'
        },
        colors: [primaryColor, lightColor],
        fill: {
            type: ['solid', 'solid'],
            opacity: [1, 0.1]
        },
        plotOptions: {
            bar: {
                columnWidth: '35%',
                borderRadius: 6
            }
        },
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
            labels: {
                style: {
                    fontSize: '13px',
                    colors: '#6c757d'
                }
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 5,
            labels: {
                formatter: val => val.toFixed(0),
                style: {
                    fontSize: '13px',
                    colors: '#6c757d'
                }
            }
        },
        dataLabels: { enabled: false },
        grid: {
            borderColor: '#f1f3f5',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } }
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (val, opts) =>
                    opts.seriesIndex === 0 ? val + '% growth' : val + ' posts'
            }
        },
        legend: { show: false },
        responsive: [
            {
                breakpoint: 768,
                options: {
                    chart: { height: 350 },
                    plotOptions: { bar: { columnWidth: '60%' } }
                }
            },
            {
                breakpoint: 576,
                options: {
                    chart: { height: 300 },
                    plotOptions: { bar: { columnWidth: '70%' } }
                }
            }
        ]
    });

    revenueChart.render();

    // Platform Traffic
    (function (H) {
        H.seriesTypes.pie.prototype.animate = function (init) {
            const series = this,
                chart = series.chart,
                points = series.points,
                {
                    animation
                } = series.options,
                {
                    startAngleRad
                } = series;

            function fanAnimate(point, startAngleRad) {
                const graphic = point.graphic,
                    args = point.shapeArgs;

                if (graphic && args) {

                    graphic
                        // Set inital animation values
                        .attr({
                            start: startAngleRad,
                            end: startAngleRad,
                            opacity: 1
                        })
                        // Animate to the final position
                        .animate({
                            start: args.start,
                            end: args.end
                        }, {
                            duration: animation.duration / points.length
                        }, function () {
                            // On complete, start animating the next point
                            if (points[point.index + 1]) {
                                fanAnimate(points[point.index + 1], args.end);
                            }
                            // On the last point, fade in the data labels, then
                            // apply the inner size
                            if (point.index === series.points.length - 1) {
                                series.dataLabelsGroup.animate({
                                    opacity: 1
                                },
                                    void 0,
                                    function () {
                                        points.forEach(point => {
                                            point.opacity = 1;
                                        });
                                        series.update({
                                            enableMouseTracking: true
                                        }, false);
                                        chart.update({
                                            plotOptions: {
                                                pie: {
                                                    innerSize: '60%',
                                                    borderRadius: 8
                                                }
                                            }
                                        });
                                    });
                            }
                        });
                }
            }

            if (init) {
                // Hide points on init
                points.forEach(point => {
                    point.opacity = 0;
                });
            } else {
                fanAnimate(points[0], startAngleRad);
            }
        };
    }(Highcharts));

    Highcharts.chart('platformTraffic', {
        chart: {
            type: 'pie',
            spacing: [0, 0, 0, 0], // Remove all outer spacing
            margin: [0, 0, 0, 0],  // Remove all margins
            height: '50%',        // Use full height of container
            backgroundColor: null
        },
        responsive: {
            rules: [
                {
                    condition: {
                        maxWidth: 1200 // xl and xxl
                    },
                    chartOptions: {
                        chart: {
                            height: '76%'
                        }
                    }
                }
            ]
        },
        title: {
            text: null
        },
        tooltip: {
            headerFormat: '',
            pointFormat:
                '<span style="color:{point.color}">\u25cf</span> {point.name}: <b>{point.percentage:.1f}%</b>'
        },
        accessibility: {
            point: {
                valueSuffix: '%'
            }
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                borderWidth: 2,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b><br>{point.percentage:.1f}%',
                    distance: 20
                },
                center: ['50%', '50%'], // Fully center the chart
                size: '100%'            // Make it expand to fit container
            }
        },
        colors: getColors([
            "--pe-primary",
            "--pe-secondary",
            "--pe-success",
            "--pe-info",
            "--pe-warning"
        ]),
        series: [
            {
                enableMouseTracking: false,
                animation: {
                    duration: 2000
                },
                colorByPoint: true,
                data: [
                    { name: 'YouTube', y: 21.3 },
                    { name: 'Facebook', y: 18.7 },
                    { name: 'Twitter', y: 20.2 },
                    { name: 'LinkedIn', y: 14.2 },
                    { name: 'Instagram', y: 25.6 }
                ]
            }
        ]
    });

    // Sample sparkline data
    var sparklineData1 = [34, 44, 54, 32, 56, 42, 66, 31, 43, 39, 62, 51, 35, 41, 50, 27, 63, 53, 61, 32, 54, 43, 39, 46];
    var sparklineData2 = [54, 54, 42, 42, 12, 53, 41, 41, 45, 26, 42, 75, 41, 42, 60, 59, 38, 33, 30, 22, 44, 51, 29, 35];
    var sparklineData3 = [34, 75, 45, 33, 41, 43, 45, 74, 44, 20, 63, 50, 45, 75, 49, 28, 75, 54, 60, 48, 53, 75, 40, 47];
    var sparklineData4 = [53, 42, 43, 54, 61, 52, 42, 33, 44, 48, 43, 59, 31, 49, 59, 58, 39, 34, 29, 23, 45, 50, 30, 55];


    // Dynamic sparkline chart options with unique colors
    const sparkOptions = (title, data, colorVar) => ({
        chart: {
            type: 'area',
            height: 131,
            sparkline: { enabled: true }
        },
        stroke: {
            curve: 'straight',
            width: 2
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                inverseColors: false,
                opacityFrom: 0.5,
                opacityTo: 0.1,
                stops: [0, 100]
            }
        },
        colors: getColors([colorVar]),
        series: [{
            name: title,
            data: data
        }],
        labels: [...Array(24).keys()].map(n => `2018-09-${(n + 1).toString().padStart(2, '0')}`),
        yaxis: { min: 0 },
        xaxis: { type: 'datetime' },
    });

    // Destroy existing if already rendered
    if (spark1) spark1.destroy();
    if (spark2) spark2.destroy();
    if (spark3) spark3.destroy();
    if (spark4) spark4.destroy();

    // Create charts with unique colors
    spark1 = new ApexCharts(document.querySelector("#spark1"), sparkOptions("Sales", sparklineData1, "--pe-secondary"));
    spark2 = new ApexCharts(document.querySelector("#spark2"), sparkOptions("Expenses", sparklineData2, "--pe-success"));
    spark3 = new ApexCharts(document.querySelector("#spark3"), sparkOptions("Profits", sparklineData3, "--pe-warning"));
    spark4 = new ApexCharts(document.querySelector("#spark4"), sparkOptions("Total Earned", sparklineData4, "--pe-primary"));

    spark1.render();
    spark2.render();
    spark3.render();
    spark4.render();
}
window.addEventListener("DOMContentLoaded", renderCharts);

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

// Followers by Country
var worldemapmarkers = new jsVectorMap({
    selector: "#world-map",
    map: 'world',
    zoomOnScroll: false,
    selectedMarkers: [0, 1], // e.g. Highlight Australia & Canada
    regionStyle: {
        initial: {
            stroke: "#9599ad",
            strokeWidth: 0.25,
            fillOpacity: 1,
        },
    },
    markersSelectable: true,
    markers: [
        { name: "Australia", coords: [-25.2744, 133.7751] },
        { name: "Canada", coords: [56.1304, -106.3468] },
        { name: "Italy", coords: [41.8719, 12.5674] },
        { name: "United Kingdom", coords: [55.3781, -3.4360] },
    ],
    markerStyle: {
        initial: {
            fill: "#adb5bd"
        },
        selected: { fill: "var(--bs-primary)" },
        hover: { fill: "var(--bs-primary)" },
    },
    labels: {
        markers: {
            render: function (marker) {
                return marker.name;
            }
        }
    }
});

// Added better initialization
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        renderCharts();
    }, 250);
});
