<?php
require_once '../includes/db_connect.php';
require '../includes/PHPMailer/src/Exception.php';
require '../includes/PHPMailer/src/PHPMailer.php';
require '../includes/PHPMailer/src/SMTP.php';

function isMarketOpen($conn) {
    $dayOfWeek = date('l');
    $currentTime = date('H:i:s');

    $query = "SELECT * FROM default_market_hours 
              WHERE day_of_week = ? AND is_open = 1 AND open_time <= ? AND close_time >= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $dayOfWeek, $currentTime, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

function updateOpeningAndClosingPrices($conn) {
    $currentDate = date('Y-m-d');
    $dayOfWeek = date('l');
    $marketHoursQuery = "SELECT open_time, close_time FROM default_market_hours WHERE day_of_week = ?";
    $stmt = $conn->prepare($marketHoursQuery);
    $stmt->bind_param("s", $dayOfWeek);
    $stmt->execute();
    $result = $stmt->get_result();
    $marketHours = $result->fetch_assoc();
    $openTime = $marketHours['open_time'];
    $closeTime = $marketHours['close_time'];
    $stockQuery = "SELECT stock_id, current_price FROM stocks";
    $stockResult = $conn->query($stockQuery);

    while ($stock = $stockResult->fetch_assoc()) {
        $stockId = $stock['stock_id'];
        $openingPriceQuery = "
            SELECT price 
            FROM stock_price_history 
            WHERE stock_id = ? 
            AND DATE(timestamp) = ? 
            ORDER BY timestamp ASC 
            LIMIT 1";
        $stmt = $conn->prepare($openingPriceQuery);
        $stmt->bind_param("is", $stockId, $currentDate);
        $stmt->execute();
        $openingResult = $stmt->get_result();

        if ($openingResult->num_rows > 0) {
            $openingPrice = $openingResult->fetch_assoc()['price'];
            $updateOpeningPriceQuery = "UPDATE stocks SET opening_price = ? WHERE stock_id = ?";
            $stmt = $conn->prepare($updateOpeningPriceQuery);
            $stmt->bind_param("di", $openingPrice, $stockId);
            $stmt->execute();
        }
        if (!isMarketOpen($conn)) {
            $closingPriceQuery = "
                SELECT price 
                FROM stock_price_history 
                WHERE stock_id = ? 
                AND DATE(timestamp) = ? 
                ORDER BY timestamp DESC 
                LIMIT 1";
            $stmt = $conn->prepare($closingPriceQuery);
            $stmt->bind_param("is", $stockId, $currentDate);
            $stmt->execute();
            $closingResult = $stmt->get_result();

            if ($closingResult->num_rows > 0) {
                $closingPrice = $closingResult->fetch_assoc()['price'];
                $updateClosingPriceQuery = "UPDATE stocks SET closing_price = ? WHERE stock_id = ?";
                $stmt = $conn->prepare($updateClosingPriceQuery);
                $stmt->bind_param("di", $closingPrice, $stockId);
                $stmt->execute();
            }
        }
    }
}

function calculateNewPrice($currentPrice) {
    $eventProbability = rand(0, 1000);
    if ($eventProbability < 5) {
        $percentageChange = (rand(-300, -100) / 1000);
    } elseif ($eventProbability >= 5 && $eventProbability < 10) {
        $percentageChange = (rand(100, 300) / 1000);
    } else {
        $percentageChange = (rand(-20, 21) / 1000);
    }

    $priceChange = $currentPrice * $percentageChange;
    $newPrice = $currentPrice + $priceChange;

    $maxChange = 0.10 * $currentPrice;
    if ($newPrice < 0.5 * $currentPrice) {
        $newPrice = 0.5 * $currentPrice;
    } elseif ($newPrice > 1.5 * $currentPrice) {
        $newPrice = 1.5 * $currentPrice;
    }

    return [$newPrice, $percentageChange * 100];
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function processPendingTransactions($conn) {
    $query = "SELECT * FROM user_stock_transactions WHERE status = 'pending'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        while ($transaction = $result->fetch_assoc()) {
            $transactionId = $transaction['id'];
            $userId = $transaction['user_id'];
            $stockId = $transaction['stock_id'];
            $quantity = $transaction['quantity'];
            $initialPrice = $transaction['price_per_share'];
            $transactionType = $transaction['transaction_type'];
            $orderType = strtolower($transaction['order_type']);
            $limitPrice = $transaction['limit_price'];

            $priceQuery = "SELECT current_price, ticker_symbol FROM stocks WHERE stock_id = ?";
            $stmt = $conn->prepare($priceQuery);
            $stmt->bind_param("i", $stockId);
            $stmt->execute();
            $priceResult = $stmt->get_result();
            $stock = $priceResult->fetch_assoc();
            $currentPrice = $stock['current_price'];
            $tickerSymbol = $stock['ticker_symbol'];

            $executeTransaction = false;

            if ($orderType === 'market') {
                $executeTransaction = true;
            } elseif ($orderType === 'limit') {
                if ($transactionType === 'purchase' && $currentPrice <= $limitPrice) {
                    $executeTransaction = true;
                } elseif ($transactionType === 'sale' && $currentPrice >= $limitPrice) {
                    $executeTransaction = true;
                }
            }

            if ($executeTransaction) {
                if ($transactionType === 'purchase') {
                    $totalCost = $quantity * $currentPrice;
                    $balanceQuery = "SELECT balance FROM user_wallets WHERE user_id = ?";
                    $balanceStmt = $conn->prepare($balanceQuery);
                    $balanceStmt->bind_param("i", $userId);
                    $balanceStmt->execute();
                    $balanceResult = $balanceStmt->get_result();
                    $wallet = $balanceResult->fetch_assoc();

                    if ($wallet['balance'] >= $totalCost) {
                        $updateWalletQuery = "UPDATE user_wallets SET balance = balance - ? WHERE user_id = ?";
                        $walletStmt = $conn->prepare($updateWalletQuery);
                        $walletStmt->bind_param("di", $totalCost, $userId);
                        $walletStmt->execute();
                    } else {
                        continue;
                    }
                } elseif ($transactionType === 'sale') {
                    $totalEarnings = $quantity * $currentPrice;
                    $updateWalletQuery = "UPDATE user_wallets SET balance = balance + ? WHERE user_id = ?";
                    $walletStmt = $conn->prepare($updateWalletQuery);
                    $walletStmt->bind_param("di", $totalEarnings, $userId);
                    $walletStmt->execute();
                }

                $updateTransactionQuery = "UPDATE user_stock_transactions SET status = 'completed', price_per_share = ? WHERE id = ?";
                $transactionStmt = $conn->prepare($updateTransactionQuery);
                $transactionStmt->bind_param("di", $currentPrice, $transactionId);
                $transactionStmt->execute();

                sendTransactionEmail($userId, $transactionId, $tickerSymbol, $quantity, $transactionType, $initialPrice, $currentPrice);
            }
        }
    }
}

