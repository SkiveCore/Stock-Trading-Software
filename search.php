<?php

require 'includes/db_connect.php';

$query = $_GET['query'] ?? '';
$results = [];

if (!empty($query)) {
    $stmt = $conn->prepare("SELECT ticker_symbol, company_name, current_price, percentage_change FROM stocks WHERE company_name LIKE ? OR ticker_symbol LIKE ?");
    $searchTerm = "%" . $query . "%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>Search Results - ZNCTech</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/search.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="container">
        <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
        <?php if (count($results) > 0): ?>
            <div class="search-results-list">
                <?php foreach ($results as $stock): ?>
                    <a href="/<?php echo urlencode($stock['ticker_symbol']); ?>" class="search-result-link">
                        <div class="search-result-item">
                            <div class="stock-info">
                                <span class="stock-symbol"><?php echo htmlspecialchars($stock['ticker_symbol']); ?></span>
                                <span class="stock-name"><?php echo htmlspecialchars($stock['company_name']); ?></span>
                            </div>
                            <div class="stock-stats">
                                <span class="stock-price">$<?php echo number_format($stock['current_price'], 2); ?></span>
                                <?php 
                                    $percentageChange = (float) $stock['percentage_change'];
                                    $changeClass = $percentageChange >= 0 ? 'positive' : 'negative';
                                    $percentageText = is_nan($percentageChange) ? '---' : number_format($percentageChange, 2) . '%';
                                ?>
                                <span class="stock-change <?php echo $changeClass; ?>">
                                    <?php echo $percentageText; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No results found for "<?php echo htmlspecialchars($query); ?>"</p>
        <?php endif; ?>
    </div>
    <?php include "includes/footer.php"; ?>
</body>
</html>
