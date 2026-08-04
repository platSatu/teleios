/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: restaurant analytics details init js
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

function renderCharts() {

    // Revenue
    const defaultData = 'https://demo-live-data.highcharts.com/time-data.csv';
    const urlInput = document.getElementById('fetchURL');
    const pollingCheckbox = document.getElementById('enablePolling');
    const pollingInput = document.getElementById('pollingTime');

    const [primaryColor] = getColors(['--pe-primary']);
    const safePrimary = primaryColor || '#e36666';

    function createChart() {
        const pollingEnabled = pollingCheckbox.checked === true;
        const refreshRate = parseInt(pollingInput.value, 10) || 1;

        const config = {
            chart: {
                type: 'areaspline',
                backgroundColor: 'transparent'
            },
            title: { text: null },
            lang: { locale: 'en-GB' },
            tooltip: {
                shared: true,
                valuePrefix: '₹ '
            },
            accessibility: {
                announceNewData: {
                    enabled: true,
                    minAnnounceInterval: 15000,
                    announcementFormatter: function (allSeries, newSeries, newPoint) {
                        if (newPoint) return 'New point added. Value: ' + newPoint.y;
                        return false;
                    }
                }
            },
            yAxis: {
                title: { text: null },
                gridLineDashStyle: 'Dash',
                gridLineColor: '#ccc'
            },
            xAxis: {
                categories: !pollingEnabled
                    ? ['10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM']
                    : undefined,
                title: { text: null },
                lineColor: '#ccc',
                tickColor: '#ccc'
            },
            plotOptions: {
                areaspline: {
                    color: safePrimary,
                    fillColor: {
                        linearGradient: { x1: 0, x2: 0, y1: 0, y2: 1 },
                        stops: [
                            [0, safePrimary + '40'],
                            [1, safePrimary + '00']
                        ]
                    },
                    threshold: null,
                    marker: {
                        lineWidth: 1,
                        lineColor: null,
                        fillColor: 'white'
                    }
                }
            }
        };

        if (pollingEnabled) {
            Highcharts.chart('salesSummaryChart', {
                ...config,
                data: {
                    csvURL: urlInput.value,
                    enablePolling: true,
                    dataRefreshRate: refreshRate
                }
            });
        } else {
            Highcharts.chart('salesSummaryChart', {
                ...config,
                series: [{
                    name: 'Revenue',
                    data: [1200, 1400, 1800, 1600, 2100, 1900, 2400, 2600, 2800]
                }]
            });
        }
    }

    urlInput.value = defaultData;

    pollingCheckbox.onchange =
        urlInput.onchange =
        pollingInput.onchange = createChart;

    createChart();

    // Wallet
    Highcharts.chart('wallet', {
        chart: {
            type: 'pie',
            custom: {},
            events: {
                render() {
                    const chart = this,
                        series = chart.series[0];
                    let customLabel = chart.options.chart.custom.label;

                    if (!customLabel) {
                        customLabel = chart.options.chart.custom.label =
                            chart.renderer.label(
                                'Total<br/>' +
                                '<strong>2 877 820</strong>'
                            )
                                .css({
                                    color:
                                        'var(--highcharts-neutral-color-100, #000)',
                                    textAnchor: 'middle'
                                })
                                .add();
                    }

                    const x = series.center[0] + chart.plotLeft,
                        y = series.center[1] + chart.plotTop -
                            (customLabel.attr('height') / 2);

                    customLabel.attr({
                        x,
                        y
                    });
                    // Set font size based on chart diameter
                    customLabel.css({
                        fontSize: `${series.center[2] / 12}px`
                    });
                }
            }
        },
        accessibility: {
            point: {
                valueSuffix: '%'
            }
        },
        title: {
            text: null
        },
        tooltip: {
            pointFormat: '{series.name}: <b>{point.percentage:.0f}%</b>'
        },
        legend: {
            enabled: false
        },
        colors: getColors(["--pe-primary", "--pe-info", "--pe-success", "--pe-warning"]),
        plotOptions: {
            series: {
                allowPointSelect: true,
                cursor: 'pointer',
                borderRadius: 8,
                dataLabels: [{
                    enabled: true,
                    distance: 20,
                    format: '{point.name}'
                }, {
                    enabled: true,
                    distance: -15,
                    format: '{point.percentage:.0f}%',
                    style: {
                        fontSize: '0.9em'
                    }
                }],
                showInLegend: true
            }
        },
        series: [{
            name: 'Registrations',
            colorByPoint: true,
            innerSize: '75%',
            data: [{
                name: 'Wraps',
                y: 23.9
            }, {
                name: 'Drinks',
                y: 12.6
            }, {
                name: 'Pizza',
                y: 37.0
            }, {
                name: 'Burger',
                y: 26.4
            }]
        }]
    });

    // Order Status
    Highcharts.chart('orderStatusChart', {
        chart: {
            type: 'column'
        },
        title: {
            text: null,
        },
        xAxis: {
            categories: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled']
        },
        credits: {
            enabled: false
        },
        plotOptions: {
            column: {
                borderRadius: '25%'
            }
        },
        colors: getColors(["--pe-primary", "--pe-light", "--pe-dark"]),
        series: [{
            name: 'John',
            data: [5, 3, 4, 7, 2]
        }, {
            name: 'Jane',
            data: [2, -2, -3, 2, 1]
        }, {
            name: 'Joe',
            data: [3, 4, 4, -2, 5]
        }]
    });

}

window.addEventListener("DOMContentLoaded", renderCharts);

