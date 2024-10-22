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
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Admin</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php 
	include "includes/header.php";

	include "includes/footer.php";
	?>

</body>
</html>
