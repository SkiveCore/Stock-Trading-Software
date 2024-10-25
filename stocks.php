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

$cash_balance = 0;
$query = "SELECT balance, currency FROM user_wallets WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wallet = $result->fetch_assoc();

if ($wallet) {
    $cash_balance = $wallet['balance'];
}
$buffer = 1.05;
$max_quantity = floor($cash_balance / ($stock['current_price'] * $buffer));
$message = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : null;
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
			// Clear the message after displaying it
			unset($_SESSION['message']);
			unset($_SESSION['message_type']);
		endif;
		?>
        <div class="content-wrapper">
            <div class="chart-container">
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
				<h3>Purchase Stock</h3>
				<div class="price-info">
					<p><strong>Current Price:</strong> $<?php echo number_format($stock['current_price'], 2); ?></p>
					<p><strong>Cash Balance:</strong> $<?php echo number_format($cash_balance, 2); ?></p>
				</div>
				<form action="buy_stock.php" method="POST">
					<input type="hidden" name="stock_id" value="<?php echo $stock_id; ?>">
					<div class="form-group">
						<label for="quantity">Quantity:</label>
						<input type="number" id="quantity" name="quantity" min="1" max="<?php echo $max_quantity; ?>" required autocomplete="off">
					</div>
					<p class="total-cost">Total Cost: $0.00</p>
					<button type="submit" class="buy-button">Buy</button>
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="/js/stockPerformance.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const quantityInput = document.getElementById('quantity');
			const maxQuantity = <?php echo $max_quantity; ?>;
			const minQuantity = 1;
			const totalCostElement = document.querySelector('.total-cost');
			const stockPrice = <?php echo $stock['current_price']; ?>;
			quantityInput.addEventListener('input', function () {
				let quantity = parseInt(quantityInput.value, 10);
				if (isNaN(quantity) || quantity < minQuantity) {
					quantityInput.value = 0;
				}
				else if (quantity > maxQuantity) {
					quantityInput.value = maxQuantity;
				}
				quantity = parseInt(quantityInput.value, 10);
				const totalCost = (quantity * stockPrice).toFixed(2);
				totalCostElement.textContent = `Total Cost: $${totalCost}`;
			});
			document.querySelector('form').addEventListener('submit', function (e) {
				let quantity = parseInt(quantityInput.value, 10);
				if (isNaN(quantity) || quantity < minQuantity || quantity > maxQuantity) {
					e.preventDefault();
					alert(`Invalid quantity. Please enter a value between ${minQuantity} and ${maxQuantity}.`);
				}
			});
		});
	</script>
</body>
</html>
