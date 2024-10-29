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

$sql = "(SELECT 'bank' as type, transaction_type, amount, NULL as ticker_symbol, NULL as quantity, bank_method_id as method_id, transaction_date, NULL as status, NULL as transaction_id, NULL as order_type, NULL as limit_price FROM user_bank_transactions WHERE user_id = ?)
        UNION ALL
        (SELECT 'stock' as type, transaction_type, quantity * price_per_share as amount, s.ticker_symbol, ust.quantity, ust.stock_id as method_id, transaction_date, ust.status, ust.id as transaction_id, ust.order_type, ust.limit_price FROM user_stock_transactions ust
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="wallet-container">
        <h1>Transactions</h1>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-container <?php echo $_SESSION['message_type']; ?>">
                <p><?php echo $_SESSION['message']; ?></p>
            </div>
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        endif;
        ?>
        <table class="payment-methods-table">
            <tr>
                <th>Type</th>
                <th>Transaction</th>
                <th>Stock</th>
                <th>Qty</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            <?php if ($transactions->num_rows > 0): ?>
                <?php while ($transaction = $transactions->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php if ($transaction['type'] === 'stock'): ?>
                            <i class="fas fa-shopping-cart transaction-icon purchase-icon" title="Stock Transaction"></i>
                        <?php else: ?>
                            <i class="fas fa-credit-card transaction-icon bank-icon" title="Bank Transaction"></i>
                        <?php endif; ?>
                    </td>

                    <td><?php echo ucfirst(htmlspecialchars($transaction['transaction_type'])); ?></td>

                    <td><?php echo $transaction['type'] === 'stock' ? htmlspecialchars($transaction['ticker_symbol']) : '---'; ?></td>

                    <td><?php echo $transaction['type'] === 'stock' ? htmlspecialchars($transaction['quantity']) : '---'; ?></td>

                    <td>$<?php echo number_format((float)$transaction['amount'], 2); ?></td>

                    <td>
						<?php if ($transaction['status'] === 'pending' && strtolower($transaction['order_type']) === 'limit'): ?>
							<span class="status-badge pending tooltip" 
								  data-tooltip="This limit order is pending until the market price reaches your specified limit of $<?php echo number_format((float)$transaction['limit_price'], 2); ?>">
								  Pending
							</span>
						<?php elseif ($transaction['status'] === 'pending'): ?>
							<span class="status-badge pending">Pending</span>
						<?php else: ?>
							<span class="status-badge <?php echo strtolower($transaction['status']); ?>">
								<?php echo ucfirst(htmlspecialchars($transaction['status'] ?? '---')); ?>
							</span>
						<?php endif; ?>
					</td>


                    <td><?php echo date("M j, g:i A", strtotime($transaction['transaction_date'])); ?></td>

                    <td>
                        <?php if ($transaction['type'] === 'stock' && $transaction['status'] === 'pending'): ?>
                            <a href="cancel_transaction.php?id=<?php echo $transaction['transaction_id']; ?>" class="cancel-icon" title="Cancel Transaction">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        <?php else: ?>
                            ---
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No transactions found.</td>
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
