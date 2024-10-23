<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <h1><a href="/index.php">ZNCTech</a></h1>
    <div class="hamburger" onclick="toggleMenu()">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="nav-menu">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
            <a href="/account.php" class="nav-link">Account Details</a>
			<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                <a href="/admin/admin.php" class="nav-link">Admin Panel</a>
            <?php endif; ?>
            <a href="/logout.php" class="nav-link">Logout</a>
        <?php else: ?>
            <a href="/login.php" class="nav-link">Login</a>
            <a href="/register.php" class="nav-link">Register</a>
        <?php endif; ?>
    </div>
</header>
