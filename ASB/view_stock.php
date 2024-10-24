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
                    <p><strong>Sector:</strong> <span class="editable" data-field="sector"><?php echo htmlspecialchars($stock['sector'] ?? 'N/A'); ?></span> <span class="edit-icon" onclick="editDropdownField('sector')">✏️</span></p>
                    <p><strong>Industry:</strong> <span class="editable" data-field="industry"><?php echo htmlspecialchars($stock['industry'] ?? 'N/A'); ?></span> <span class="edit-icon" onclick="editDropdownField('industry')">✏️</span></p>
                    <p><strong>Stock Exchange:</strong> <span class="editable" data-field="stock_exchange"><?php echo htmlspecialchars($stock['stock_exchange'] ?? 'N/A'); ?></span> <span class="edit-icon" onclick="editDropdownField('stock_exchange')">✏️</span></p>
                    <p><strong>CEO:</strong> <span class="editable" data-field="company_ceo"><?php echo htmlspecialchars($stock['company_ceo'] ?? 'N/A'); ?></span> <span class="edit-icon" onclick="editField('company_ceo')">✏️</span></p>
                </div>

                <div class="financial-metrics">
                    <h4>Financial Metrics</h4>
                    <p><strong>PE Ratio:</strong> <?php echo $stock['pe_ratio'] !== null ? number_format($stock['pe_ratio'], 2) : 'N/A'; ?></p>
                    <p><strong>EPS (Earnings Per Share):</strong> <?php echo $stock['eps'] !== null ? number_format($stock['eps'], 2) : 'N/A'; ?></p>
                    <p><strong>Dividend Yield:</strong> <?php echo $stock['dividend_yield'] !== null ? number_format($stock['dividend_yield'], 2) . '%' : 'N/A'; ?></p>
                    <p><strong>Dividend Per Share:</strong> <span class="editable" data-field="dividend_per_share"><?php echo $stock['dividend_per_share'] !== null ? '$' . number_format($stock['dividend_per_share'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('dividend_per_share')">✏️</span></p>
                    <p><strong>Beta:</strong> <?php echo $stock['beta'] !== null ? number_format($stock['beta'], 2) : 'N/A'; ?></p>
                    <p><strong>Revenue:</strong> <span class="editable" data-field="revenue"><?php echo $stock['revenue'] !== null ? '$' . number_format($stock['revenue'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('revenue')">✏️</span></p>
                    <p><strong>Net Income:</strong> <span class="editable" data-field="net_income"><?php echo $stock['net_income'] !== null ? '$' . number_format($stock['net_income'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('net_income')">✏️</span></p>
                    <p><strong>Total Debt:</strong> <span class="editable" data-field="total_debt"><?php echo $stock['total_debt'] !== null ? '$' . number_format($stock['total_debt'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('total_debt')">✏️</span></p>
                    <!--<p><strong>Operating Income:</strong> <span class="editable" data-field="operating_income"><?php echo $stock['operating_income'] !== null ? '$' . number_format($stock['operating_income'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('operating_income')">✏️</span></p>-->
                    <p><strong>Price-to-Sales Ratio:</strong> <?php echo $stock['price_to_sales_ratio'] !== null ? number_format($stock['price_to_sales_ratio'], 2) : 'N/A'; ?></p>
                    <!--<p><strong>Cash Flow Per Share:</strong> <span class="editable" data-field="cash_flow_per_share"><?php echo $stock['cash_flow_per_share'] !== null ? '$' . number_format($stock['cash_flow_per_share'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('cash_flow_per_share')">✏️</span></p>-->
                    <p><strong>Total Assets:</strong> <span class="editable" data-field="total_assets"><?php echo $stock['total_assets'] !== null ? '$' . number_format($stock['total_assets'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('total_assets')">✏️</span></p>
                    <p><strong>Total Liabilities:</strong> <span class="editable" data-field="total_liabilities"><?php echo $stock['total_liabilities'] !== null ? '$' . number_format($stock['total_liabilities'], 2) : 'N/A'; ?></span> <span class="edit-icon" onclick="editField('total_liabilities')">✏️</span></p>
                    <p><strong>Shareholder Equity:</strong> <?php echo $stock['shareholder_equity'] !== null ? '$' . number_format($stock['shareholder_equity'], 2) : 'N/A'; ?></p>
                    <p><strong>Debt-to-Equity Ratio:</strong> <?php echo $stock['debt_to_equity_ratio'] !== null ? number_format($stock['debt_to_equity_ratio'], 2) : 'N/A'; ?></p>
                    <p><strong>Return on Equity:</strong> <?php echo $stock['return_on_equity'] !== null ? number_format($stock['return_on_equity'], 2) . '%' : 'N/A'; ?></p>
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

        function editDropdownField(field) {
			console.log(`Editing dropdown field: ${field}`);
            const span = document.querySelector(`.editable[data-field='${field}']`);
            const currentValue = span.textContent;
            const select = document.createElement('select');
            const options = getOptionsForField(field);

            options.forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option;
                optionElement.textContent = option;
                if (option === currentValue) {
                    optionElement.selected = true;
                }
                select.appendChild(optionElement);
            });

            select.onchange = function() {
                updateField(field, select.value);
                span.textContent = select.value;
                span.style.display = 'inline';
                select.remove();
            };

            span.style.display = 'none';
            span.parentElement.insertBefore(select, span.nextSibling);
        }

        function editField(field) {
            const span = document.querySelector(`.editable[data-field='${field}']`);
            const currentValue = span.textContent;
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue;
            input.onblur = function() {
                updateField(field, input.value);
                span.textContent = input.value;
                span.style.display = 'inline';
                input.remove();
            };
            span.style.display = 'none';
            span.parentElement.insertBefore(input, span.nextSibling);
            input.focus();
        }

        function updateField(field, value) {
			const stockId = <?php echo $stock_id; ?>;
			console.log(`Updating field: ${field}, value: ${value}`); // Add this line
			fetch('/ASB/update_stock.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({ stock_id: stockId, field, value })
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					console.log('Field updated successfully.');
					if (['net_income', 'outstanding_shares', 'revenue', 'dividend_per_share', 'current_price', 'total_debt', 'total_assets', 'total_liabilities'].includes(field)) {
                    	recalculateDerivedFields();
					}
				} else {
					console.error('Error updating field:', data.message);
				}
			})
			.catch(error => console.error('Error:', error));
		}
		function recalculateDerivedFields() {
			const stockId = <?php echo $stock_id; ?>;
			fetch(`/ASB/recalculate_stock.php?stock_id=${stockId}`)
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						const fieldsToUpdate = [
							{ field: 'pe_ratio', value: data.pe_ratio },
							{ field: 'eps', value: data.eps },
							{ field: 'price_to_sales_ratio', value: data.price_to_sales_ratio },
							{ field: 'debt_to_equity_ratio', value: data.debt_to_equity_ratio },
							{ field: 'return_on_equity', value: data.return_on_equity }
						];

						fieldsToUpdate.forEach(item => {
							const element = document.querySelector(`.editable[data-field="${item.field}"]`);
							if (element) {
								element.textContent = item.value;
							}
						});
					} else {
						console.error('Error recalculating fields:', data.message);
					}
				})
				.catch(error => console.error('Error:', error));
		}
        function getOptionsForField(field) {
            switch (field) {
                case 'sector':
                    return ['Technology', 'Finance', 'Healthcare', 'Consumer Goods', 'Energy', 'Utilities'];
                case 'industry':
                    return ['Software', 'Banking', 'Pharmaceuticals', 'Retail', 'Oil & Gas', 'Telecommunications'];
                case 'stock_exchange':
                    return ['NYSE', 'NASDAQ', 'LSE', 'TSX', 'ASX'];
                default:
                    return [];
            }
        }
    </script>
</body>
</html>
