<?php
$user_id = $_SESSION['user_id'];

require_once 'includes/db_connect.php';

// Fetch the user's wallet balance
$wallet_sql = "SELECT balance FROM user_wallets WHERE user_id = ?";
$wallet_stmt = $conn->prepare($wallet_sql);
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$wallet = $wallet_result->fetch_assoc();
$balance = $wallet['balance'] ?? 0.00;

// Fetch the stocks owned by the user
$stocks_sql = "SELECT s.ticker_symbol, s.company_name, s.current_price, s.percentage_change, SUM(ust.quantity) as total_quantity
               FROM user_stock_transactions ust
               JOIN stocks s ON ust.stock_id = s.stock_id
               WHERE ust.user_id = ?
               GROUP BY s.ticker_symbol, s.company_name, s.current_price, s.percentage_change";
$stocks_stmt = $conn->prepare($stocks_sql);
$stocks_stmt->bind_param("i", $user_id);
$stocks_stmt->execute();
$stocks_result = $stocks_stmt->get_result();
$user_stocks = $stocks_result->fetch_all(MYSQLI_ASSOC);
$stock_count = count($user_stocks);

if ($stock_count < 10) {
    $remaining_count = 10 - $stock_count;
    $owned_stock_ids = array_column($user_stocks, 'ticker_symbol');
    
    if (!empty($owned_stock_ids)) {
        // If there are owned stock IDs, exclude them from the random selection
        $placeholders = implode(',', array_fill(0, count($owned_stock_ids), '?'));
        $random_stocks_sql = "SELECT ticker_symbol, company_name, current_price, percentage_change 
                              FROM stocks 
                              WHERE ticker_symbol NOT IN ($placeholders)
                              ORDER BY RAND() LIMIT ?";
        $random_stmt = $conn->prepare($random_stocks_sql);

        // Dynamically bind parameters
        $types = str_repeat('s', count($owned_stock_ids)) . 'i';
        $params = array_merge($owned_stock_ids, [$remaining_count]);
        $random_stmt->bind_param($types, ...$params);
    } else {
        // If there are no owned stocks, just select random stocks
        $random_stocks_sql = "SELECT ticker_symbol, company_name, current_price, percentage_change 
                              FROM stocks 
                              ORDER BY RAND() LIMIT ?";
        $random_stmt = $conn->prepare($random_stocks_sql);
        $random_stmt->bind_param("i", $remaining_count);
    }

    $random_stmt->execute();
    $random_result = $random_stmt->get_result();
    $random_stocks = $random_result->fetch_all(MYSQLI_ASSOC);
} else {
    $random_stocks = [];
}

?>
<!DOCTYPE html>
    <link rel="stylesheet" href="css/dashboard.css">
    <div class="trading-dashboard-container">
        <div class="chart-container">
            <h2>Portfolio Performance</h2>
            <canvas id="stockChart"></canvas>

            <div class="timeframe-buttons">
                <span class="time-button selected" onclick="updateChart('1d')">1D</span>
                <span class="time-button" onclick="updateChart('1w')">1W</span>
                <span class="time-button" onclick="updateChart('1m')">1M</span>
                <span class="time-button" onclick="updateChart('3m')">3M</span>
                <span class="time-button" onclick="updateChart('ytd')">YTD</span>
                <span class="time-button" onclick="updateChart('1y')">1Y</span>
                <span class="time-button" onclick="updateChart('all')">ALL</span>
            </div>

            <div class="account-info">
                <p>Buying Power: $<?php echo number_format((float)$balance, 2); ?></p>
                <p>Cash Account: $<?php echo number_format((float)$balance, 2); ?></p>
            </div>
        </div>
        <div class="stock-list">
			<h3>Your Stocks</h3>
			<ul>
				<?php if ($stock_count > 0): ?>
					<?php foreach ($user_stocks as $stock): 
						$change_class = $stock['percentage_change'] >= 0 ? 'positive' : 'negative';
					?>
						<li class="stock-item">
							<a href="/<?php echo urlencode($stock['ticker_symbol']); ?>" class="stock-link">
								<div class="stock-info">
									<div class="stock-header">
										<span class="stock-symbol"><?php echo htmlspecialchars($stock['ticker_symbol']); ?></span>
										<span class="stock-name"><?php echo htmlspecialchars($stock['company_name']); ?></span>
									</div>
									<div class="stock-details">
										<div class="stock-price-quantity">
											<span class="stock-price">$<?php echo number_format($stock['current_price'], 2); ?></span>
											<span class="stock-quantity">Quantity: <?php echo htmlspecialchars($stock['total_quantity']); ?></span>
										</div>
										<span class="stock-change <?php echo $change_class; ?>">
											<?php echo number_format($stock['percentage_change'], 2); ?>%
										</span>
									</div>
								</div>
							</a>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
				<?php foreach ($random_stocks as $random_stock):
					$change_class = $random_stock['percentage_change'] >= 0 ? 'positive' : 'negative'; ?>
					<li class="stock-item">
						<a href="/<?php echo urlencode($random_stock['ticker_symbol']); ?>" class="stock-link">
							<div class="stock-info">
								<div class="stock-header">
									<span class="stock-symbol"><?php echo htmlspecialchars($random_stock['ticker_symbol']); ?></span>
									<span class="stock-name"><?php echo htmlspecialchars($random_stock['company_name']); ?></span>
								</div>
								<div class="stock-details">
									<div class="stock-price-quantity">
										<span class="stock-price">$<?php echo number_format($random_stock['current_price'], 2); ?></span>
										<span class="stock-quantity">Quantity: N/A</span>
									</div>
									<span class="stock-change <?php echo $change_class; ?>"><?php echo number_format($random_stock['percentage_change'], 2); ?>%</span>
								</div>
							</div>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
    </div>
