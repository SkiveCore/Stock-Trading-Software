<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';

$symbol = isset($_GET['symbol']) ? htmlspecialchars($_GET['symbol']) : null;
$stock_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($symbol) {
    $query = "SELECT * FROM stocks WHERE ticker_symbol = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $symbol);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_assoc();
    if (!$stock) {
		header("Location: /404.php");
		exit();
    }
    $stock_id = $stock['stock_id'];
} elseif ($stock_id) {
    $query = "SELECT * FROM stocks WHERE stock_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $stock_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_assoc();
    if (!$stock) {
		header("Location: /404.php");
		exit();
    }
} else {
    header("Location: /404.php");
    exit();
}

$cash_balance = 0;
$user_id = $_SESSION['user_id'] ?? 0;
$query = "SELECT balance FROM user_wallets WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wallet = $result->fetch_assoc();
$balance = $wallet['balance'] ?? 0.00;
$pending_sql = "SELECT SUM(quantity * price_per_share) AS pending_amount 
                FROM user_stock_transactions 
                WHERE user_id = ? AND status = 'pending' AND transaction_type = 'purchase'";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending = $pending_result->fetch_assoc();
$pending_amount = $pending['pending_amount'] ?? 0.00;
$buying_power = $balance - $pending_amount;
$owned_quantity_sql = "SELECT SUM(quantity) AS owned_quantity 
                       FROM user_stock_transactions 
                       WHERE user_id = ? AND stock_id = ? AND transaction_type = 'purchase' AND status = 'completed'";
$owned_quantity_stmt = $conn->prepare($owned_quantity_sql);
$owned_quantity_stmt->bind_param("ii", $user_id, $stock_id);
$owned_quantity_stmt->execute();
$owned_quantity_result = $owned_quantity_stmt->get_result();
$owned_data = $owned_quantity_result->fetch_assoc();
$owned_quantity = $owned_data['owned_quantity'] ?? 0;
$buffer = 1.05;
$max_quantity_to_buy = floor($buying_power / ($stock['current_price'] * $buffer));
$max_quantity_to_sell = $owned_quantity;
$message = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : null;


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . '/BackendAutomation';
$stock_symbol = htmlspecialchars($stock['ticker_symbol']);
$stock_name = htmlspecialchars($stock['company_name']);
$timestamp = date("Y-m-d-H-i");
$chart_image_url = "{$base_url}/generate_stock_chart.php?symbol={$stock_symbol}&id={$stock_id}";

