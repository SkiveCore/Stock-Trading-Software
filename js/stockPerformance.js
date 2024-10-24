document.addEventListener('DOMContentLoaded', function () {
    const chartElement = document.getElementById('stockPerformanceChart');
    const ctx = chartElement.getContext('2d');

    const dpi = window.devicePixelRatio || 1;
    chartElement.width = chartElement.clientWidth * dpi;
    chartElement.height = chartElement.clientHeight * dpi;
    ctx.scale(dpi, dpi);

    const stockPerformanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Stock Price',
                data: [],
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1.5,
                fill: false,
                pointRadius: 0,
                spanGaps: true,
                segment: {
                    borderDash: ctx => {
                        const prevY = ctx.p0.parsed.y;
                        const nextY = ctx.p1.parsed.y;
                        if (prevY === null || nextY === null || prevY === NaN || nextY === NaN) {
                            return [6, 6];
                        }
                        return undefined;
                    },
                },
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        tooltipFormat: 'PPpp',
                        parser: 'yyyy-MM-dd HH:mm:ss',
                    },
                    adapters: {
                        date: {
                            zone: 'utc',
                        }
                    },
                    display: false
                },
                y: {
                    display: false
                }
            },
            plugins: {
                tooltip: {
                    enabled: true,
                    mode: 'index',
                    intersect: false,
                },
                legend: {
                    display: false,
                }
            },
            elements: {
                line: {
                    tension: 0,
                }
            },
            animation: true
        }
    });

    function updateStockChart(timeframe) {
        const stockIdInput = document.querySelector('input[name="stock_id"]');
        const stockId = stockIdInput ? stockIdInput.value : null;

        fetch(`/BackendAutomation/fetchStockHistory.php?stock_id=${stockId}&timeframe=all`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let chartData;
                    switch (timeframe) {
                        case '1d':
                        case '1w':
                        case '1m':
                        case '3m':
                        case '1y':
                            chartData = buildDataWithInterval(data.timestamps, data.prices, timeframe);
                            break;
                        case 'ytd':
                            chartData = buildYTDData(data.timestamps, data.prices);
                            break;
                        case 'all':
                            chartData = data.timestamps.map((timestamp, index) => ({
                                x: new Date(Date.parse(timestamp.replace(' ', 'T') + 'Z')),
                                y: parseFloat(data.prices[index]),
                            }));
                            break;
                        default:
                            chartData = [];
                    }

                    stockPerformanceChart.data.datasets[0].data = chartData;
                    stockPerformanceChart.update('none');
                } else {
                    console.error('Error fetching data:', data.message);
                }
            })
            .catch(error => console.error('Error:', error));

        const buttons = document.querySelectorAll('.time-button');
        const selectedButton = document.getElementById(timeframe);

        if (!buttons.length || !selectedButton) {
            console.warn("Required elements for 'updateChart' are missing.");
            return;
        }

        buttons.forEach(button => button.classList.remove('selected'));
        selectedButton.classList.add('selected');
    }

    function buildDataWithInterval(timestamps, prices, timeframe) {
        const dataPoints = [];
        const nowUTC = Date.now();
        let startTime;
        let intervalMillis;

        switch (timeframe) {
            case '1d':
                const now = new Date(nowUTC);
                startTime = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());
                intervalMillis = 60 * 1000;
                break;
            case '1w':
                startTime = nowUTC - 7 * 24 * 60 * 60 * 1000;
                intervalMillis = 60 * 60 * 1000;
                break;
            case '1m':
                startTime = nowUTC - 30 * 24 * 60 * 60 * 1000;
                intervalMillis = 24 * 60 * 60 * 1000;
                break;
            case '3m':
                startTime = nowUTC - 90 * 24 * 60 * 60 * 1000;
                intervalMillis = 24 * 60 * 60 * 1000;
                break;
            case '1y':
                startTime = nowUTC - 365 * 24 * 60 * 60 * 1000;
                intervalMillis = 7 * 24 * 60 * 60 * 1000;
                break;
            default:
                startTime = nowUTC - 24 * 60 * 60 * 1000;
                intervalMillis = 60 * 1000;
                break;
        }

        const dataLength = timestamps.length;
        let dataIndex = 0;
        let lastPrice = null;
        const times = timestamps.map(ts => Date.parse(ts.replace(' ', 'T') + 'Z'));
        const pricesFloat = prices.map(p => parseFloat(p));

        for (let time = startTime; time <= nowUTC; time += intervalMillis) {
            while (dataIndex < dataLength && times[dataIndex] <= time) {
                lastPrice = pricesFloat[dataIndex];
                dataIndex++;
            }
            const y = (lastPrice !== null) ? lastPrice : null;
            dataPoints.push({ x: new Date(time), y: y });
        }

        return dataPoints;
    }

    function buildYTDData(timestamps, prices) {
        const dataPoints = [];
        const now = new Date();
        const nowUTC = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());
        const startOfYear = Date.UTC(now.getUTCFullYear(), 0, 1);
        const intervalMillis = 24 * 60 * 60 * 1000; // 1 day

        const dataLength = timestamps.length;
        let dataIndex = 0;
        let lastPrice = null;

        const times = timestamps.map(ts => Date.parse(ts.replace(' ', 'T') + 'Z'));
        const pricesFloat = prices.map(p => parseFloat(p));

        for (let time = startOfYear; time <= nowUTC; time += intervalMillis) {
            while (dataIndex < dataLength && times[dataIndex] <= time) {
                lastPrice = pricesFloat[dataIndex];
                dataIndex++;
            }
            const y = (lastPrice !== null) ? lastPrice : null;
            dataPoints.push({ x: new Date(time), y: y });
        }

        return dataPoints;
    }

    updateStockChart('1d');

    document.querySelectorAll('.time-button').forEach(button => {
        button.addEventListener('click', function () {
            updateStockChart(this.id);
        });
    });
});
