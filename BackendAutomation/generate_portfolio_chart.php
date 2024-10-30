<?php
session_start();
require_once '../includes/db_connect.php';

$user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? 0;

if ($user_id === 0) {
    echo json_encode(['success' => false, 'message' => 'User ID is missing.']);
    exit();
}
$cache_interval = 3600;
$timestamp = date("Y-m-d-H-i");
$chart_base_dir = "../images/portfolio/{$user_id}/{$timestamp}";
$latest_image_dir = null;
if (is_dir("../images/portfolio/{$user_id}")) {
    $dirs = array_filter(glob("../images/portfolio/{$user_id}/*"), 'is_dir');
    usort($dirs, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $latest_image_dir = $dirs[0] ?? null;
}
if ($latest_image_dir && (time() - filemtime($latest_image_dir) < $cache_interval)) {
    echo json_encode(['success' => true, 'image_base_path' => "/images/portfolio/{$user_id}/" . basename($latest_image_dir) . "/portfolio_chart"]);
    exit();
}
if (!is_dir($chart_base_dir)) {
    mkdir($chart_base_dir, 0777, true);
}
$query = "
    SELECT hsh.timestamp, SUM(ust.quantity * hsh.price) AS portfolio_value
    FROM user_stock_transactions ust
    JOIN stock_price_history hsh ON ust.stock_id = hsh.stock_id
    WHERE ust.user_id = ? AND ust.status = 'completed' AND hsh.timestamp <= NOW()
    GROUP BY hsh.timestamp
    ORDER BY hsh.timestamp ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
$values = [];
while ($row = $result->fetch_assoc()) {
    $dates[] = $row['timestamp'];
    $values[] = (float) $row['portfolio_value'];
}
if (empty($dates) || empty($values)) {
    echo json_encode(['success' => false, 'message' => 'No data found']);
    exit();
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

foreach ($resolutions as $width) {
    $height = (int) ($width / 2);
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    $lineColor = imagecolorallocate($image, 75, 192, 192);
    $textColor = imagecolorallocate($image, 0, 0, 0);
    $max_value = max($values);
    $min_value = min($values);
    $value_range = $max_value - $min_value;
    $num_points = count($values);
    for ($i = 0; $i < $num_points - 1; $i++) {
        $x1 = (int)(($i / ($num_points - 1)) * $width);
        $y1 = (int)($height - (($values[$i] - $min_value) / $value_range) * $height);
        $x2 = (int)((($i + 1) / ($num_points - 1)) * $width);
        $y2 = (int)($height - (($values[$i + 1] - $min_value) / $value_range) * $height);
        imageline($image, $x1, $y1, $x2, $y2, $lineColor);
    }
    $label = "Portfolio Value ({$dates[0]} to {$dates[count($dates) - 1]})";
    imagestring($image, 5, 10, 10, $label, $textColor);
    foreach ($formats as $format) {
        $filename = "{$chart_base_dir}/portfolio_chart_{$width}.{$format}";
        save_chart_image($image, $filename, $format);
    }
    imagedestroy($image);
}

echo json_encode(['success' => true, 'image_base_path' => "/images/portfolio/{$user_id}/{$timestamp}/portfolio_chart"]);
?>
