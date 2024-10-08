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
</head>
<body>

    <?php 
	include "includes/header.php";
    if (isset($_SESSION['user_id'])): 
	?>
	coming soon.....

    <?php else: ?>
    <!-- Hero Section -->
    <section class="hero">
        <h2>Trade Smarter, Trade Better with ZNCTech</h2>
        <p>Join ZNCTech today and start trading with tools you can trust, designed to help you achieve your financial goals.</p>
        <md-filled-button href="register.php">Get Started</md-filled-button>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="feature-item">
            <img src="images/shield.webp" alt="Secure Trading">
            <h3>Secure Trading</h3>
            <p>Your investments are safe with us. We provide advanced encryption and security to protect your trades and personal information.</p>
        </div>
        <div class="feature-item">
            <img src="images/stock.webp" alt="Real-Time Data">
            <h3>Real-Time Market Data</h3>
            <p>Access up-to-date market information and make informed decisions with real-time stock data at your fingertips.</p>
        </div>
        <div class="feature-item">
            <img src="images/support.webp" alt="Dedicated Support">
            <h3>Dedicated Support</h3>
            <p>Our dedicated team is here to assist you during trading hours, providing expert support when you need it most.</p>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <h2>Ready to Start Trading?</h2>
        <md-filled-button href="register.php">Sign Up Now</md-filled-button>
    </section>
	<?php endif; ?>
    <?php include "includes/footer.php"; ?>

</body>
</html>