function sendTransactionEmail($userId, $transactionId, $tickerSymbol, $quantity, $transactionType, $initialPrice, $currentPrice) {
    global $conn;
    $userQuery = "SELECT first_name, email FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $firstName = $user['first_name'];
    $userEmail = $user['email'];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'donotreply@znctech.org';
        $mail->Password = 'rpnz cihj elzv ebmx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom('donotreply@znctech.org', 'ZNCTech');
        $mail->addAddress($userEmail, $firstName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Stock Transaction Processed - ZNCTech';

        $emailBody = file_get_contents('../includes/transaction_processed_template.html');
        $emailBody = str_replace('{{first_name}}', htmlspecialchars($firstName), $emailBody);
        $emailBody = str_replace('{{transaction_id}}', htmlspecialchars($transactionId), $emailBody);
        $emailBody = str_replace('{{ticker_symbol}}', htmlspecialchars($tickerSymbol), $emailBody);
        $emailBody = str_replace('{{quantity}}', htmlspecialchars($quantity), $emailBody);
        $emailBody = str_replace('{{initial_price}}', number_format($initialPrice, 2), $emailBody);
        $emailBody = str_replace('{{processed_price}}', number_format($currentPrice, 2), $emailBody);
        
        if ($transactionType === 'purchase') {
            $emailBody = str_replace('{{transaction_type}}', 'purchase', $emailBody);
            $emailBody = str_replace('{{total_cost}}', number_format($quantity * $currentPrice, 2), $emailBody);
        } else {
            $emailBody = str_replace('{{transaction_type}}', 'sale', $emailBody);
            $emailBody = str_replace('{{total_cost}}', number_format($quantity * $currentPrice, 2), $emailBody);
        }

        $mail->Body = $emailBody;
        $mail->send();
    } catch (Exception $e) {
        error_log("Error sending email for transaction $transactionId: " . $mail->ErrorInfo);
    }
}
function updateDailyVolume($conn) {
    $last24Hours = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $volumeQuery = "
        SELECT stock_id, SUM(quantity) AS daily_volume
        FROM user_stock_transactions
        WHERE transaction_date >= ? AND status = 'completed'
        GROUP BY stock_id";
    $stmt = $conn->prepare($volumeQuery);
    $stmt->bind_param("s", $last24Hours);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stockId = $row['stock_id'];
        $dailyVolume = $row['daily_volume'];
        
        $updateVolumeQuery = "UPDATE stocks SET volume = ? WHERE stock_id = ?";
        $updateStmt = $conn->prepare($updateVolumeQuery);
        $updateStmt->bind_param("ii", $dailyVolume, $stockId);
        $updateStmt->execute();
    }
    $resetVolumeQuery = "
        UPDATE stocks 
        SET volume = 0 
        WHERE stock_id NOT IN (SELECT stock_id FROM user_stock_transactions WHERE transaction_date >= ? AND status = 'completed')";
    $resetStmt = $conn->prepare($resetVolumeQuery);
    $resetStmt->bind_param("s", $last24Hours);
    $resetStmt->execute();
}




