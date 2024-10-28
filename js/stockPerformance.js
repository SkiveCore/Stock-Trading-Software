document.addEventListener('DOMContentLoaded', function () {
    const chartElement = document.getElementById('stockPerformanceChart');
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

    async function updateStockChart(timeframe) {
        const stockIdInput = document.querySelector('input[name="stock_id"]');
        const stockId = stockIdInput ? stockIdInput.value : null;

        try {
            const [stockData, marketData] = await Promise.all([
                fetch(`/BackendAutomation/fetchStockHistory.php?stock_id=${stockId}&timeframe=all`).then(response => response.json()),
                fetch(`/BackendAutomation/fetchMarketHours.php`).then(response => response.json()),
            ]);

            if (stockData.success && marketData.success) {
                let chartData;
                switch (timeframe) {
                    case '1d':
                    case '1w':
                    case '1m':
                    case '3m':
                    case '1y':
                        chartData = buildDataWithInterval(stockData.timestamps, stockData.prices, timeframe);
                        break;
                    case 'ytd':
                        chartData = buildYTDData(stockData.timestamps, stockData.prices);
                        break;
                    case 'all':
                        chartData = stockData.timestamps.map((timestamp, index) => ({
                            x: parseTimestamp(timestamp),
                            y: parseFloat(stockData.prices[index]),
                        }));
                        break;
                    default:
                        chartData = [];
                }

                stockPerformanceChart.data.datasets[0].data = chartData;
                stockPerformanceChart.options.plugins.annotation.annotations = {};
                const annotations = buildMarketHourAnnotations(chartData, marketData.market_hours);
                stockPerformanceChart.options.plugins.annotation.annotations = annotations;

                stockPerformanceChart.update('none');
            } else {
                console.error('Error fetching data:', stockData.message || marketData.message);
            }
        } catch (error) {
            console.error('Error:', error);
        }
        const buttons = document.querySelectorAll('.time-button');
        const selectedButton = document.getElementById(timeframe);

        if (!buttons.length || !selectedButton) {
            console.warn("Required elements for 'updateChart' are missing.");
            return;
        }

        buttons.forEach(button => button.classList.remove('selected'));
        selectedButton.classList.add('selected');
    }

    function buildMarketHourAnnotations(chartData, marketHours) {
        const annotations = {};
        const marketHoursMap = marketHours.reduce((map, mh) => {
            map[mh.day_of_week] = mh;
            return map;
        }, {});
        const uniqueDates = [...new Set(chartData.map(point => point.x.toDateString()))];

        uniqueDates.forEach((dateString, index) => {
            const date = new Date(dateString);
            const dayOfWeek = date.toLocaleString('en-US', { weekday: 'long' });

            const marketHour = marketHoursMap[dayOfWeek];
            if (marketHour) {
                const openTime = new Date(`${dateString} ${marketHour.open_time}`);
                const closeTime = new Date(`${dateString} ${marketHour.close_time}`);

                const dayStart = new Date(dateString);
                const dayEnd = new Date(dateString);
                dayEnd.setHours(23, 59, 59, 999);
                annotations[`beforeMarketOpen_${index}`] = {
                    type: 'box',
                    xMin: dayStart.getTime(),
                    xMax: openTime.getTime(),
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    borderWidth: 0,
                };
                annotations[`afterMarketClose_${index}`] = {
                    type: 'box',
                    xMin: closeTime.getTime(),
                    xMax: dayEnd.getTime(),
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    borderWidth: 0,
                };
            } else {
                const dayStart = new Date(dateString);
                const dayEnd = new Date(dateString);
                dayEnd.setHours(23, 59, 59, 999);
                annotations[`marketClosed_${index}`] = {
                    type: 'box',
                    xMin: dayStart.getTime(),
                    xMax: dayEnd.getTime(),
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    borderWidth: 0,
                };
            }
        });
        return annotations;
    }

	function buildDataWithInterval(timestamps, prices, timeframe) {
		const dataPoints = [];
		const now = Date.now();
		
		let startTime;
		let intervalMillis;

		switch (timeframe) {
			case '1d': {
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				startTime = today.getTime();
				intervalMillis = 60 * 1000;
				break;
			}
			case '1w': {
				startTime = now - 7 * 24 * 60 * 60 * 1000;
				intervalMillis = 60 * 60 * 1000;
				break;
			}
			case '1m': {
				startTime = now - 30 * 24 * 60 * 60 * 1000;
				intervalMillis = 24 * 60 * 60 * 1000;
				break;
			}
			case '3m': {
				startTime = now - 90 * 24 * 60 * 60 * 1000;
				intervalMillis = 24 * 60 * 60 * 1000;
				break;
			}
			case '1y': {
				startTime = now - 365 * 24 * 60 * 60 * 1000;
				intervalMillis = 7 * 24 * 60 * 60 * 1000;
				break;
			}
			default: {
				startTime = now - 24 * 60 * 60 * 1000;
				intervalMillis = 60 * 1000;
				break;
			}
		}

		const dataLength = timestamps.length;
		let dataIndex = 0;
		let lastPrice = null;

		const times = timestamps.map(ts => parseTimestamp(ts).getTime());
		const pricesFloat = prices.map(p => parseFloat(p));

		if (times[0] > startTime) {
			startTime = times[0];
		}

		for (let time = startTime; time <= now; time += intervalMillis) {
			while (dataIndex < dataLength && times[dataIndex] <= time) {
				lastPrice = pricesFloat[dataIndex];
				dataIndex++;
			}
			const y = lastPrice !== null ? lastPrice : null;
			dataPoints.push({ x: new Date(time), y: y });
		}

		return dataPoints;
    }

    function buildYTDData(timestamps, prices) {
        const dataPoints = [];
        const now = new Date();
        const startOfYear = new Date(now.getFullYear(), 0, 1).getTime();
        const nowTime = now.getTime();
        const intervalMillis = 24 * 60 * 60 * 1000;

        const dataLength = timestamps.length;
        let dataIndex = 0;
        let lastPrice = null;

        const times = timestamps.map(ts => parseTimestamp(ts).getTime());
        const pricesFloat = prices.map(p => parseFloat(p));

        for (let time = startOfYear; time <= nowTime; time += intervalMillis) {
            while (dataIndex < dataLength && times[dataIndex] <= time) {
                lastPrice = pricesFloat[dataIndex];
                dataIndex++;
            }
            const y = lastPrice !== null ? lastPrice : null;
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
