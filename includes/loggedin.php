<div class="trading-dashboard-container clearfix">
    <!-- Graph Area -->
    <div class="chart-container">
        <h2>Portfolio Performance</h2>
        <canvas id="stockChart"></canvas>

		<div class="timeframe-buttons">
			<span class="time-button selected" onclick="updateChart('1d')" id="1d">1D</span>
			<span class="time-button" onclick="updateChart('1w')" id="1w">1W</span>
			<span class="time-button" onclick="updateChart('1m')" id="1m">1M</span>
			<span class="time-button" onclick="updateChart('3m')" id="3m">3M</span>
			<span class="time-button" onclick="updateChart('ytd')" id="ytd">YTD</span>
			<span class="time-button" onclick="updateChart('1y')" id="1y">1Y</span>
			<span class="time-button" onclick="updateChart('all')" id="all">ALL</span>

			<div class="divider-line-full"></div>

			<div class="divider-line-highlight"></div>
		</div>



        <div class="account-info">
            <p>Buying Power: $0.00</p>
            <p>Cash Account: $0.00</p>
        </div>
    </div>

    <div class="stock-list">
        <h3>Your Stocks</h3>
        <ul>
            <li>No stocks available.</li>
        </ul>
    </div>
</div>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
