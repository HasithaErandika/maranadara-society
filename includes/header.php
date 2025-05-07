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
                <div class="header-user-info">
                    <div class="user-avatar">
                        <i class="ri-user-line"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $_SESSION['user_name'] ?? 'Admin User'; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <a href="../../login.php?logout=1" class="header-btn logout-btn" aria-label="Logout">
                    <i class="ri-logout-box-r-line"></i>
                    <span>Logout</span>
                </a>
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
        --primary-color: #e67e22;
        --primary-hover: #d35400;
        --bg-color: #f5f6f5;
        --card-bg: #FFFFFF;
        --text-primary: #333;
        --text-secondary: #7f8c8d;
        --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    .header-nav {
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1000;
        background: var(--card-bg);
        box-shadow: var(--shadow);
        padding: 12px 0;
        font-family: 'Inter', sans-serif;
        backdrop-filter: blur(10px);
        background-color: rgba(255, 255, 255, 0.95);
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .header-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 24px;
        font-weight: 700;
        color: var(--primary-color);
        text-decoration: none;
        transition: var(--transition);
    }

    .header-logo:hover {
        color: var(--primary-hover);
        transform: translateY(-1px);
    }

    .header-logo i {
        font-size: 28px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .header-user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px;
        background: var(--bg-color);
        border-radius: 20px;
        transition: var(--transition);
    }

    .header-user-info:hover {
        background: var(--primary-color);
        color: #FFFFFF;
    }

    .header-user-info:hover .user-name,
    .header-user-info:hover .user-role {
        color: #FFFFFF;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #FFFFFF;
        font-size: 18px;
    }

    .user-details {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .user-role {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .header-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: var(--primary-color);
        color: #FFFFFF;
        font-size: 14px;
        font-weight: 600;
        border-radius: 20px;
        text-decoration: none;
        transition: var(--transition);
    }

    .header-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(230, 126, 34, 0.2);
    }

    .header-btn i {
        font-size: 18px;
    }

    .logout-btn {
        background: #e74c3c;
    }

    .logout-btn:hover {
        background: #c0392b;
        box-shadow: 0 4px 8px rgba(231, 76, 60, 0.2);
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
        border-radius: 12px;
        min-width: 200px;
        display: none;
        animation: slideDown 0.3s ease;
        padding: 8px;
    }

    .dropdown-menu.active {
        display: block;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        color: var(--text-primary);
        font-size: 14px;
        text-decoration: none;
        transition: var(--transition);
        border-radius: 8px;
    }

    .dropdown-item:hover {
        background: var(--primary-color);
        color: #FFFFFF;
    }

    .dropdown-item i {
        font-size: 18px;
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

    @media (max-width: 768px) {
        .header-nav {
            padding: 10px 0;
        }

        .header-logo {
            font-size: 20px;
        }

        .header-logo i {
            font-size: 24px;
        }

        .header-user-info {
            padding: 6px 12px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            font-size: 16px;
        }

        .user-name {
            font-size: 13px;
        }

        .user-role {
            font-size: 11px;
        }

        .header-btn {
            padding: 6px 12px;
            font-size: 13px;
        }

        .header-btn i {
            font-size: 16px;
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