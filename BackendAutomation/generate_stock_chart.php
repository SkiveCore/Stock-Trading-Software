<?php
require_once '../includes/db_connect.php';

$stock_symbol = $_GET['symbol'] ?? 'AAPL';
$stock_id = intval($_GET['id'] ?? 0);
$stock_name = $stock_symbol;
$cache_interval = 3600;
$timestamp = date("Y-m-d-H-i");
$base_dir = "../images/{$stock_name}";
$chart_base_dir = "{$base_dir}/{$timestamp}";
$latest_image_dir = null;
if (is_dir($base_dir)) {
    $dirs = array_filter(glob("$base_dir/*"), 'is_dir');
    usort($dirs, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $latest_image_dir = $dirs[0] ?? null;
}
if ($latest_image_dir && (time() - filemtime($latest_image_dir) < $cache_interval)) {
    echo json_encode(['success' => true, 'image_base_path' => "/images/{$stock_symbol}/" . basename($latest_image_dir) . "/{$stock_symbol}_chart"]);
    exit();
}
if (!is_dir($chart_base_dir)) {
    mkdir($chart_base_dir, 0777, true);
}

$resolutions = [1500, 1024, 512, 256, 128];
$formats = ['png', 'webp', 'jpeg'];

function save_chart_image($image, $filename, $format) {
    switch ($format) {
        case 'webp':
            return imagewebp($image, $filename);
        case 'jpeg':
            return imagejpeg($image, $filename);
        case 'png':
        default:
            return imagepng($image, $filename);
    }
}
$query = "
    SELECT timestamp, price
    FROM stock_price_history
    WHERE stock_id = $stock_id
    ORDER BY timestamp ASC
";
$result = $conn->query($query);

$timestamps = [];
$prices = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = $row['timestamp'];
        $prices[] = (float) $row['price'];
    }
}

if (empty($timestamps) || empty($prices)) {
    echo json_encode(['success' => false, 'message' => 'No data found']);
    exit();
}
foreach ($resolutions as $width) {
    $height = (int) ($width / 2);
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    $lineColor = imagecolorallocate($image, 75, 192, 192);
    $textColor = imagecolorallocate($image, 0, 0, 0);

    $max_price = max($prices);
    $min_price = min($prices);
    $price_range = $max_price - $min_price;
    $num_points = count($prices);

    for ($i = 0; $i < $num_points - 1; $i++) {
        $x1 = (int)(($i / ($num_points - 1)) * $width);
        $y1 = (int)($height - (($prices[$i] - $min_price) / $price_range) * $height);
        $x2 = (int)((($i + 1) / ($num_points - 1)) * $width);
        $y2 = (int)($height - (($prices[$i + 1] - $min_price) / $price_range) * $height);
        imageline($image, $x1, $y1, $x2, $y2, $lineColor);
    }

    $label = "$stock_symbol ({$timestamps[0]} to {$timestamps[count($timestamps) - 1]})";
    imagestring($image, 5, 10, 10, $label, $textColor);

    foreach ($formats as $format) {
        $filename = "{$chart_base_dir}/{$stock_symbol}_chart_{$width}.{$format}";
        save_chart_image($image, $filename, $format);
    }

    imagedestroy($image);
}

echo json_encode(['success' => true, 'image_base_path' => "/images/{$stock_symbol}/{$timestamp}/{$stock_symbol}_chart"]);
?>
