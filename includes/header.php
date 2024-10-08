<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <h1><a href="/index.php">ZNCTech</a></h1>
    <div class="nav-buttons">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="/account.php">Account Details</a>
            <a href="/logout.php">Logout</a>
        <?php else: ?>
            <a href="/login.php">Login</a>
            <a href="/register.php">Register</a>
        <?php endif; ?>
    </div>
</header>