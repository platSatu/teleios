/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: highchart.init.js
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

function renderCharts() {
    // Line chart
    Highcharts.chart('lineChart', {

        title: {
            text: null
        },

        yAxis: {
            title: {
                text: 'Number of Employees'
            }
        },

        xAxis: {
            accessibility: {
                rangeDescription: 'Range: 2010 to 2022'
            }
        },

        plotOptions: {
            series: {
                label: {
                    connectorAllowed: false
                },
                pointStart: 2010
            }
        },
        colors: getColors(["--pe-primary", "--pe-secondary", "--pe-success", "--pe-info", "--pe-danger"]),
        series: [{
            name: 'Installation & Developers',
            data: [
                43934, 48656, 65165, 81827, 112143, 142383,
                171533, 165174, 155157, 161454, 154610, 168960, 171558
            ]
        }, {
            name: 'Manufacturing',
            data: [
                24916, 37941, 29742, 29851, 32490, 30282,
                38121, 36885, 33726, 34243, 31050, 33099, 33473
            ]
        }, {
            name: 'Sales & Distribution',
            data: [
                11744, 30000, 16005, 19771, 20185, 24377,
                32147, 30912, 29243, 29213, 25663, 28978, 30618
            ]
        }, {
            name: 'Operations & Maintenance',
            data: [
                null, null, null, null, null, null, null,
                null, 11164, 11218, 10077, 12530, 16585
            ]
        }, {
            name: 'Other',
            data: [
                21908, 5548, 8105, 11248, 8989, 11816, 18274,
                17300, 13053, 11906, 10073, 11471, 11648
            ]
        }],

        responsive: {
            rules: [{
                condition: {
                    maxWidth: 500
                },
                chartOptions: {
                    legend: {
                        layout: 'horizontal',
                        align: 'center',
                        verticalAlign: 'bottom'
                    }
                }
            }]
        }
    });

    // With data label
    Highcharts.chart('withDartaLabels', {
        chart: {
            type: 'line'
        },
        title: {
            text: null
        },
        xAxis: {
            categories: [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep',
                'Oct', 'Nov', 'Dec'
            ]
        },
        yAxis: {
            title: {
                text: 'Temperature (°C)'
            }
        },
        plotOptions: {
            line: {
                dataLabels: {
                    enabled: true
                },
                enableMouseTracking: false
            }
        },
        colors: getColors(["--pe-primary", "--pe-secondary"]),
        series: [{
            name: 'Reggane',
            data: [
                16.0, 18.2, 23.1, 27.9, 32.2, 36.4, 39.8, 38.4, 35.5, 29.2,
                22.0, 17.8
            ]
        }, {
            name: 'Tallinn',
            data: [
                -2.9, -3.6, -0.6, 4.8, 10.2, 14.5, 17.6, 16.5, 12.0, 6.5,
                2.0, -0.9
            ]
        }]
    });

    // Area-spline
    Highcharts.chart('areaSpline', {
        chart: {
            type: 'areaspline'
        },
        title: {
            text: null
        },
        legend: {
            layout: 'vertical',
            align: 'left',
            verticalAlign: 'top',
            x: 120,
            y: 70,
            floating: true,
            borderWidth: 1,
        },
        xAxis: {
            plotBands: [{
                from: 2020,
                to: 2023,
                color: 'rgba(68, 170, 213, .2)'
            }]
        },
        yAxis: {
            title: {
                text: 'Quantity'
            }
        },
        tooltip: {
            shared: true,
            headerFormat: '<b>Hunting season starting autumn {point.x}</b><br>'
        },
        credits: {
            enabled: false
        },
        plotOptions: {
            series: {
                pointStart: 2000
            },
            areaspline: {
                fillOpacity: 0.5
            }
        },
        colors: getColors(["--pe-primary", "--pe-info"]),
        series: [{
            name: 'Moose',
            data:
                [
                    38000,
                    37300,
                    37892,
                    38564,
                    36770,
                    36026,
                    34978,
                    35657,
                    35620,
                    35971,
                    36409,
                    36435,
                    34643,
                    34956,
                    33199,
                    31136,
                    30835,
                    31611,
                    30666,
                    30319,
                    31766,
                    29278,
                    27487,
                    26007
                ]
        }, {
            name: 'Deer',
            data:
                [
                    22534,
                    23599,
                    24533,
                    25195,
                    25896,
                    27635,
                    29173,
                    32646,
                    35686,
                    37709,
                    39143,
                    36829,
                    35031,
                    36202,
                    35140,
                    33718,
                    37773,
                    42556,
                    43820,
                    46445,
                    50048,
                    52804,
                    49317,
                    52490
                ]
        }]
    });

    // Area with missing points
    Highcharts.chart('areaWithMissingPoints', {
        chart: {
            type: 'area'
        },
        title: {
            text: null
        },
        subtitle: {
            text: '* Missing data for Yasin in 2019',
            align: 'right',
            verticalAlign: 'bottom'
        },
        legend: {
            layout: 'vertical',
            align: 'left',
            verticalAlign: 'top',
            x: 150,
            y: 60,
            floating: true,
            borderWidth: 1,
        },
        yAxis: {
            title: {
                text: 'Amount'
            }
        },
        plotOptions: {
            series: {
                pointStart: 2016
            },
            area: {
                fillOpacity: 0.5
            }
        },
        credits: {
            enabled: false
        },
        colors: getColors(["--pe-primary", "--pe-secondary"]),
        series: [{
            name: 'Arvid',
            data: [11, 11, 8, 13, 12, 14, 4, 12]
        }, {
            name: 'Yasin',
            data: [10, 10, 8, null, 8, 6, 4, 8]
        }]
    });

    // Basic column
    Highcharts.chart('basicColumn', {
        chart: {
            type: 'column'
        },
        title: {
            text: null
        },
        xAxis: {
            categories: ['USA', 'China', 'Brazil', 'EU', 'Argentina', 'India'],
            crosshair: true,
            accessibility: {
                description: 'Countries'
            }
        },
        yAxis: {
            min: 0,
            title: {
                text: '1000 metric tons (MT)'
            }
        },
        colors: getColors(["--pe-primary", "--pe-dark"]),
        tooltip: {
            valueSuffix: ' (1000 MT)'
        },
        plotOptions: {
            column: {
                pointPadding: 0.2,
                borderWidth: 0
            }
        },
        series: [
            {
                name: 'Corn',
                data: [387749, 280000, 129000, 64300, 54000, 34300]
            },
            {
                name: 'Wheat',
                data: [45321, 140000, 10000, 140500, 19500, 113500]
            }
        ]
    });

    // Bar with negative stack
    Highcharts.Templating.helpers.abs = value => Math.abs(value);

    // Age categories
    const categories = [
        '0-4', '5-9', '10-14', '15-19', '20-24', '25-29', '30-34', '35-40', '40-45',
        '45-49', '50-54', '55-59', '60-64', '65-69', '70-74', '75-79', '80-84',
        '80+'
    ];

    Highcharts.chart('barWithNegativeStack', {
        chart: {
            type: 'bar'
        },
        title: {
            text: null
        },
        accessibility: {
            point: {
                valueDescriptionFormat: '{index}. Age {xDescription}, {value}%.'
            }
        },
        xAxis: [{
            categories: categories,
            reversed: false,
            labels: {
                step: 1
            },
            accessibility: {
                description: 'Age (male)'
            }
        }, { // mirror axis on right side
            opposite: true,
            reversed: false,
            categories: categories,
            linkedTo: 0,
            labels: {
                step: 1
            },
            accessibility: {
                description: 'Age (female)'
            }
        }],
        colors: getColors(["--pe-primary", "--pe-secondary"]),
        yAxis: {
            title: {
                text: null
            },
            labels: {
                format: '{abs value}%'
            },
            accessibility: {
                description: 'Percentage population',
                rangeDescription: 'Range: 0 to 5%'
            }
        },

        plotOptions: {
            series: {
                stacking: 'normal',
                borderRadius: '50%'
            }
        },

        tooltip: {
            format: '<b>{series.name}, age {point.category}</b><br/>' +
                'Population: {(abs point.y):.2f}%'
        },

        series: [{
            name: 'Male',
            data: [
                -1.38, -2.09, -2.45, -2.71, -2.97,
                -3.69, -4.04, -3.81, -4.19, -4.61,
                -4.56, -4.21, -3.53, -2.55, -1.82,
                -1.46, -0.78, -0.71
            ]
        }, {
            name: 'Female',
            data: [
                1.35, 1.98, 2.43, 2.39, 2.71,
                3.02, 3.50, 3.52, 4.03, 4.40,
                4.17, 3.88, 3.29, 2.42, 1.80,
                1.39, 0.99, 1.15
            ]
        }]
    });

    // Pie chart
    Highcharts.chart('pieChart', {
        chart: {
            type: 'pie',
            zooming: {
                type: 'xy'
            },
            panning: {
                enabled: true,
                type: 'xy'
            },
            panKey: 'shift'
        },
        title: {
            text: null
        },
        tooltip: {
            valueSuffix: '%'
        },
        colors: getColors(["--pe-warning", "--pe-primary", "--pe-success", "--pe-info", "--pe-secondary"]),
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: [{
                    enabled: true,
                    distance: 20
                }, {
                    enabled: true,
                    distance: -40,
                    format: '{point.percentage:.1f}%',
                    style: {
                        fontSize: '1.2em',
                        textOutline: 'none',
                        opacity: 0.7
                    },
                    filter: {
                        operator: '>',
                        property: 'percentage',
                        value: 10
                    }
                }]
            }
        },
        series: [
            {
                name: 'Percentage',
                colorByPoint: true,
                data: [
                    {
                        name: 'Water',
                        y: 55.02
                    },
                    {
                        name: 'Fat',
                        sliced: true,
                        selected: true,
                        y: 26.71
                    },
                    {
                        name: 'Carbohydrates',
                        y: 1.09
                    },
                    {
                        name: 'Protein',
                        y: 15.5
                    },
                    {
                        name: 'Ash',
                        y: 1.68
                    }
                ]
            }
        ]
    });

    // Donut chart
    Highcharts.chart('donutChart', {
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
                name: 'EV',
                y: 23.9
            }, {
                name: 'Hybrids',
                y: 12.6
            }, {
                name: 'Diesel',
                y: 37.0
            }, {
                name: 'Petrol',
                y: 26.4
            }]
        }]
    });

    // Pie chart with custom entrance animation

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
                                                    innerSize: '40%',
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

    Highcharts.chart('pieChartWithAnimation', {
        chart: {
            type: 'pie'
        },
        title: {
            text: null
        },
        tooltip: {
            headerFormat: '',
            pointFormat:
                '<span style="color:{point.color}">\u25cf</span> ' +
                '{point.name}: <b>{point.percentage:.1f}%</b>'
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
                    format: '<b>{point.name}</b><br>{point.percentage}%',
                    distance: 20
                }
            }
        },
        colors: getColors(["--pe-primary", "--pe-secondary", "--pe-success", "--pe-info", "--pe-warning"]),
        series: [{
            // Disable mouse tracking on load, enable after custom animation
            enableMouseTracking: false,
            animation: {
                duration: 2000
            },
            colorByPoint: true,
            data: [{
                name: 'Customer Support',
                y: 21.3
            }, {
                name: 'Development',
                y: 18.7
            }, {
                name: 'Sales',
                y: 20.2
            }, {
                name: 'Marketing',
                y: 14.2
            }, {
                name: 'Other',
                y: 25.6
            }]
        }]
    });

    // 3D bubbles
    Highcharts.chart('threeDBubbles', {
        chart: {
            type: 'bubble',
            plotBorderWidth: 1,
            zooming: {
                type: 'xy'
            }
        },
        title: {
            text: null
        },
        xAxis: {
            gridLineWidth: 1,
            accessibility: {
                rangeDescription: 'Range: 0 to 100.'
            }
        },
        yAxis: {
            startOnTick: false,
            endOnTick: false,
            accessibility: {
                rangeDescription: 'Range: 0 to 100.'
            }
        },
        series: [{
            data: [
                [9, 81, 63],
                [98, 5, 89],
                [51, 50, 73],
                [41, 22, 14],
                [58, 24, 20],
                [78, 37, 34],
                [55, 56, 53],
                [18, 45, 70],
                [42, 44, 28],
                [3, 52, 59],
                [31, 18, 97],
                [79, 91, 63],
                [93, 23, 23],
                [44, 83, 22]
            ],
            marker: {
                fillColor: {
                    radialGradient: { cx: 0.4, cy: 0.3, r: 0.7 },
                    stops: [
                        [0, 'white'],
                        [1, Highcharts.getOptions().colors[0]]
                    ]
                }
            }
        }, {
            data: [
                [42, 38, 20],
                [6, 18, 1],
                [1, 93, 55],
                [57, 2, 90],
                [80, 76, 22],
                [11, 74, 96],
                [88, 56, 10],
                [30, 47, 49],
                [57, 62, 98],
                [4, 16, 16],
                [46, 10, 11],
                [22, 87, 89],
                [57, 91, 82],
                [45, 15, 98]
            ],
            marker: {
                fillColor: {
                    radialGradient: { cx: 0.4, cy: 0.3, r: 0.7 },
                    stops: [
                        [0, 'rgba(255,255,255,0.5)'],
                        [
                            1,
                            Highcharts.color(
                                Highcharts.getOptions().colors[1]
                            ).setOpacity(0.5).get('rgba')
                        ]
                    ]
                }
            }
        }]

    });

    // Bubble chart
    Highcharts.chart('bubbleChart', {

        chart: {
            type: 'bubble',
            plotBorderWidth: 1,
            zooming: {
                type: 'xy'
            }
        },

        legend: {
            enabled: false
        },

        title: {
            text: null
        },

        accessibility: {
            point: {
                valueDescriptionFormat: '{index}. {point.name}, fat: {point.x}g, ' +
                    'sugar: {point.y}g, obesity: {point.z}%.'
            }
        },

        xAxis: {
            gridLineWidth: 1,
            title: {
                text: 'Daily fat intake'
            },
            labels: {
                format: '{value} gr'
            },
            plotLines: [{
                dashStyle: 'dot',
                width: 2,
                value: 65,
                label: {
                    rotation: 0,
                    y: 15,
                    style: {
                        fontStyle: 'italic'
                    },
                    text: 'Safe fat intake 65g/day'
                },
                zIndex: 3
            }],
            accessibility: {
                rangeDescription: 'Range: 60 to 100 grams.'
            }
        },

        yAxis: {
            startOnTick: false,
            endOnTick: false,
            title: {
                text: 'Daily sugar intake'
            },
            labels: {
                format: '{value} gr'
            },
            maxPadding: 0.2,
            plotLines: [{
                dashStyle: 'dot',
                width: 2,
                value: 50,
                label: {
                    align: 'right',
                    style: {
                        fontStyle: 'italic'
                    },
                    text: 'Safe sugar intake 50g/day',
                    x: -10
                },
                zIndex: 3
            }],
            accessibility: {
                rangeDescription: 'Range: 0 to 160 grams.'
            }
        },

        tooltip: {
            useHTML: true,
            headerFormat: '<table>',
            pointFormat: '<tr><th colspan="2"><h3>{point.country}</h3></th></tr>' +
                '<tr><th>Fat intake:</th><td>{point.x}g</td></tr>' +
                '<tr><th>Sugar intake:</th><td>{point.y}g</td></tr>' +
                '<tr><th>Obesity (adults):</th><td>{point.z}%</td></tr>',
            footerFormat: '</table>',
            followPointer: true
        },

        plotOptions: {
            series: {
                dataLabels: {
                    enabled: true,
                    format: '{point.name}'
                }
            }
        },

        series: [{
            data: [
                { x: 95, y: 95, z: 13.8, name: 'BE', country: 'Belgium' },
                { x: 86.5, y: 102.9, z: 14.7, name: 'DE', country: 'Germany' },
                { x: 80.8, y: 91.5, z: 15.8, name: 'FI', country: 'Finland' },
                { x: 80.4, y: 102.5, z: 12, name: 'NL', country: 'Netherlands' },
                { x: 80.3, y: 86.1, z: 11.8, name: 'SE', country: 'Sweden' },
                { x: 78.4, y: 70.1, z: 16.6, name: 'ES', country: 'Spain' },
                { x: 74.2, y: 68.5, z: 14.5, name: 'FR', country: 'France' },
                { x: 73.5, y: 83.1, z: 10, name: 'NO', country: 'Norway' },
                { x: 71, y: 93.2, z: 24.7, name: 'UK', country: 'United Kingdom' },
                { x: 69.2, y: 57.6, z: 10.4, name: 'IT', country: 'Italy' },
                { x: 68.6, y: 20, z: 16, name: 'RU', country: 'Russia' },
                {
                    x: 65.5,
                    y: 126.4,
                    z: 35.3,
                    name:
                        'US',
                    country: 'United States'
                },
                { x: 65.4, y: 50.8, z: 28.5, name: 'HU', country: 'Hungary' },
                { x: 63.4, y: 51.8, z: 15.4, name: 'PT', country: 'Portugal' },
                { x: 64, y: 82.9, z: 31.3, name: 'NZ', country: 'New Zealand' }
            ],
            colorByPoint: true
        }]

    });

    // Dual axes, line and column
    Highcharts.chart('dualAxes', {
        chart: {
            zooming: {
                type: 'xy'
            }
        },
        title: {
            text: null,
            align: 'left'
        },
        credits: {
            text: 'Source: ' +
                '<a href="https://www.yr.no/nb/historikk/graf/5-97251/Norge/Finnmark/Karasjok/Karasjok?q=2023"' +
                'target="_blank">YR</a>'
        },
        xAxis: [{
            categories: [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ],
            crosshair: true
        }],
        yAxis: [{ // Primary yAxis
            labels: {
                format: '{value}°C'
            },
            title: {
                text: 'Temperature'
            },
            lineColor: Highcharts.getOptions().colors[1],
            lineWidth: 2
        }, { // Secondary yAxis
            title: {
                text: 'Precipitation'
            },
            labels: {
                format: '{value} mm'
            },
            lineColor: Highcharts.getOptions().colors[0],
            lineWidth: 2,
            opposite: true
        }],
        colors: getColors(["--pe-secondary", "--pe-primary"]),
        tooltip: {
            shared: true
        },
        legend: {
            align: 'left',
            verticalAlign: 'top'
        },
        series: [{
            name: 'Precipitation',
            type: 'column',
            yAxis: 1,
            data: [
                45.7, 37.0, 28.9, 17.1, 39.2, 18.9, 90.2, 78.5, 74.6,
                18.7, 17.1, 16.0
            ],
            tooltip: {
                valueSuffix: ' mm'
            }

        }, {
            name: 'Temperature',
            type: 'spline',
            data: [
                -11.4, -9.5, -14.2, 0.2, 7.0, 12.1, 13.5, 13.6, 8.2,
                -2.8, -12.0, -15.5
            ],
            tooltip: {
                valueSuffix: '°C'
            }
        }]
    });

    // Line styles
    Highcharts.chart('lineStyles', {
        chart: {
            type: 'spline'
        },

        legend: {
            symbolWidth: 40
        },

        title: {
            text: null,
            align: 'left'
        },

        yAxis: {
            title: {
                text: 'Percentage usage'
            },
            accessibility: {
                description: 'Percentage usage'
            }
        },
        colors: getColors(["--pe-secondary", "--pe-primary", "--pe-danger", "--pe-info", "--pe-warning", "--pe-success"]),
        xAxis: {
            title: {
                text: 'Time'
            },
            accessibility: {
                description: 'Time from December 2010 to September 2019'
            },
            categories: [
                'December 2010', 'May 2012', 'January 2014', 'July 2015',
                'October 2017', 'September 2019'
            ]
        },

        tooltip: {
            valueSuffix: '%',
            stickOnContact: true
        },

        plotOptions: {
            series: {
                point: {
                    events: {
                        click: function () {
                            window.location.href = this.series.options.website;
                        }
                    }
                },
                cursor: 'pointer',
                lineWidth: 2
            }
        },

        series: [
            {
                name: 'NVDA',
                data: [34.8, 43.0, 51.2, 41.4, 64.9, 72.4],
                website: 'https://www.nvaccess.org',
                accessibility: {
                    description: 'This is the most used screen reader in 2019.'
                }
            }, {
                name: 'JAWS',
                data: [69.6, 63.7, 63.9, 43.7, 66.0, 61.7],
                website: 'https://www.freedomscientific.com/Products/Blindness/JAWS',
                dashStyle: 'ShortDashDot',
            }, {
                name: 'VoiceOver',
                data: [20.2, 30.7, 36.8, 30.9, 39.6, 47.1],
                website: 'http://www.apple.com/accessibility/osx/voiceover',
                dashStyle: 'ShortDot',
            }, {
                name: 'Narrator',
                data: [null, null, null, null, 21.4, 30.3],
                website: 'https://support.microsoft.com/en-us/help/22798/windows-10-complete-guide-to-narrator',
                dashStyle: 'Dash',
            }, {
                name: 'ZoomText/Fusion',
                data: [6.1, 6.8, 5.3, 27.5, 6.0, 5.5],
                website: 'http://www.zoomtext.com/products/zoomtext-magnifierreader',
                dashStyle: 'ShortDot',
            }, {
                name: 'Other',
                data: [42.6, 51.5, 54.2, 45.8, 20.2, 15.4],
                website: 'http://www.disabled-world.com/assistivedevices/computer/screen-readers.php',
                dashStyle: 'ShortDash',
            }
        ],

        responsive: {
            rules: [{
                condition: {
                    maxWidth: 550
                },
                chartOptions: {
                    chart: {
                        spacingLeft: 3,
                        spacingRight: 3
                    },
                    legend: {
                        itemWidth: 150
                    },
                    xAxis: {
                        categories: [
                            'Dec. 2010', 'May 2012', 'Jan. 2014', 'July 2015',
                            'Oct. 2017', 'Sep. 2019'
                        ],
                        title: ''
                    },
                    yAxis: {
                        visible: false
                    }
                }
            }]
        }
    });

    // All instruments
    const chart = Highcharts.chart('allInstruments', {
        chart: {
            events: {
                render: function () {
                    this.series[0].points.forEach(function (point) {
                        if (point.x >= new Date('2022').getTime()) {
                            point.ttBelow = true;
                        }
                    });
                }
            }
        },
        title: {
            text: null,
            align: 'left',
            margin: 15
        },
        legend: {
            enabled: false
        },
        accessibility: {
            landmarkVerbosity: 'one',
            point: {
                describeNull: false
            }
        },
        yAxis: [{
            top: '0%',
            height: '72%',
            title: {
                text: 'Revenue (millions)'
            },
            lineWidth: 1
        }, {
            top: '85%',
            height: '15%',
            offset: 0,
            title: {
                text: 'Profit margin'
            },
            labels: {
                format: '{value}%'
            },
            plotBands: [{
                from: 0,
                to: 30,
            }]
        }],
        colors: getColors(["--pe-primary", "--pe-secondary"]),
        xAxis: {
            type: 'category',
            accessibility: {
                rangeDescription: '2017 to 2024'
            }
        },
        plotOptions: {
            series: {
                states: {
                    inactive: {
                        enabled: false
                    }
                }
            }
        },
        sonification: {
            duration: 9500,
            afterSeriesWait: 1100,
            events: {
                beforePlay: function (e) {
                    e.chart.sonification.speak('Revenue');
                },
                onSeriesEnd: function (e) {
                    if (e.series.index === 0) {
                        e.series.chart.sonification.speak('Profit margin', {}, 150);
                    }
                }
            },
            defaultInstrumentOptions: {
                mapping: {
                    playDelay: 750,
                    noteDuration: 400
                }
            }
        },
        data: {
            csv: document.getElementById('csv').textContent
        },
        series: [{
            type: 'spline',
            yAxis: 0,
            sonification: {
                tracks: [{
                    roundToMusicalNotes: false
                }]
            }
        }, {
            type: 'column',
            yAxis: 1,
            pointWidth: 30
        }]
    });

    document.getElementById('sonify').onclick = function () {
        chart.toggleSonify();
    };

    Object.keys(Highcharts.sonification.InstrumentPresets).forEach(function (preset) {
        const option = document.createElement('option');
        option.textContent = option.value = preset;
        document.getElementById('preset').appendChild(option);
    });

    document.getElementById('preset').onchange = function () {
        chart.update({
            sonification: {
                defaultInstrumentOptions: {
                    instrument: this.value
                }
            }
        }, false);
    };

    // Live Chart
    const defaultData = 'https://demo-live-data.highcharts.com/time-data.csv';
    const urlInput = document.getElementById('fetchURL');
    const pollingCheckbox = document.getElementById('enablePolling');
    const pollingInput = document.getElementById('pollingTime');

    function createChart() {
        Highcharts.chart('liveData', {
            chart: {
                type: 'areaspline'
            },
            lang: {
                locale: 'en-GB'
            },
            title: {
                text: null
            },
            accessibility: {
                announceNewData: {
                    enabled: true,
                    minAnnounceInterval: 15000,
                    announcementFormatter: function (
                        allSeries,
                        newSeries,
                        newPoint
                    ) {
                        if (newPoint) {
                            return 'New point added. Value: ' + newPoint.y;
                        }
                        return false;
                    }
                }
            },
            plotOptions: {
                areaspline: {
                    color: '#e36666',
                    fillColor: {
                        linearGradient: { x1: 0, x2: 0, y1: 0, y2: 1 },
                        stops: [
                            [0, '#e36666'],
                            [1, '#e3666600']
                        ]
                    },
                    threshold: null,
                    marker: {
                        lineWidth: 1,
                        lineColor: null,
                        fillColor: 'white'
                    }
                }
            },
            data: {
                csvURL: urlInput.value,
                enablePolling: pollingCheckbox.checked === true,
                dataRefreshRate: parseInt(pollingInput.value, 10)
            }
        });

        if (pollingInput.value < 1 || !pollingInput.value) {
            pollingInput.value = 1;
        }
    }

    urlInput.value = defaultData;

    // We recreate instead of using chart update to make sure the loaded CSV and such is completely gone.
    pollingCheckbox.onchange = urlInput.onchange =
        pollingInput.onchange = createChart;

    // Create the chart
    createChart();

    // Gauge series
    Highcharts.chart('gaugeSeries', {

        chart: {
            type: 'gauge',
            plotBackgroundColor: null,
            plotBackgroundImage: null,
            plotBorderWidth: 0,
            plotShadow: false,
            height: '80%'
        },

        title: {
            text: 'Speedometer'
        },

        pane: {
            startAngle: -90,
            endAngle: 89.9,
            background: null,
            center: ['50%', '75%'],
            size: '110%'
        },

        // the value axis
        yAxis: {
            min: 0,
            max: 200,
            tickPixelInterval: 72,
            tickPosition: 'inside',
            tickColor: Highcharts.defaultOptions.chart.backgroundColor || '#FFFFFF',
            tickLength: 20,
            tickWidth: 2,
            minorTickInterval: null,
            labels: {
                distance: 20,
                style: {
                    fontSize: '14px'
                }
            },
            lineWidth: 0,
            plotBands: [{
                from: 0,
                to: 130,
                color: '#55BF3B', // green
                thickness: 20,
                borderRadius: '50%'
            }, {
                from: 150,
                to: 200,
                color: '#DF5353', // red
                thickness: 20,
                borderRadius: '50%'
            }, {
                from: 120,
                to: 160,
                color: '#DDDF0D', // yellow
                thickness: 20
            }]
        },

        series: [{
            name: 'Speed',
            data: [80],
            tooltip: {
                valueSuffix: ' km/h'
            },
            dataLabels: {
                format: '{y} km/h',
                borderWidth: 0,
                color: (
                    Highcharts.defaultOptions.title &&
                    Highcharts.defaultOptions.title.style &&
                    Highcharts.defaultOptions.title.style.color
                ) || '#333333',
                style: {
                    fontSize: '16px'
                }
            },
            dial: {
                radius: '80%',
                backgroundColor: 'gray',
                baseWidth: 12,
                baseLength: '0%',
                rearLength: '0%'
            },
            pivot: {
                backgroundColor: 'gray',
                radius: 6
            }

        }]

    });

    // Add some life
    setInterval(() => {
        const chart = Highcharts.charts[0];
        if (chart && !chart.renderer.forExport) {
            const point = chart.series[0].points[0],
                inc = Math.round((Math.random() - 0.5) * 20);

            let newVal = point.y + inc;
            if (newVal < 0 || newVal > 200) {
                newVal = point.y - inc;
            }

            point.update(newVal);
        }

    }, 3000);

    // Tile map, honeycomb
    Highcharts.chart('tileMap', {
        chart: {
            type: 'tilemap',
            inverted: true,
            height: '80%'
        },

        accessibility: {
            description: 'A tile map represents the states of the USA by ' +
                'population in 2023. The hexagonal tiles are positioned to ' +
                'geographically echo the map of the USA. A color-coded legend ' +
                'states the population levels as below 1 million (beige), 1 to 5 ' +
                'million (orange), 5 to 20 million (pink) and above 20 million ' +
                '(hot pink). The chart is interactive, and the individual state ' +
                'data points are displayed upon hovering. Three states have a ' +
                'population of above 20 million: California (38.9 million), ' +
                'Texas (30.5 million) and Florida (22.6 million). The northern ' +
                'US region from Massachusetts in the Northwest to Illinois in ' +
                'the Midwest contains the highest concentration of states with a ' +
                'population of 5 to 20 million people. The southern US region ' +
                'from South Carolina in the Southeast to New Mexico in the ' +
                'Southwest contains the highest concentration of states with a ' +
                'population of 1 to 5 million people. 6 states have a population ' +
                'of less than 1 million people; these include Alaska, Delaware, ' +
                'Wyoming, North Dakota, South Dakota and Vermont. The state with ' +
                'the lowest population is Wyoming in the Northwest with 584,153 ' +
                'people.',
            screenReaderSection: {
                beforeChartFormat:
                    '<h5>{chartTitle}</h5>' +
                    '<div>{chartSubtitle}</div>' +
                    '<div>{chartLongdesc}</div>' +
                    '<div>{viewTableButton}</div>'
            },
            point: {
                valueDescriptionFormat: '{index}. {xDescription}, {point.value}.'
            }
        },

        title: {
            text: null,
            style: {
                fontSize: '1em'
            }
        },

        xAxis: {
            visible: false
        },

        yAxis: {
            visible: false
        },

        colorAxis: {
            dataClasses: [{
                from: 0,
                to: 1000000,
                color: 'rgb(59, 82, 139)',
                name: '< 1M'
            }, {
                from: 1000000,
                to: 5000000,
                color: 'rgb(33, 145, 140)',
                name: '1M - 5M'
            }, {
                from: 5000000,
                to: 20000000,
                color: 'rgb(94, 201, 98)',
                name: '5M - 20M'
            }, {
                from: 20000000,
                color: 'rgb(253, 231, 37)',
                name: '> 20M'
            }]
        },

        tooltip: {
            headerFormat: '',
            pointFormat: 'The population of <b> {point.name}</b> is <b>' +
                '{point.value}</b>'
        },

        plotOptions: {
            series: {
                dataLabels: {
                    enabled: true,
                    format: '{point.hc-a2}',
                    style: {
                        textOutline: false
                    }
                }
            }
        },

        series: [{
            name: '',
            data: [{
                'hc-a2': 'AL',
                name: 'Alabama',
                region: 'South',
                x: 6,
                y: 7,
                value: 5108468
            }, {
                'hc-a2': 'AK',
                name: 'Alaska',
                region: 'West',
                x: 0,
                y: 0,
                value: 733406
            }, {
                'hc-a2': 'AZ',
                name: 'Arizona',
                region: 'West',
                x: 5,
                y: 3,
                value: 7431344
            }, {
                'hc-a2': 'AR',
                name: 'Arkansas',
                region: 'South',
                x: 5,
                y: 6,
                value: 3067732
            }, {
                'hc-a2': 'CA',
                name: 'California',
                region: 'West',
                x: 5,
                y: 2,
                value: 38965193
            }, {
                'hc-a2': 'CO',
                name: 'Colorado',
                region: 'West',
                x: 4,
                y: 3,
                value: 5877610
            }, {
                'hc-a2': 'CT',
                name: 'Connecticut',
                region: 'Northeast',
                x: 3,
                y: 11,
                value: 3617176
            }, {
                'hc-a2': 'DE',
                name: 'Delaware',
                region: 'South',
                x: 4,
                y: 9,
                value: 1031890
            }, {
                'hc-a2': 'DC',
                name: 'District of Columbia',
                region: 'South',
                x: 4,
                y: 10,
                value: 678972
            }, {
                'hc-a2': 'FL',
                name: 'Florida',
                region: 'South',
                x: 8,
                y: 8,
                value: 22610726
            }, {
                'hc-a2': 'GA',
                name: 'Georgia',
                region: 'South',
                x: 7,
                y: 8,
                value: 11029227
            }, {
                'hc-a2': 'HI',
                name: 'Hawaii',
                region: 'West',
                x: 8,
                y: 0,
                value: 1435138
            }, {
                'hc-a2': 'ID',
                name: 'Idaho',
                region: 'West',
                x: 3,
                y: 2,
                value: 1964726
            }, {
                'hc-a2': 'IL',
                name: 'Illinois',
                region: 'Midwest',
                x: 3,
                y: 6,
                value: 12549689
            }, {
                'hc-a2': 'IN',
                name: 'Indiana',
                region: 'Midwest',
                x: 3,
                y: 7,
                value: 6862199
            }, {
                'hc-a2': 'IA',
                name: 'Iowa',
                region: 'Midwest',
                x: 3,
                y: 5,
                value: 3207004
            }, {
                'hc-a2': 'KS',
                name: 'Kansas',
                region: 'Midwest',
                x: 5,
                y: 5,
                value: 2940546
            }, {
                'hc-a2': 'KY',
                name: 'Kentucky',
                region: 'South',
                x: 4,
                y: 6,
                value: 4526154
            }, {
                'hc-a2': 'LA',
                name: 'Louisiana',
                region: 'South',
                x: 6,
                y: 5,
                value: 4573749
            }, {
                'hc-a2': 'ME',
                name: 'Maine',
                region: 'Northeast',
                x: 0,
                y: 11,
                value: 1395722
            }, {
                'hc-a2': 'MD',
                name: 'Maryland',
                region: 'South',
                x: 4,
                y: 8,
                value: 6180253
            }, {
                'hc-a2': 'MA',
                name: 'Massachusetts',
                region: 'Northeast',
                x: 2,
                y: 10,
                value: 7001399
            }, {
                'hc-a2': 'MI',
                name: 'Michigan',
                region: 'Midwest',
                x: 2,
                y: 7,
                value: 1037261
            }, {
                'hc-a2': 'MN',
                name: 'Minnesota',
                region: 'Midwest',
                x: 2,
                y: 4,
                value: 5737915
            }, {
                'hc-a2': 'MS',
                name: 'Mississippi',
                region: 'South',
                x: 6,
                y: 6,
                value: 2939690
            }, {
                'hc-a2': 'MO',
                name: 'Missouri',
                region: 'Midwest',
                x: 4,
                y: 5,
                value: 6196156
            }, {
                'hc-a2': 'MT',
                name: 'Montana',
                region: 'West',
                x: 2,
                y: 2,
                value: 1132182
            }, {
                'hc-a2': 'NE',
                name: 'Nebraska',
                region: 'Midwest',
                x: 4,
                y: 4,
                value: 1978379
            }, {
                'hc-a2': 'NV',
                name: 'Nevada',
                region: 'West',
                x: 4,
                y: 2,
                value: 3194176
            }, {
                'hc-a2': 'NH',
                name: 'New Hampshire',
                region: 'Northeast',
                x: 1,
                y: 11,
                value: 1402054
            }, {
                'hc-a2': 'NJ',
                name: 'New Jersey',
                region: 'Northeast',
                x: 3,
                y: 10,
                value: 9290841
            }, {
                'hc-a2': 'NM',
                name: 'New Mexico',
                region: 'West',
                x: 6,
                y: 3,
                value: 2114371
            }, {
                'hc-a2': 'NY',
                name: 'New York',
                region: 'Northeast',
                x: 2,
                y: 9,
                value: 19571216
            }, {
                'hc-a2': 'NC',
                name: 'North Carolina',
                region: 'South',
                x: 5,
                y: 9,
                value: 10835491
            }, {
                'hc-a2': 'ND',
                name: 'North Dakota',
                region: 'Midwest',
                x: 2,
                y: 3,
                value: 783926
            }, {
                'hc-a2': 'OH',
                name: 'Ohio',
                region: 'Midwest',
                x: 3,
                y: 8,
                value: 11785935
            }, {
                'hc-a2': 'OK',
                name: 'Oklahoma',
                region: 'South',
                x: 6,
                y: 4,
                value: 4053824
            }, {
                'hc-a2': 'OR',
                name: 'Oregon',
                region: 'West',
                x: 4,
                y: 1,
                value: 4233358
            }, {
                'hc-a2': 'PA',
                name: 'Pennsylvania',
                region: 'Northeast',
                x: 3,
                y: 9,
                value: 12961683
            }, {
                'hc-a2': 'RI',
                name: 'Rhode Island',
                region: 'Northeast',
                x: 2,
                y: 11,
                value: 1095926
            }, {
                'hc-a2': 'SC',
                name: 'South Carolina',
                region: 'South',
                x: 6,
                y: 8,
                value: 5373555
            }, {
                'hc-a2': 'SD',
                name: 'South Dakota',
                region: 'Midwest',
                x: 3,
                y: 4,
                value: 919318
            }, {
                'hc-a2': 'TN',
                name: 'Tennessee',
                region: 'South',
                x: 5,
                y: 7,
                value: 7126489
            }, {
                'hc-a2': 'TX',
                name: 'Texas',
                region: 'South',
                x: 7,
                y: 4,
                value: 30503301
            }, {
                'hc-a2': 'UT',
                name: 'Utah',
                region: 'West',
                x: 5,
                y: 4,
                value: 3417734
            }, {
                'hc-a2': 'VT',
                name: 'Vermont',
                region: 'Northeast',
                x: 1,
                y: 10,
                value: 647464
            }, {
                'hc-a2': 'VA',
                name: 'Virginia',
                region: 'South',
                x: 5,
                y: 8,
                value: 8715698
            }, {
                'hc-a2': 'WA',
                name: 'Washington',
                region: 'West',
                x: 2,
                y: 1,
                value: 7812880
            }, {
                'hc-a2': 'WV',
                name: 'West Virginia',
                region: 'South',
                x: 4,
                y: 7,
                value: 1770071
            }, {
                'hc-a2': 'WI',
                name: 'Wisconsin',
                region: 'Midwest',
                x: 2,
                y: 5,
                value: 5910955
            }, {
                'hc-a2': 'WY',
                name: 'Wyoming',
                region: 'West',
                x: 3,
                y: 3,
                value: 584057
            }]
        }]
    });

    // 3D donut
    Highcharts.chart('threeDDonut', {
        chart: {
            type: 'pie',
            options3d: {
                enabled: true,
                alpha: 45
            }
        },
        title: {
            text: null
        },
        plotOptions: {
            pie: {
                innerSize: 100,
                depth: 45
            }
        },
        colors: getColors(["--pe-secondary", "--pe-primary", "--pe-danger", "--pe-info", "--pe-warning", "--pe-success", "--pe-primary", "--pe-dark", "--pe-info"]),
        series: [{
            name: 'Medals',
            data: [
                ['Norway', 16],
                ['Germany', 12],
                ['USA', 8],
                ['Sweden', 8],
                ['Netherlands', 8],
                ['ROC', 6],
                ['Austria', 7],
                ['Canada', 4],
                ['Japan', 3]

            ]
        }]
    });

}
window.addEventListener("DOMContentLoaded", renderCharts);