updateOpeningAndClosingPrices($conn);
updateDailyVolume($conn);

if (isMarketOpen($conn)) {
	processPendingTransactions($conn);
}

$query = "SELECT stock_id, current_price, fifty_two_week_high, fifty_two_week_low, previous_close, ticker_symbol FROM stocks";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stockId = $row['stock_id'];
        $currentPrice = $row['current_price'];
        $previousClose = $row['previous_close'];
        $earliestPriceTodayQuery = "
            SELECT price 
            FROM stock_price_history 
            WHERE stock_id = $stockId 
            AND DATE(timestamp) = CURDATE() 
            ORDER BY timestamp ASC 
            LIMIT 1";
        list($newPrice, $percentageChange) = calculateNewPrice($currentPrice);
		$beta = ($previousClose && $previousClose != 0) ? (($newPrice - $previousClose) / $previousClose) : 0;
        $earliestPriceTodayResult = $conn->query($earliestPriceTodayQuery);
        $earliestPriceToday = $earliestPriceTodayResult->num_rows > 0 ? $earliestPriceTodayResult->fetch_assoc()['price'] : null;

        $referencePrice = (!is_nan($previousClose) && $previousClose != 0) ? $previousClose : ($earliestPriceToday ?: $currentPrice);

        $percentageChangeFromReference = ($referencePrice != 0) ? round((($newPrice - $referencePrice) / $referencePrice) * 100, 2) : 0;
        $updateQuery = "
            UPDATE stocks 
            SET current_price = $newPrice,
                beta = $beta,
				percentage_change = $percentageChangeFromReference
            WHERE stock_id = $stockId";
        $conn->query($updateQuery);

        $insertHistoryQuery = "
            INSERT INTO stock_price_history (stock_id, price, change_percentage, timestamp)
            VALUES ($stockId, $newPrice, $percentageChange, NOW())";
        $conn->query($insertHistoryQuery);

        update52WeekHighLow($stockId, $newPrice);
    }
}

$conn->close();

function update52WeekHighLow($stockId, $latestPrice) {
    global $conn;
    $query = "
        SELECT price
        FROM stock_price_history
        WHERE stock_id = $stockId 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 52 WEEK)
        ORDER BY timestamp DESC";
    $result = $conn->query($query);

    $prices = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row['price'];
        }
    }
    $prices[] = $latestPrice;
    $newHigh = max($prices);
    $newLow = min($prices);
    $updateQuery = "
        UPDATE stocks 
        SET fifty_two_week_high = $newHigh, 
            fifty_two_week_low = $newLow
        WHERE stock_id = $stockId";
    $conn->query($updateQuery);
}
?>
