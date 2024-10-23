<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Page Not Found</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/404.css">
</head>
<body>

    <?php include "includes/header.php"; ?>

    <div class="container not-found-page">
        <h2 class="center-align">Oops! Page Not Found</h2>
        <p class="center-align">Sorry, but the page you were looking for doesn't exist.</p>
        <p class="center-align">You can go back to the <a href="index.php">homepage</a> or use the navigation menu to find what you're looking for.</p>
    </div>

    <?php include "includes/footer.php"; ?>

</body>
</html>
