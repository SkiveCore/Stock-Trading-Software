<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once '../includes/db_connect.php';

$stock_id = intval($_GET['stock_id']);
$query = "SELECT * FROM stocks WHERE stock_id = $stock_id";
$result = $conn->query($query);
$stock = $result->fetch_assoc();

if ($stock) {
    $net_income = $stock['net_income'];
    $outstanding_shares = $stock['outstanding_shares'];
    $current_price = $stock['current_price'];
    $revenue = $stock['revenue'];
    $total_debt = $stock['total_debt'];
    $total_assets = $stock['total_assets'];
    $total_liabilities = $stock['total_liabilities'];
	
	
	$shareholder_equity = $total_assets - $total_liabilities;

    $eps = $outstanding_shares ? $net_income / $outstanding_shares : 0;
    $pe_ratio = $eps ? $current_price / $eps : 0;
    $price_to_sales_ratio = $revenue && $outstanding_shares ? $current_price / ($revenue / $outstanding_shares) : 0;
	
	$debt_to_equity_ratio = $shareholder_equity ? $total_debt / $shareholder_equity : 0;

	$return_on_equity = $shareholder_equity ? ($net_income / $shareholder_equity) * 100 : 0;


    $updateQuery = "UPDATE stocks 
                    SET eps = '$eps', 
                        pe_ratio = '$pe_ratio', 
                        price_to_sales_ratio = '$price_to_sales_ratio', 
                        debt_to_equity_ratio = '$debt_to_equity_ratio',
                        return_on_equity = '$return_on_equity',
                        shareholder_equity = '$shareholder_equity' 
                    WHERE stock_id = $stock_id";
    if ($conn->query($updateQuery) === TRUE) {
        echo json_encode([
            'success' => true, 
            'pe_ratio' => number_format($pe_ratio, 2), 
            'eps' => number_format($eps, 2), 
            'price_to_sales_ratio' => number_format($price_to_sales_ratio, 2), 
            'debt_to_equity_ratio' => number_format($debt_to_equity_ratio, 2),
            'return_on_equity' => number_format($return_on_equity, 2),
            'shareholder_equity' => number_format($shareholder_equity, 2)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Stock not found']);
}
?>
