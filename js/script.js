document.addEventListener('DOMContentLoaded', function () {
    let stockChart;
    const chartElement = document.getElementById('stockChart');
    if (chartElement) {
        const ctx = chartElement.getContext('2d');
        const now = new Date();
        const minutesInDay = 1440;
        const currentMinute = now.getHours() * 60 + now.getMinutes();
        const timeLabels = [];
        const portfolioData = [];
        for (let i = 0; i <= minutesInDay; i++) {
            const timeLabel = new Date(now.setHours(0, 0, 0, 0));
            timeLabel.setMinutes(i);
            timeLabels.push(timeLabel.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

            if (i <= currentMinute) {
                portfolioData.push(0);
            } else {
                portfolioData.push(null);
            }
        }
        stockChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Portfolio Value',
                    data: portfolioData,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: false,
                    borderDash: [],
                    pointRadius: 0,
                }, {
                    label: 'Future Portfolio Value',
                    data: portfolioData.map((val, i) => (i > currentMinute ? 0 : null)),
                    borderColor: 'rgba(192, 192, 192, 1)',
                    borderWidth: 2,
                    fill: false,
                    borderDash: [5, 5],
                    pointRadius: 0
                }]
            },
            options: {
                scales: {
                    x: {
                        display: false
                    },
                    y: {
                        display: false,
                        min: -1,
                        max: 1
                    }
                },
                plugins: {
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                const index = context.dataIndex;
                                if (index > currentMinute) {
                                    return `Time: ${timeLabels[currentMinute]}`;
                                }
                                return `Time: ${context.label}`;
                            },
                            title: function (tooltipItems) {
                                const index = tooltipItems[0].dataIndex;
                                if (index > currentMinute) {
                                    return `${timeLabels[currentMinute]}`;
                                }
                                return tooltipItems[0].label;
                            }
                        },
                        positioner: function (tooltipItems, coordinates) {
                            const index = tooltipItems[0].dataIndex;
                            if (index > currentMinute) {
                                const chart = tooltipItems[0].chart;
                                const x = chart.scales.x.getPixelForValue(timeLabels[currentMinute]);
                                const y = chart.scales.y.getPixelForValue(0);
                                return { x: x, y: y };
                            }
                            return coordinates;
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                hover: {
                    mode: 'index',
                    intersect: false
                },
                animation: {
                    duration: 0
                }
            },
            plugins: [{
                afterDraw: function (chart) {
                    if (chart.tooltip._active && chart.tooltip._active.length) {
                        const activePoint = chart.tooltip._active[0];
                        const ctx = chart.ctx;
                        const x = activePoint.element.x;
                        const y = activePoint.element.y;
                        const bottomY = chart.scales.y.bottom;
                        const activeIndex = activePoint.index;
                        const linePositionY = activeIndex > currentMinute ? chart.scales.y.getPixelForValue(0) : y;
                        const linePositionX = activeIndex > currentMinute ? chart.scales.x.getPixelForValue(timeLabels[currentMinute]) : x;
                        ctx.save();
                        ctx.beginPath();
                        ctx.moveTo(linePositionX, linePositionY);
                        ctx.lineTo(linePositionX, bottomY);
                        ctx.lineWidth = 2;
                        ctx.strokeStyle = 'rgba(75, 192, 192, 0.8)';
                        ctx.stroke();
                        ctx.restore();
                    }
                }
            }]
        });
    }

	function updateChart(timeframe) {
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
    updateChart('1d');
});



