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
            spanGaps: true,
            scales: {
                x: {
                    type: 'time',
                    time: {
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
        const selectedButton = document.getElementById(timeframe);

        if (!buttons.length || !selectedButton) {
            console.warn("Required elements for 'updateChart' are missing.");
            return;
        }

        buttons.forEach(button => button.classList.remove('selected'));
        selectedButton.classList.add('selected');
    }

    function buildOptimized1DayData(timestamps, prices) {
        const now = new Date();
        const startOfDay = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate());
        const oneDayData = [];
        const interval = 60; // seconds

        for (let second = 0; second < 86400; second += interval) {
            const timestampMillis = startOfDay + (second * 1000);
            const timestamp = new Date(timestampMillis);
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts).getTime() - timestampMillis) < interval * 1000);
            const price = index !== -1 ? prices[index] : (oneDayData.length > 0 ? oneDayData[oneDayData.length - 1].y : 0);
            oneDayData.push({ x: timestamp, y: price });
        }

        return oneDayData;
    }

	
	function buildWeeklyData(timestamps, prices) {
    const oneWeekData = [];
    const now = new Date();

    const nowUTC = Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds(), now.getUTCMilliseconds());
		const interval = 60 * 60 * 1000; // 1 hour in milliseconds
		const startTimeUTC = nowUTC - (7 * 24 * 60 * 60 * 1000);
		for (let hour = 0; hour <= 168; hour++) { // 168 hours in a week
			const timeMillis = startTimeUTC + (hour * interval);
			const time = new Date(timeMillis);
			const index = timestamps.findIndex(ts => {
				const tsMillis = Date.parse(ts);
				return Math.abs(tsMillis - timeMillis) < interval;
			});
			const price = index !== -1 
				? prices[index] 
				: (oneWeekData.length > 0 ? oneWeekData[oneWeekData.length - 1].y : 0);
			oneWeekData.push({ 
				x: new Date(time), // Ensure the date is in UTC
				y: price 
			});
		}
		return oneWeekData;
	}

    function buildMonthlyData(timestamps, prices) {
        const oneMonthData = [];
        const now = new Date();
        const interval = 24 * 60 * 60 * 1000; // 1 day in milliseconds
        const startTime = now.getTime() - (30 * interval);

        for (let day = 0; day < 30; day++) {
            const timeMillis = startTime + (day * interval);
            const time = new Date(timeMillis);
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts).getTime() - timeMillis) < interval);
            const price = index !== -1 ? prices[index] : (oneMonthData.length > 0 ? oneMonthData[oneMonthData.length - 1].y : 0);
            oneMonthData.push({ x: time, y: price });
        }

        return oneMonthData;
    }

    function buildQuarterlyData(timestamps, prices) {
        const threeMonthsData = [];
        const now = new Date();
        const interval = 24 * 60 * 60 * 1000; // 1 day in milliseconds
        const startTime = now.getTime() - (90 * interval);

        for (let day = 0; day < 90; day++) {
            const timeMillis = startTime + (day * interval);
            const time = new Date(timeMillis);
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts).getTime() - timeMillis) < interval);
            const price = index !== -1 ? prices[index] : (threeMonthsData.length > 0 ? threeMonthsData[threeMonthsData.length - 1].y : 0);
            threeMonthsData.push({ x: time, y: price });
        }

        return threeMonthsData;
    }

    function buildYTDData(timestamps, prices) {
        const ytdData = [];
        const now = new Date();
        const startOfYear = Date.UTC(now.getUTCFullYear(), 0, 1);
        const interval = 24 * 60 * 60 * 1000; // 1 day in milliseconds
        const daysSinceStartOfYear = Math.floor((now.getTime() - startOfYear) / interval);

        for (let day = 0; day <= daysSinceStartOfYear; day++) {
            const timeMillis = startOfYear + (day * interval);
            const time = new Date(timeMillis);
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts).getTime() - timeMillis) < interval);
            const price = index !== -1 ? prices[index] : (ytdData.length > 0 ? ytdData[ytdData.length - 1].y : 0);
            ytdData.push({ x: time, y: price });
        }

        return ytdData;
    }

    function buildYearlyData(timestamps, prices) {
        const oneYearData = [];
        const now = new Date();
        const interval = 24 * 60 * 60 * 1000; // 1 day in milliseconds
        const startTime = now.getTime() - (365 * interval);

        for (let day = 0; day < 365; day++) {
            const timeMillis = startTime + (day * interval);
            const time = new Date(timeMillis);
            const index = timestamps.findIndex(ts => Math.abs(new Date(ts).getTime() - timeMillis) < interval);
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