$ch = curl_init($chart_image_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

$chart_image_response = curl_exec($ch);

if (curl_errno($ch)) {
    curl_close($ch);
}

curl_close($ch);

$chart_image_data = json_decode($chart_image_response, true);

if ($chart_image_data && $chart_image_data['success'] && isset($chart_image_data['image_base_path'])) {
    $image_base_path = $chart_image_data['image_base_path'];
} else {
    $image_base_path = "/images/default_chart";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>Stock Performance - <?php echo htmlspecialchars($stock['company_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/stock-style.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="stock-performance-container">
        <h2>Stock Performance - <?php echo htmlspecialchars($stock['company_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($stock['ticker_symbol'] ?? 'N/A'); ?>)</h2>
    	<?php if (isset($_SESSION['message'])): ?>
			<div class="message-container <?php echo $_SESSION['message_type']; ?>">
				<p><?php echo $_SESSION['message']; ?></p>
			</div>
			<?php 
			unset($_SESSION['message']);
			unset($_SESSION['message_type']);
		endif;
		?>
        <div class="content-wrapper">
            <div class="chart-container">
            	<noscript>
					<style>
						.timeframe-buttons {
							display: none;
						}
					</style>
					<p class="noscript-message">JavaScript is required for an interactive chart. Here’s the latest stock performance chart:</p>
					<picture>
						<source srcset="<?php echo "{$image_base_path}_1500.webp 1500w, {$image_base_path}_1024.webp 1024w, {$image_base_path}_512.webp 512w, {$image_base_path}_256.webp 256w, {$image_base_path}_128.webp 128w"; ?>" type="image/webp">
						<source srcset="<?php echo "{$image_base_path}_1500.png 1500w, {$image_base_path}_1024.png 1024w, {$image_base_path}_512.png 512w, {$image_base_path}_256.png 256w, {$image_base_path}_128.png 128w"; ?>" type="image/png">
						<img src="<?php echo "{$image_base_path}_256.png"; ?>" loading="lazy" fetchpriority="low" alt="Stock Performance Chart for <?php echo htmlspecialchars($stock['company_name']); ?>" class="responsive-chart-image">
					</picture>
				</noscript>
				<canvas id="stockPerformanceChart"></canvas>
                <div class="timeframe-buttons">
					<span class="time-button selected" id="1d">1D</span>
					<span class="time-button" id="1w">1W</span>
					<span class="time-button" id="1m">1M</span>
					<span class="time-button" id="3m">3M</span>
					<span class="time-button" id="ytd">YTD</span>
					<span class="time-button" id="1y">1Y</span>
					<span class="time-button" id="all">ALL</span>
				</div>
            </div>

			<div class="purchase-card">
				<h3>Trade Stock</h3>
				<div class="price-info">
					<p><strong>Current Price:</strong> $<?php echo number_format($stock['current_price'], 2); ?></p>
					<p><strong>Buying Power:</strong> $<?php echo number_format($buying_power, 2); ?></p>
					<p><strong>Owned Quantity:</strong> <?php echo number_format($owned_quantity); ?></p>
				</div>
				<div class="trade-tabs">
					<span id="buy-tab" class="trade-tab active">Buy</span>
					<span id="sell-tab" class="trade-tab">Sell</span>
				</div>
				<form id="buy-form" action="buy_stock.php" method="POST">
					<input type="hidden" name="stock_id" value="<?php echo $stock_id; ?>">
					<div class="form-group">
						<label for="buy-quantity">Quantity:</label>
						<input type="number" id="buy-quantity" name="quantity" min="1" max="<?php echo $max_quantity_to_buy; ?>" placeholder="Max: <?php echo $max_quantity_to_buy; ?>" required autocomplete="off">
					</div>
					<p class="total-cost" id="buy-total-cost">Total Cost: $0.00</p>
					<button type="submit" class="trade-button buy-button">Buy</button>
				</form>
				<form id="sell-form" action="sell_stock.php" method="POST" style="display: none;">
					<input type="hidden" name="stock_id" value="<?php echo $stock_id; ?>">
					<div class="form-group">
						<label for="sell-quantity">Quantity:</label>
						<input type="number" id="sell-quantity" name="quantity" min="1" max="<?php echo $max_quantity_to_sell; ?>" placeholder="Max: <?php echo $max_quantity_to_sell; ?>" required autocomplete="off">
					</div>
					<p class="total-earnings" id="sell-total-earnings">Total Earnings: $0.00</p>
					<button type="submit" class="trade-button sell-button">Sell</button>
				</form>
			</div>

        	<div class="stock-details-container">
                <h2>Stock Details - <?php echo htmlspecialchars($stock['company_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($stock['ticker_symbol'] ?? 'N/A'); ?>)</h2>

				<div class="stock-summary">
					<div class="stock-summary-item">
						<h3>Current Price</h3>
						<p>$<?php echo number_format($stock['current_price'] ?? 0, 2); ?></p>
					</div>
					<div class="stock-summary-item">
						<h3>Opening Price</h3>
						<p>$<?php echo number_format($stock['opening_price'] ?? 0, 2); ?></p>
					</div>
					<div class="stock-summary-item">
						<h3>Closing Price</h3>
						<p>$<?php echo number_format($stock['closing_price'] ?? 0, 2); ?></p>
					</div>
				</div>

				<div class="stock-section">
					<h3>52-Week Performance</h3>
					<div class="stock-52-week">
						<p><span class="high-price">↑ High:</span> $<?php echo number_format($stock['fifty_two_week_high'] ?? 0, 2); ?></p>
						<p><span class="low-price">↓ Low:</span> $<?php echo number_format($stock['fifty_two_week_low'] ?? 0, 2); ?></p>
					</div>
				</div>

				<div class="stock-section">
					<h3>Market & Volume</h3>
					<div class="stock-market-volume">
						<p>Market Cap: $<?php echo number_format($stock['market_cap'] ?? 0, 2); ?></p>
						<p>Volume: <?php echo number_format($stock['volume'] ?? 0); ?></p>
					</div>
				</div>

				<div class="stock-section">
					<h3>Company Information</h3>
					<p>Sector: <?php echo htmlspecialchars($stock['sector'] ?? 'N/A'); ?></p>
					<p>Industry: <?php echo htmlspecialchars($stock['industry'] ?? 'N/A'); ?></p>
					<p>Stock Exchange: <?php echo htmlspecialchars($stock['stock_exchange'] ?? 'N/A'); ?></p>
					<p>CEO: <?php echo htmlspecialchars($stock['company_ceo'] ?? 'N/A'); ?></p>
				</div>

				<div class="stock-section">
					<h3>Financial Metrics</h3>
					<p>EPS: <?php echo $stock['eps'] !== null ? number_format($stock['eps'], 2) : 'N/A'; ?></p>
					<p>PE Ratio: <?php echo $stock['pe_ratio'] !== null ? number_format($stock['pe_ratio'], 2) : 'N/A'; ?></p>
					<p>Dividend Yield: <?php echo $stock['dividend_yield'] !== null ? number_format($stock['dividend_yield'], 2) . '%' : 'N/A'; ?></p>
					<p>Debt to Equity Ratio: <?php echo $stock['debt_to_equity_ratio'] !== null ? number_format($stock['debt_to_equity_ratio'], 2) : 'N/A'; ?></p>
					<p>Return on Equity: <?php echo $stock['return_on_equity'] !== null ? number_format($stock['return_on_equity'], 2) . '%' : 'N/A'; ?></p>
				</div>
        </div>
    	</div>
	</div>

    <?php include "includes/footer.php"; ?>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.1.0"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
	<script src="/js/stockPerformance.js"></script>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const buyTab = document.getElementById('buy-tab');
			const sellTab = document.getElementById('sell-tab');
			const buyForm = document.getElementById('buy-form');
			const sellForm = document.getElementById('sell-form');
			buyTab.addEventListener('click', function() {
				buyForm.style.display = 'block';
				sellForm.style.display = 'none';
				buyTab.classList.add('active');
				sellTab.classList.remove('active');
			});
			sellTab.addEventListener('click', function() {
				buyForm.style.display = 'none';
				sellForm.style.display = 'block';
				buyTab.classList.remove('active');
				sellTab.classList.add('active');
			});

			const buyQuantityInput = document.getElementById('buy-quantity');
			const sellQuantityInput = document.getElementById('sell-quantity');
			const buyTotalCostElement = document.getElementById('buy-total-cost');
			const sellTotalEarningsElement = document.getElementById('sell-total-earnings');
			const stockPrice = <?php echo $stock['current_price']; ?>;
			buyQuantityInput.addEventListener('input', function () {
				let quantity = parseInt(buyQuantityInput.value, 10);
				const totalCost = (quantity * stockPrice).toFixed(2);
				buyTotalCostElement.textContent = `Total Cost: $${totalCost}`;
			});
			sellQuantityInput.addEventListener('input', function () {
				let quantity = parseInt(sellQuantityInput.value, 10);
				const totalEarnings = (quantity * stockPrice).toFixed(2);
				sellTotalEarningsElement.textContent = `Total Earnings: $${totalEarnings}`;
			});
		});
	</script>
</body>
</html>
