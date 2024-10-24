<?php
require_once '../includes/db_connect.php';

function calculateNewPrice($currentPrice) {
    $eventProbability = rand(0, 1000);
    $percentageChange = 0;
    if ($eventProbability < 5) {
        $percentageChange = (rand(-300, -100) / 1000);
    } elseif ($eventProbability >= 5 && $eventProbability < 10) {
        $percentageChange = (rand(100, 300) / 1000);
    } else {
        $percentageChange = (rand(-20, 20) / 1000);
    }

    $priceChange = $currentPrice * $percentageChange;
    $newPrice = $currentPrice + $priceChange;
    if ($newPrice < 0.1 * $currentPrice) {
        $newPrice = 0.1 * $currentPrice;
    } elseif ($newPrice > 2 * $currentPrice) {
        $newPrice = 2 * $currentPrice;
    }

    return [$newPrice, $percentageChange * 100];
}

$query = "SELECT stock_id, current_price, fifty_two_week_high, fifty_two_week_low, previous_close, ticker_symbol FROM stocks";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stockId = $row['stock_id'];
        $currentPrice = $row['current_price'];
        $previousClose = $row['previous_close'];

        // Find the earliest price today if no previous close
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
		#echo "$$percentageChangeFromReference = ($referencePrice != 0) ? round((($newPrice - $referencePrice) / $referencePrice) * 100, 2) : 0;";
		#echo "<br>$stockId - $percentageChangeFromReference<br>";
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
