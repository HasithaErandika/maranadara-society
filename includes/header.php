<?php
if (!defined('APP_START')) {
    die('No direct script access allowed');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="fixed w-full z-20 top-0 bg-white shadow-md">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-[var(--primary-orange)] flex items-center" aria-label="Home">
            <i class="ri-hand-heart-line mr-2 text-2xl"></i> Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <?php if (isset($_SESSION['role'])): ?>
                <span class="text-[var(--text-primary)] hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                <a href="../../login.php?logout=1" class="nav-btn px-4 py-2 rounded-lg text-white" aria-label="Logout">Logout</a>
            <?php else: ?>
                <div class="relative group">
                    <button class="nav-btn px-4 py-2 rounded-lg flex items-center text-white" id="login-toggle">
                        <i class="ri-login-box-line mr-2"></i> Login
                    </button>
                    <div class="dropdown-menu absolute top-full right-0 mt-2 hidden group-hover:block bg-white shadow-md rounded-lg">
                        <a href="../../login.php" class="dropdown-item block px-4 py-2 text-[var(--text-primary)] hover:bg-[var(--orange-light)]">Member Login</a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <button id="sidebar-toggle" class="text-[var(--primary-orange)]" aria-label="Toggle Sidebar">
                    <i class="ri-menu-line text-2xl"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
    :root {
        --primary-orange: #F97316;
        --orange-dark: #C2410C;
        --orange-light: #FED7AA;
        --gray-bg: #F9FAFB;
        --card-bg: #FFFFFF;
        --text-primary: #111827;
        --text-secondary: #6B7280;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .nav-btn {
        background: var(--primary-orange);
        transition: background 0.3s ease;
    }

    .nav-btn:hover {
        background: var(--orange-dark);
    }

    .dropdown-menu {
        min-width: 160px;
    }

    .dropdown-item {
        transition: background 0.3s ease;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const loginToggle = document.getElementById('login-toggle');
        const loginMenu = document.querySelector('.dropdown-menu');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');

        if (loginToggle && loginMenu) {
            loginToggle.addEventListener('click', (e) => {
                if (window.innerWidth < 768) {
                    e.preventDefault();
                    loginMenu.classList.toggle('hidden');
                }
            });
        }

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('hidden');
            });
        }
    });
</script>