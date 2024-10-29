document.addEventListener('DOMContentLoaded', function () {
    const chartElement = document.getElementById('stockChart');
    const ctx = chartElement.getContext('2d');

    const dpi = window.devicePixelRatio || 1;
    chartElement.width = chartElement.clientWidth * dpi;
    chartElement.height = chartElement.clientHeight * dpi;
    ctx.scale(dpi, dpi);
    Chart.register(window['chartjs-plugin-annotation']);

    function parseTimestamp(ts) {
        const parts = ts.match(/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/);
        if (parts) {
            const year = parseInt(parts[1], 10);
            const month = parseInt(parts[2], 10) - 1;
            const day = parseInt(parts[3], 10);
            const hour = parseInt(parts[4], 10);
            const minute = parseInt(parts[5], 10);
            const second = parseInt(parts[6], 10);
            return new Date(year, month, day, hour, minute, second);
        } else {
            return new Date(ts);
        }
    }

    const portfolioChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Portfolio Value',
                data: [],
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1.5,
                fill: false,
                pointRadius: 0,
                spanGaps: false,
                segment: {
                    borderDash: ctx => {
                        const prevY = ctx.p0.parsed.y;
                        const nextY = ctx.p1.parsed.y;
                        if (prevY === null || nextY === null || isNaN(prevY) || isNaN(nextY)) {
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
                        tooltipFormat: 'Pp',
                        parser: 'yyyy-MM-dd HH:mm:ss',
                    },
                    display: false,
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: 10,
                    },
                },
                y: {
                    display: false,
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: 5,
                    },
                }
            },
            plugins: {
                annotation: {
                    annotations: {}
                },
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

    async function updatePortfolioChart(timeframe) {
        try {
            const response = await fetch(`/BackendAutomation/stock_portfolio.php?timeframe=${timeframe}`);
            const data = await response.json();

            if (data.success) {
                let chartData;
                switch (timeframe) {
                    case '1d':
                    case '1w':
                    case '1m':
                    case '3m':
                    case '1y':
                        chartData = buildDataWithInterval(data.data, timeframe);
                        break;
                    case 'ytd':
                        chartData = buildYTDData(data.data);
                        break;
                    case 'all':
                        chartData = data.data.map(entry => ({
                            x: parseTimestamp(entry.timestamp),
                            y: entry.portfolio_value
                        }));
                        break;
                    default:
                        chartData = [];
                }

                portfolioChart.data.datasets[0].data = chartData;
                portfolioChart.options.plugins.annotation.annotations = {};

                portfolioChart.update('none');
            } else {
                console.error('Error fetching portfolio data:', data.message);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function buildDataWithInterval(data, timeframe) {
        const dataPoints = [];
        const now = Date.now();

        let startTime;
        let intervalMillis;

        switch (timeframe) {
            case '1d':
                startTime = now - 24 * 60 * 60 * 1000;
                intervalMillis = 60 * 1000;
                break;
            case '1w':
                startTime = now - 7 * 24 * 60 * 60 * 1000;
                intervalMillis = 60 * 60 * 1000;
                break;
            case '1m':
                startTime = now - 30 * 24 * 60 * 60 * 1000;
                intervalMillis = 24 * 60 * 60 * 1000;
                break;
            case '3m':
                startTime = now - 90 * 24 * 60 * 60 * 1000;
                intervalMillis = 24 * 60 * 60 * 1000;
                break;
            case '1y':
                startTime = now - 365 * 24 * 60 * 60 * 1000;
                intervalMillis = 7 * 24 * 60 * 60 * 1000;
                break;
            default:
                startTime = now - 24 * 60 * 60 * 1000;
                intervalMillis = 60 * 1000;
                break;
        }

        let dataIndex = 0;
        let lastValue = null;

        const times = data.map(entry => parseTimestamp(entry.timestamp).getTime());
        const values = data.map(entry => parseFloat(entry.portfolio_value));

        for (let time = startTime; time <= now; time += intervalMillis) {
            while (dataIndex < times.length && times[dataIndex] <= time) {
                lastValue = values[dataIndex];
                dataIndex++;
            }
            dataPoints.push({ x: new Date(time), y: lastValue });
        }

        return dataPoints;
    }

    function buildYTDData(data) {
        const dataPoints = [];
        const now = new Date();
        const startOfYear = new Date(now.getFullYear(), 0, 1).getTime();
        const intervalMillis = 24 * 60 * 60 * 1000;

        let dataIndex = 0;
        let lastValue = null;

        const times = data.map(entry => parseTimestamp(entry.timestamp).getTime());
        const values = data.map(entry => parseFloat(entry.portfolio_value));

        for (let time = startOfYear; time <= now.getTime(); time += intervalMillis) {
            while (dataIndex < times.length && times[dataIndex] <= time) {
                lastValue = values[dataIndex];
                dataIndex++;
            }
            dataPoints.push({ x: new Date(time), y: lastValue });
        }

        return dataPoints;
    }
	document.querySelectorAll('.time-button').forEach(button => {
		button.addEventListener('click', function () {
			const timeframe = button.getAttribute('data-timeframe');
			document.querySelectorAll('.time-button').forEach(btn => btn.classList.remove('selected'));
			button.classList.add('selected');
			updatePortfolioChart(timeframe);
		});
	});
    updatePortfolioChart('1d');
});
