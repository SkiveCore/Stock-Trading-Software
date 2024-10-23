<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: /404.php');
    exit();
}
require '../includes/db_connect.php';

$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;


$query = "SELECT * FROM stocks LIMIT $limit OFFSET $offset";
$result = $conn->query($query);


$countQuery = "SELECT COUNT(*) as total FROM stocks";
$countResult = $conn->query($countQuery);
$totalStocks = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalStocks / $limit);
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
    <?php include "../includes/header.php"; ?>

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
                            <td><?php echo '$' . number_format($row['current_price'], 2); ?></td>
                            <td><?php echo '$' . number_format($row['market_cap'], 2); ?></td>
                            <td><a href="view_stock.php?id=<?php echo $row['stock_id']; ?>">View</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No stocks found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li><a href="?page=1">First</a></li>
                <li><a href="?page=<?php echo $page - 1; ?>">&lt;</a></li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li><a href="?page=<?php echo $i; ?>" <?php echo $i === $page ? 'style="font-weight: bold;"' : ''; ?>><?php echo $i; ?></a></li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <li><a href="?page=<?php echo $page + 1; ?>">&gt;</a></li>
                <li><a href="?page=<?php echo $totalPages; ?>">Last</a></li>
            <?php endif; ?>
        </ul>

        
        <h3>Add New Stock</h3>
        <form action="add_stock.php" method="POST" class="add-stock-form">
            <label for="company_name">Company Name</label>
            <input type="text" id="company_name" name="company_name" required>

            <label for="ticker_symbol">Ticker Symbol</label>
            <input type="text" id="ticker_symbol" name="ticker_symbol" required>

            <label for="current_price">Current Price</label>
            <input type="number" step="0.01" id="current_price" name="current_price" required>

            <label for="outstanding_shares">Outstanding Shares</label>
            <input type="number" step="1" id="outstanding_shares" name="outstanding_shares" required>

            <button type="submit">Add Stock</button>
        </form>
    </div>
    <?php include "../includes/footer.php"; ?>
</body>
</html>
