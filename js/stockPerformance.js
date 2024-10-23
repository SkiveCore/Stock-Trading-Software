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
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'minute',
                        tooltipFormat: 'PPpp',
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
                    tension: 0.2
                }
            },
            animation: false
        }
    });

    function updateStockChart(timeframe) {
        const stockIdInput = document.querySelector('input[name="stock_id"]');
		const stockId = stockIdInput ? stockIdInput.value : null;

        fetch(`/BackendAutomation/fetchStockHistory.php?stock_id=${stockId}&timeframe=${timeframe}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let chartData;
                    switch (timeframe) {
                        case '1d':
                            chartData = buildOptimized1DayData(data.timestamps, data.prices);
                            break;
                        case '1w':
                            chartData = buildWeeklyData(data.timestamps, data.prices);
                            break;
                        case '1m':
                            chartData = buildMonthlyData(data.timestamps, data.prices);
                            break;
                        case '3m':
                            chartData = buildQuarterlyData(data.timestamps, data.prices);
                            break;
                        case 'ytd':
                            chartData = buildYTDData(data.timestamps, data.prices);
                            break;
                        case '1y':
                            chartData = buildYearlyData(data.timestamps, data.prices);
                            break;
                        case 'all':
                            chartData = data.timestamps.map((timestamp, index) => ({
                                x: new Date(timestamp),
                                y: data.prices[index],
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
		const dividerHighlight = document.querySelector('.divider-line-highlight');
		const selectedButton = document.getElementById(timeframe);

		if (!buttons.length || !dividerHighlight || !selectedButton) {
			console.warn("Required elements for 'updateChart' are missing.");
			return;
		}

		buttons.forEach(button => button.classList.remove('selected'));
		selectedButton.classList.add('selected');

		const buttonWidth = selectedButton.offsetWidth;
		const buttonPosition = selectedButton.offsetLeft;

		dividerHighlight.style.width = `${buttonWidth}px`;
		dividerHighlight.style.left = `${buttonPosition}px`;
    }

    function buildOptimized1DayData(timestamps, prices) {
        const now = new Date();
        const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const oneDayData = [];
        const interval = 60;

        for (let second = 0; second < 86400; second += interval) {
            const timestamp = new Date(startOfDay);
            timestamp.setSeconds(second);
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts) - timestamp) < interval * 1000);
            const price = index !== -1 ? prices[index] : (oneDayData.length > 0 ? oneDayData[oneDayData.length - 1].y : 0);
            oneDayData.push({ x: timestamp, y: price });
        }

        return oneDayData;
    }

    function buildWeeklyData(timestamps, prices) {
        const oneWeekData = [];
        const now = new Date();
        const interval = 60 * 60;
        const startTime = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000));

        for (let hour = 0; hour <= 168; hour++) {
            const time = new Date(startTime.getTime() + (hour * interval * 1000));
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts) - time) < interval * 1000);
            const price = index !== -1 ? prices[index] : (oneWeekData.length > 0 ? oneWeekData[oneWeekData.length - 1].y : 0);
            oneWeekData.push({ x: time, y: price });
        }

        return oneWeekData;
    }

    function buildMonthlyData(timestamps, prices) {
        const oneMonthData = [];
        const now = new Date();
        const interval = 24 * 60 * 60;
        const startTime = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));

        for (let day = 0; day < 30; day++) {
            const time = new Date(startTime.getTime() + (day * interval * 1000));
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts) - time) < interval * 1000);
            const price = index !== -1 ? prices[index] : (oneMonthData.length > 0 ? oneMonthData[oneMonthData.length - 1].y : 0);
            oneMonthData.push({ x: time, y: price });
        }

        return oneMonthData;
    }

    function buildQuarterlyData(timestamps, prices) {
        const threeMonthsData = [];
        const now = new Date();
        const interval = 24 * 60 * 60;
        const startTime = new Date(now.getTime() - (90 * 24 * 60 * 60 * 1000));

        for (let day = 0; day < 90; day++) {
            const time = new Date(startTime.getTime() + (day * interval * 1000));
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts) - time) < interval * 1000);
            const price = index !== -1 ? prices[index] : (threeMonthsData.length > 0 ? threeMonthsData[threeMonthsData.length - 1].y : 0);
            threeMonthsData.push({ x: time, y: price });
        }

        return threeMonthsData;
    }

    function buildYTDData(timestamps, prices) {
        const ytdData = [];
        const now = new Date();
        const startOfYear = new Date(now.getFullYear(), 0, 1);
        const interval = 24 * 60 * 60;

        for (let day = 0; day <= (now - startOfYear) / (24 * 60 * 60 * 1000); day++) {
            const time = new Date(startOfYear.getTime() + (day * interval * 1000));
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts) - time) < interval * 1000);
            const price = index !== -1 ? prices[index] : (ytdData.length > 0 ? ytdData[ytdData.length - 1].y : 0);
            ytdData.push({ x: time, y: price });
        }

        return ytdData;
    }

    function buildYearlyData(timestamps, prices) {
        const oneYearData = [];
        const now = new Date();
        const interval = 24 * 60 * 60;
        const startTime = new Date(now.getTime() - (365 * 24 * 60 * 60 * 1000));

        for (let day = 0; day < 365; day++) {
            const time = new Date(startTime.getTime() + (day * interval * 1000));
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts) - time) < interval * 1000);
            const price = index !== -1 ? prices[index] : (oneYearData.length > 0 ? oneYearData[oneYearData.length - 1].y : 0);
            oneYearData.push({ x: time, y: price });
        }

        return oneYearData;
    }

    updateStockChart('1d');

    document.querySelectorAll('.time-button').forEach(button => {
        button.addEventListener('click', function () {
            updateStockChart(this.id);
        });
    });
});

