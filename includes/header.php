<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <div class="header-left">
        <h1><a href="/index.php">ZNCTech</a></h1>
    </div>
    <div class="search-bar">
        <input type="text" id="stock-search" placeholder="Search stocks..." autocomplete="off">
        <div class="search-results" id="search-results"></div>
    </div>
    <div class="header-right">
        <div class="user-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-icon" onclick="toggleUserMenu()">
					<picture>
						<source srcset="/images/profile-picture-1500.avif 1500w,
										/images/profile-picture-1024.avif 1024w,
										/images/profile-picture-512.avif 512w,
										/images/profile-picture-256.avif 256w,
										/images/profile-picture-128.avif 128w"
								type="image/avif">
						<source srcset="/images/profile-picture-1500.webp 1500w,
										/images/profile-picture-1024.webp 1024w,
										/images/profile-picture-512.webp 512w,
										/images/profile-picture-256.webp 256w,
										/images/profile-picture-128.webp 128w"
								type="image/webp">
						<source srcset="/images/profile-picture-1500.png 1500w,
										/images/profile-picture-1024.png 1024w,
										/images/profile-picture-512.png 512w,
										/images/profile-picture-256.png 256w,
										/images/profile-picture-128.png 128w"
								type="image/png">
						<img src="/images/profile-picture-256.png" 
							 loading="lazy" 
							 fetchpriority="low" 
							 alt="User Avatar" 
							 width="256" 
							 height="256">
					</picture>
				</div>
                <div class="user-dropdown" id="user-dropdown">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                    <a href="/account.php" class="dropdown-link">Account Details</a>
                    <a href="/wallet" class="dropdown-link">Wallet</a>
                    <a href="/transactions" class="dropdown-link">Transactions</a>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                        <a href="/ASB/index.php" class="dropdown-link">Admin Panel</a>
                    <?php endif; ?>
                    <a href="/logout.php" class="dropdown-link">Logout</a>
                </div>
            <?php else: ?>
                <a href="/login.php" class="nav-link">Login</a>
                <a href="/register.php" class="nav-link">Register</a>
            <?php endif; ?>
        </div>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('stock-search');
    const searchResults = document.getElementById('search-results');

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        if (query.length > 0) {
            fetch(`/BackendAutomation/searchStock.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.length > 0) {
                        searchResults.style.display = 'block';
                        data.forEach(stock => {
                            const div = document.createElement('div');
                            div.className = 'search-result-item';
							
							let percentageChange = parseFloat(stock.percentage_change);
                            let percentageText = isNaN(percentageChange) ? '---' : `${percentageChange.toFixed(2)}%`;
                            let changeClass = percentageChange >= 0 ? 'positive' : 'negative';
							
                            div.innerHTML = `
                                <div class="stock-info">
                                    <span class="stock-symbol">${stock.ticker_symbol}</span>
                                    <span class="stock-name">${stock.company_name}</span>
                                </div>
                                <div class="stock-stats">
                                    <span class="stock-price">$${parseFloat(stock.current_price).toFixed(2)}</span>
                                    <span class="stock-change ${isNaN(percentageChange) ? '' : changeClass}">
                                        ${percentageText}
                                    </span>
                                </div>
                            `;
                            div.onclick = () => window.location.href = `/${stock.ticker_symbol}`;
                            searchResults.appendChild(div);
                        });
                    } else {
                        searchResults.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        } else {
            searchResults.style.display = 'none';
        }
    });

    document.addEventListener('click', function (event) {
        if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
            searchResults.style.display = 'none';
        }
        if (!document.querySelector('.user-icon').contains(event.target) && !document.getElementById('user-dropdown').contains(event.target)) {
            document.getElementById('user-dropdown').classList.remove('show');
        }
    });
});

function toggleMenu() {
    const menu = document.querySelector('.nav-menu');
    const hamburger = document.querySelector('.hamburger');
    menu.classList.toggle('show');
    hamburger.classList.toggle('active');
}

function toggleUserMenu() {
    document.getElementById('user-dropdown').classList.toggle('show');
}
</script>
