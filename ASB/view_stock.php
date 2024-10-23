<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: /404.php');
    exit();
}
require_once '../includes/db_connect.php';

$stock_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$query = "SELECT * FROM stocks WHERE stock_id = $stock_id";
$result = $conn->query($query);
$stock = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/metainfo.php"; ?>
    <title>ZNCTech - View Stock</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
</head>
<body>
    <?php include "../includes/header.php"; ?>

    <div class="stock-container">
        <div class="stock-header">
            <h2>Stock Details - <?php echo htmlspecialchars($stock['company_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($stock['ticker_symbol'] ?? 'N/A'); ?>)</h2>
        </div>
        <?php if ($stock): ?>
            <div class="stock-info">
                <div class="price-section">
                    <div class="price-card">
                        <h4>Current Price</h4>
                        <p>$<?php echo number_format($stock['current_price'] ?? 0, 2); ?></p>
                    </div>
                    <div class="price-card">
                        <h4>Opening Price</h4>
                        <p>$<?php echo number_format($stock['opening_price'] ?? 0, 2); ?></p>
                    </div>
                    <div class="price-card">
                        <h4>Closing Price</h4>
                        <p>$<?php echo number_format($stock['closing_price'] ?? 0, 2); ?></p>
                    </div>
                </div>

                <div class="performance-section">
                    <h4>52-Week Performance</h4>
                    <div class="performance-card">
                        <div>
                            <span class="arrow-up">↑</span> High: $<?php echo number_format($stock['fifty_two_week_high'] ?? 0, 2); ?>
                        </div>
                        <div>
                            <span class="arrow-down">↓</span> Low: $<?php echo number_format($stock['fifty_two_week_low'] ?? 0, 2); ?>
                        </div>
                    </div>
                </div>

                <div class="market-volume-section">
                    <div class="market-card">
                        <h4>Market Cap</h4>
                        <p>$<?php echo number_format($stock['market_cap'] ?? 0, 2); ?></p>
                    </div>
                    <div class="volume-card">
                        <h4>Volume</h4>
                        <p><?php echo number_format($stock['volume'] ?? 0); ?></p>
                    </div>
                </div>

                <div class="company-info">
                    <h4>Company Information</h4>
                    <p><strong>Sector:</strong> <?php echo htmlspecialchars($stock['sector'] ?? 'N/A'); ?></p>
                    <p><strong>Industry:</strong> <?php echo htmlspecialchars($stock['industry'] ?? 'N/A'); ?></p>
                    <p><strong>Stock Exchange:</strong> <?php echo htmlspecialchars($stock['stock_exchange'] ?? 'N/A'); ?></p>
                    <p><strong>CEO:</strong> <?php echo htmlspecialchars($stock['company_ceo'] ?? 'N/A'); ?></p>
                </div>

                <div class="financial-metrics">
                    <h4>Financial Metrics</h4>
                    <p><strong>PE Ratio:</strong> <?php echo $stock['pe_ratio'] !== null ? number_format($stock['pe_ratio'], 2) : 'N/A'; ?></p>
                    <p><strong>Dividend Yield:</strong> <?php echo $stock['dividend_yield'] !== null ? number_format($stock['dividend_yield'], 2) . '%' : 'N/A'; ?></p>
                    <p><strong>EPS (Earnings Per Share):</strong> <?php echo $stock['eps'] !== null ? number_format($stock['eps'], 2) : 'N/A'; ?></p>
                    <p><strong>Beta:</strong> <?php echo $stock['beta'] !== null ? number_format($stock['beta'], 2) : 'N/A'; ?></p>
                </div>

                <div class="update-info">
                    <p><strong>Last Trade Datetime:</strong> <?php echo htmlspecialchars($stock['last_trade_datetime'] ?? 'N/A'); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo htmlspecialchars($stock['last_update'] ?? 'N/A'); ?></p>
                </div>
				<div class="back-button-container">
                    <button onclick="goBack()" class="back-button">Back to Admin Panel</button>
                </div>
            </div>
        <?php else: ?>
            <p>Stock not found.</p>
        <?php endif; ?>
    </div>

    <?php include "../includes/footer.php"; ?>
	<script>
        function goBack() {
            window.history.back();
        }
    </script>
</body>
</html>
