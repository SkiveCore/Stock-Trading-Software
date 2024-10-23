<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: /index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/metainfo.php"; ?>
    <title>ZNCTech - Admin</title>
    <link rel="stylesheet" href="/css/style.css">
	<link rel="stylesheet" href="/css/admin-style.css">
</head>
<body>
    <?php 
	include "../includes/header.php";
	?>
	<div class="container">
        <h2>Admin Dashboard</h2>

        <!-- Stock Table -->
        <h3>Current Stocks</h3>
        <table class="stock-table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Ticker Symbol</th>
                    <th>Current Price</th>
                    <th>Market Cap</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['ticker_symbol']); ?></td>
                            <td><?php echo htmlspecialchars($row['current_price']); ?></td>
                            <td><?php echo htmlspecialchars($row['market_cap']); ?></td>
                            <td><a href="../view_stock.php?id=<?php echo $row['stock_id']; ?>">View</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No stocks found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Add New Stock Form -->
        <h3>Add New Stock</h3>
        <form action="../add_stock.php" method="POST" class="add-stock-form">
            <label for="company_name">Company Name</label>
            <input type="text" id="company_name" name="company_name" required>

            <label for="ticker_symbol">Ticker Symbol</label>
            <input type="text" id="ticker_symbol" name="ticker_symbol" required>

            <label for="current_price">Current Price</label>
            <input type="number" step="0.01" id="current_price" name="current_price" required>

            <label for="market_cap">Market Cap</label>
            <input type="number" step="0.01" id="market_cap" name="market_cap" required>

            <!-- Add more fields as needed based on your database structure -->

            <button type="submit">Add Stock</button>
        </form>
    </div>
	<?php
	include "../includes/footer.php";
	?>

</body>
</html>
