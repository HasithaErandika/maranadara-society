<?php
if (!defined('APP_START')) {
    die('No direct script access allowed');
}
?>
<nav class="fixed w-full z-20 top-0">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-[var(--primary-orange)] flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i> Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <?php if (isset($_SESSION['user'])): ?>
                <span class="text-[var(--text-primary)] hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
                <a href="../login.php?logout=1" class="nav-btn px-4 py-2 rounded-lg" aria-label="Logout">Logout</a>
            <?php else: ?>
                <div class="relative group">
                    <button class="nav-btn px-4 py-2 rounded-lg flex items-center" id="login-toggle">
                        <i class="fas fa-user mr-2"></i> Login
                    </button>
                    <div class="dropdown-menu absolute top-full right-0 mt-2 hidden group-hover:block md:group-hover:block">
                        <a href="../login.php?role=user" class="dropdown-item block px-4 py-2 text-[var(--text-primary)]">Member Login</a>
                        <a href="../login.php?role=admin" class="dropdown-item block px-4 py-2 text-[var(--text-primary)]">Admin Login</a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['user']) && $_SESSION['role'] === 'admin'): ?>
                <button id="sidebar-toggle" class="text-[var(--primary-orange)]" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars text-2xl"></i>
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

    nav {
        background: var(--card-bg);
        box-shadow: var(--shadow);
    }

    .nav-btn {
        background: var(--primary-orange);
        color: white;
        transition: all 0.3s ease;
    }

    .nav-btn:hover {
        background: var(--orange-dark);
    }

    .dropdown-menu {
        background: var(--card-bg);
        box-shadow: var(--shadow);
        border-radius: 0.75rem;
        transition: all 0.3s ease;
    }

    .dropdown-item {
        transition: all 0.3s ease;
    }

    .dropdown-item:hover {
        background: var(--orange-light);
        color: var(--text-primary);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const loginToggle = document.getElementById('login-toggle');
        const loginMenu = document.querySelector('.dropdown-menu');
        if (loginToggle && loginMenu) {
            loginToggle.addEventListener('click', (e) => {
                if (window.innerWidth < 768) {
                    e.preventDefault();
                    loginMenu.classList.toggle('hidden');
                }
            });
        }
    });
</script>