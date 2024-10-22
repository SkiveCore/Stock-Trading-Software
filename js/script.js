document.addEventListener('DOMContentLoaded', function () {
    let stockChart;
    const chartElement = document.getElementById('stockChart');

    // Check if the element exists before trying to access its context
    if (chartElement) {
        const ctx = chartElement.getContext('2d');
        const now = new Date(); // Get current time
        const minutesInDay = 1440; // Total minutes in a day (24 hours * 60 minutes)
        const currentMinute = now.getHours() * 60 + now.getMinutes(); // Calculate the current minute of the day

        // Create arrays for time and data points
        const timeLabels = [];
        const portfolioData = [];
        for (let i = 0; i <= minutesInDay; i++) {
            const timeLabel = new Date(now.setHours(0, 0, 0, 0)); // Start from midnight
            timeLabel.setMinutes(i); // Add i minutes to midnight
            timeLabels.push(timeLabel.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

            if (i <= currentMinute) {
                portfolioData.push(0); // Flat line for the past
            } else {
                portfolioData.push(null); // No data for future, dotted line will be shown
            }
        }

        // Create a line chart with a dotted line for the future and solid for the past
        stockChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Portfolio Value',
                    data: portfolioData,
                    borderColor: 'rgba(75, 192, 192, 1)', // Solid line color
                    borderWidth: 2,
                    fill: false,
                    borderDash: [], // Solid line for past
                    pointRadius: 0, // No points shown on the line
                }, {
                    label: 'Future Portfolio Value',
                    data: portfolioData.map((val, i) => (i > currentMinute ? 0 : null)), // Future data is dotted
                    borderColor: 'rgba(192, 192, 192, 1)', // Dotted line color for future
                    borderWidth: 2,
                    fill: false,
                    borderDash: [5, 5], // Dotted line for future
                    pointRadius: 0 // No points shown on the line
                }]
            },
            options: {
                scales: {
                    x: {
                        display: false // Hide x-axis labels
                    },
                    y: {
                        display: false, // Hide y-axis labels
                        min: -1,
                        max: 1 // Center the line in the middle
                    }
                },
                plugins: {
                    tooltip: {
                        enabled: true, // Show tooltip
                        mode: 'index', // Make it appear when hovering anywhere along the x-axis
                        intersect: false, // Do not intersect with the graph point
                        callbacks: {
                            label: function (context) {
                                const index = context.dataIndex;

                                // If hovering past the current minute, always show the current time
                                if (index > currentMinute) {
                                    return `Time: ${timeLabels[currentMinute]}`;
                                }
                                // For past and current times, show the actual time
                                return `Time: ${context.label}`;
                            },
                            title: function (tooltipItems) {
                                // Modify the title of the tooltip to always show the current time if hovering in the future
                                const index = tooltipItems[0].dataIndex;
                                if (index > currentMinute) {
                                    return `${timeLabels[currentMinute]}`;
                                }
                                return tooltipItems[0].label;
                            }
                        },
                        positioner: function (tooltipItems, coordinates) {
                            const index = tooltipItems[0].dataIndex;

                            // If hovering in the future, lock the tooltip to the current time's position
                            if (index > currentMinute) {
                                const chart = tooltipItems[0].chart;
                                const x = chart.scales.x.getPixelForValue(timeLabels[currentMinute]);
                                const y = chart.scales.y.getPixelForValue(0); // Ensure it's locked to the line position
                                return { x: x, y: y }; // Lock the tooltip at the current time
                            }

                            // For past and current times, return the default position
                            return coordinates;
                        }
                    },
                    legend: {
                        display: false // Hide the legend
                    }
                },
                hover: {
                    mode: 'index',
                    intersect: false
                },
                animation: {
                    duration: 0 // No animation for real-time effect
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

                        // Lock to current minute if hovering in the future
                        const activeIndex = activePoint.index;
                        const linePositionY = activeIndex > currentMinute ? chart.scales.y.getPixelForValue(0) : y;
                        const linePositionX = activeIndex > currentMinute ? chart.scales.x.getPixelForValue(timeLabels[currentMinute]) : x;

                        // Draw the vertical line
                        ctx.save();
                        ctx.beginPath();
                        ctx.moveTo(linePositionX, linePositionY); // Start at the y-position of the portfolio line
                        ctx.lineTo(linePositionX, bottomY); // Draw down to the bottom
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
		// Ensure the buttons and divider elements exist before accessing them
		const buttons = document.querySelectorAll('.time-button');
		const dividerHighlight = document.querySelector('.divider-line-highlight');
		const selectedButton = document.getElementById(timeframe);

		if (!buttons.length || !dividerHighlight || !selectedButton) {
			console.warn("Required elements for 'updateChart' are missing.");
			return; // Exit the function if elements are missing
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

function toggleMenu() {
    const menu = document.querySelector('.nav-menu');
    const hamburger = document.querySelector('.hamburger');
    
    // Toggle the 'show' class on the menu to apply the scale transformation
    menu.classList.toggle('show');
    
    // Optionally toggle an active class on the hamburger for the "X" effect
    hamburger.classList.toggle('active');
}
