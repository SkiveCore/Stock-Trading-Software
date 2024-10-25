<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'includes/db_connect.php';
$user_id = $_SESSION['user_id'];
$transactions_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $transactions_per_page;

$total_transactions_sql = "SELECT COUNT(*) as total FROM (
    SELECT id FROM user_bank_transactions WHERE user_id = ?
    UNION ALL
    SELECT id FROM user_stock_transactions WHERE user_id = ?
) as total_transactions";
$total_stmt = $conn->prepare($total_transactions_sql);
$total_stmt->bind_param('ii', $user_id, $user_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_transactions = $total_row['total'];
$total_pages = ceil($total_transactions / $transactions_per_page);

$sql = "(SELECT 'bank' as type, transaction_type, amount, NULL as ticker_symbol, NULL as quantity, bank_method_id as method_id, transaction_date FROM user_bank_transactions WHERE user_id = ?)
        UNION ALL
        (SELECT 'stock' as type, transaction_type, quantity * price_per_share as amount, s.ticker_symbol, ust.quantity, ust.stock_id as method_id, transaction_date FROM user_stock_transactions ust
        JOIN stocks s ON ust.stock_id = s.stock_id
        WHERE ust.user_id = ?)
        ORDER BY transaction_date DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $user_id, $user_id, $offset, $transactions_per_page);
$stmt->execute();
$transactions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Transactions</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/wallet.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="wallet-container">
        <h1>Transactions</h1>
        <table class="payment-methods-table">
            <tr>
                <th>Type</th>
                <th>Transaction Type</th>
                <th>Stock</th>
                <th>Quantity</th>
                <th>Amount</th>
                <th>Date</th>
            </tr>
            <?php if ($transactions->num_rows > 0): ?>
                <?php while ($transaction = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?php echo ucfirst(htmlspecialchars($transaction['type'])); ?></td>
                    <td><?php echo ucfirst(htmlspecialchars($transaction['transaction_type'])); ?></td>
                    <td>
                        <?php echo $transaction['type'] === 'stock' ? htmlspecialchars($transaction['ticker_symbol']) : '---'; ?>
                    </td>
                    <td>
                        <?php echo $transaction['type'] === 'stock' ? htmlspecialchars($transaction['quantity']) : '---'; ?>
                    </td>
                    <td>$<?php echo number_format((float)$transaction['amount'], 2); ?></td>
                    <td><?php echo date("F j, Y, g:i a", strtotime($transaction['transaction_date'])); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No transactions found.</td>
                </tr>
            <?php endif; ?>
        </table>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">&laquo; First</a>
                <a href="?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next</a>
                <a href="?page=<?php echo $total_pages; ?>">Last &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
</body>
</html>
