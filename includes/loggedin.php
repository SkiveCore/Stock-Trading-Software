<?php
$user_id = $_SESSION['user_id'];

require_once 'includes/db_connect.php';
$wallet_sql = "SELECT balance FROM user_wallets WHERE user_id = ?";
$wallet_stmt = $conn->prepare($wallet_sql);
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$wallet = $wallet_result->fetch_assoc();
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

$stocks_sql = "
    SELECT 
        s.ticker_symbol, 
        s.company_name, 
        s.current_price, 
        s.percentage_change, 
        (COALESCE(SUM(CASE WHEN ust.transaction_type = 'purchase' AND ust.status = 'completed' THEN ust.quantity ELSE 0 END), 0) -
         COALESCE(SUM(CASE WHEN ust.transaction_type = 'sale' AND ust.status = 'completed' THEN ust.quantity ELSE 0 END), 0)) AS total_quantity
    FROM 
        user_stock_transactions ust
    JOIN 
        stocks s ON ust.stock_id = s.stock_id
    WHERE 
        ust.user_id = ?
    GROUP BY 
        s.ticker_symbol, s.company_name, s.current_price, s.percentage_change
";

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
        $placeholders = implode(',', array_fill(0, count($owned_stock_ids), '?'));
        $random_stocks_sql = "SELECT ticker_symbol, company_name, current_price, percentage_change 
                              FROM stocks 
                              WHERE ticker_symbol NOT IN ($placeholders)
                              ORDER BY RAND() LIMIT ?";
        $random_stmt = $conn->prepare($random_stocks_sql);
        $types = str_repeat('s', count($owned_stock_ids)) . 'i';
        $params = array_merge($owned_stock_ids, [$remaining_count]);
        $random_stmt->bind_param($types, ...$params);
    } else {
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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . '/BackendAutomation';
$chart_image_url = "{$base_url}/generate_portfolio_chart.php?user_id={$user_id}";
$ch = curl_init($chart_image_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

$chart_image_response = curl_exec($ch);
curl_close($ch);

$chart_image_data = json_decode($chart_image_response, true);




if ($chart_image_data && $chart_image_data['success'] && isset($chart_image_data['image_base_path'])) {
    $image_base_path = $chart_image_data['image_base_path'];
} else {
    $image_base_path = "/images/default_chart";
}
?>
    <link rel="stylesheet" href="css/dashboard.css">
    <div class="trading-dashboard-container">
        <div class="chart-container">
            <h2>Portfolio Performance</h2>
            <canvas id="stockChart"></canvas>

			<noscript>
                <style>
                    .timeframe-buttons, #stockChart { display: none; }
                </style>
                <p class="noscript-message">JavaScript is required for an interactive chart. Here’s the latest portfolio performance chart:</p>
				<picture>
					<source srcset="<?php echo "{$image_base_path}_1500.webp 1500w, {$image_base_path}_1024.webp 1024w, {$image_base_path}_512.webp 512w, {$image_base_path}_256.webp 256w, {$image_base_path}_128.webp 128w"; ?>" type="image/webp">
					<source srcset="<?php echo "{$image_base_path}_1500.png 1500w, {$image_base_path}_1024.png 1024w, {$image_base_path}_512.png 512w, {$image_base_path}_256.png 256w, {$image_base_path}_128.png 128w"; ?>" type="image/png">
					<img src="<?php echo "{$image_base_path}_256.png"; ?>" loading="lazy" fetchpriority="low" alt="Portfolio Performance Chart" class="responsive-chart-image noscript-chart-image">
				</picture>
            </noscript>
			<div class="timeframe-buttons">
				<span class="time-button selected" data-timeframe="1d">1D</span>
				<span class="time-button" data-timeframe="1w">1W</span>
				<span class="time-button" data-timeframe="1m">1M</span>
				<span class="time-button" data-timeframe="3m">3M</span>
				<span class="time-button" data-timeframe="ytd">YTD</span>
				<span class="time-button" data-timeframe="1y">1Y</span>
				<span class="time-button" data-timeframe="all">ALL</span>
			</div>


            <div class="account-info">
                <p>Buying Power: $<?php echo number_format((float)$buying_power, 2); ?></p>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.1.0"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/stockPortfolio.js" defer></script>
