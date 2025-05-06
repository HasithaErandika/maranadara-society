<?php
if (!defined('APP_START')) {
    die('No direct script access allowed');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="header-nav">
    <div class="header-container">
        <a href="../../index.php" class="header-logo" aria-label="Home">
            <i class="ri-hand-heart-line"></i> Maranadhara Samithi
        </a>
        <div class="header-actions">
            <?php if (isset($_SESSION['role'])): ?>
                <span class="header-user">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                <a href="../../login.php?logout=1" class="header-btn" aria-label="Logout">Logout</a>
            <?php else: ?>
                <div class="header-dropdown">
                    <button class="header-btn" id="login-toggle" aria-expanded="false">
                        <i class="ri-login-box-line"></i> Login
                    </button>
                    <div class="dropdown-menu" id="login-menu">
                        <a href="../../login.php" class="dropdown-item">Member Login</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
    :root {
        --primary-color: #F97316;
        --primary-hover: #C2410C;
        --bg-color: #F9FAFB;
        --card-bg: #FFFFFF;
        --text-primary: #1F2A44;
        --text-secondary: #6B7280;
        --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s ease;
    }

    .header-nav {
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1000;
        background: var(--card-bg);
        box-shadow: var(--shadow);
        padding: 16px 0;
        font-family: 'Inter', sans-serif;
    }

    .header-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .header-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 28px;
        font-weight: 800;
        color: var(--primary-color);
        text-decoration: none;
        transition: var(--transition);
    }

    .header-logo:hover {
        color: var(--primary-hover);
    }

    .header-logo i {
        font-size: 36px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .header-user {
        color: var(--text-primary);
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .header-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--primary-color);
        color: #FFFFFF;
        font-size: 15px;
        font-weight: 500;
        border-radius: 8px;
        text-decoration: none;
        transition: var(--transition);
    }

    .header-btn:hover {
        background: var(--primary-hover);
    }

    .header-btn i {
        font-size: 20px;
    }

    .header-dropdown {
        position: relative;
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        background: var(--card-bg);
        box-shadow: var(--shadow);
        border-radius: 8px;
        min-width: 180px;
        display: none;
        animation: slideDown 0.3s ease;
    }

    .dropdown-menu.active {
        display: block;
    }

    .dropdown-item {
        display: block;
        padding: 12px 16px;
        color: var(--text-primary);
        font-size: 14px;
        text-decoration: none;
        transition: var(--transition);
    }

    .dropdown-item:hover {
        background: var(--primary-color);
        color: #FFFFFF;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (min-width: 768px) {
        .dropdown-menu {
            display: none;
        }

        .header-dropdown:hover .dropdown-menu {
            display: block;
        }
    }

    @media (max-width: 768px) {
        .header-nav {
            padding: 12px 0;
        }

        .header-logo {
            font-size: 24px;
        }

        .header-logo i {
            font-size: 30px;
        }

        .header-user {
            font-size: 14px;
        }

        .header-btn {
            padding: 8px 16px;
            font-size: 14px;
        }

        .header-btn i {
            font-size: 18px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var loginToggle = document.getElementById('login-toggle');
        var loginMenu = document.getElementById('login-menu');

        if (loginToggle && loginMenu) {
            loginToggle.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    loginMenu.classList.toggle('active');
                    var isExpanded = loginMenu.classList.contains('active');
                    loginToggle.setAttribute('aria-expanded', isExpanded);
                }
            });

            document.addEventListener('click', function(e) {
                if (!loginToggle.contains(e.target) && !loginMenu.contains(e.target)) {
                    loginMenu.classList.remove('active');
                    loginToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
</script>