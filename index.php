<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Trade Smarter, Trade Better</title>
    <link rel="stylesheet" href="css/style.css">
	<?php
    if (!isset($_SESSION['user_id'])){
	?>
    <link rel="stylesheet" href="css/homepage.css">
	<?php
	} 
	?>
</head>
<body>

    <?php 
	include "includes/header.php";
    if (isset($_SESSION['user_id'])): 
	include "includes/loggedin.php";
	else:
	include "includes/loggedout.php";
	endif;
	include "includes/footer.php";
	?>

</body>
</html>
