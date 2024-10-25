<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: /404.php');
    exit();
}
require '../includes/db_connect.php';


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/metainfo.php"; ?>
    <title>ZNCTech - Admin</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin-style.css">
</head>
<body>
    <?php include "../includes/header.php"; ?>

    <div class="container">
        <h2>Admin Dashboard</h2>
        <div class="tabs">
            <button class="tab-button active" onclick="openTab(event, 'stocks')">Stocks</button>
			<button class="tab-button" onclick="openTab(event, 'market-schedule')">Market Schedule</button>
			<button class="tab-button" onclick="openTab(event, 'settings')">Settings</button>
        </div>

        <div id="stocks" class="tab-content">
            <?php include "stocks_section.php"; ?>
        </div>

        <div id="market-schedule" class="tab-content" style="display:none;">
            <?php include "market_schedule_section.php"; ?>
        </div>

        <div id="settings" class="tab-content" style="display:none;">
            <h3>Settings</h3>
            <p>Additional settings and configurations will be displayed here.</p>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = "none";
            }
            var tabButtons = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].className = tabButtons[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            if (evt) {
                evt.currentTarget.className += " active";
            } else {
                var button = document.querySelector(`button[onclick="openTab(event, '${tabName}')"]`);
                if (button) button.className += " active";
            }
        }
        function checkURLAndOpenTab() {
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section');
            if (section && document.getElementById(section)) {
                openTab(null, section);
            } else {
                openTab(null, 'stocks');
            }
        }
        document.addEventListener("DOMContentLoaded", checkURLAndOpenTab);
    </script>
    <?php include "../includes/footer.php"; ?>
</body>
</html>
