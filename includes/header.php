<?php
if (!defined('APP_START')) {
    die('No direct script access allowed');
}
?>
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i> Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <?php if (isset($_SESSION['user'])): ?>
                <span class="text-gray-700 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
                <a href="../login.php?logout=1" class="text-white bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg transition-all" aria-label="Logout">Logout</a>
            <?php else: ?>
                <div class="dropdown relative">
                    <button class="text-white bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg transition-all flex items-center" id="login-toggle">
                        <i class="fas fa-user mr-2"></i> Login
                    </button>
                    <div class="dropdown-menu absolute top-full right-0 mt-2 hidden bg-white shadow-lg rounded-lg" id="login-menu">
                        <a href="../login.php?role=user" class="block px-4 py-2 text-gray-700 hover:bg-orange-100 hover:text-orange-600">Member Login</a>
                        <a href="../login.php?role=admin" class="block px-4 py-2 text-gray-700 hover:bg-orange-100 hover:text-orange-600">Admin Login</a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['user'])): ?>
                <button id="sidebar-toggle" class="md:hidden text-orange-600" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                <button id="pin-sidebar" class="text-white bg-orange-600 hover:bg-orange-700 px-2 py-1 rounded-lg hidden md:inline" aria-label="Pin Sidebar">
                    <i class="fas fa-thumbtack"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</nav>
<style>
    .dropdown .dropdown-menu {
        display: none;
        min-width: 160px;
    }
    .dropdown:hover .dropdown-menu {
        display: block;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Login dropdown toggle for mobile
        const loginToggle = document.getElementById('login-toggle');
        const loginMenu = document.getElementById('login-menu');
        if (loginToggle && loginMenu) {
            loginToggle.addEventListener('click', (e) => {
                if (window.innerWidth < 768) { // Mobile only
                    e.preventDefault();
                    loginMenu.classList.toggle('hidden');
                }
            });
        }
    });
</script>