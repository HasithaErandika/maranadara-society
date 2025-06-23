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
                    <div class="user-avatar"><i class="ri-user-line"></i></div>
                    <div class="user-details">
                        <div class="user-name"><?= $_SESSION['user_name'] ?? 'Admin User'; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <a href="../../login.php?logout=1" class="header-btn logout-btn" aria-label="Logout">
                    <i class="ri-logout-box-r-line"></i> <span>Logout</span>
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
    --accent-color: #f97316;
    --accent-hover: #c2410c;
    --danger-color: #e74c3c;
    --danger-hover: #c0392b;
    --bg-color: #fff;
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s ease;
}

.header-nav {
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(8px);
    box-shadow: var(--shadow);
    font-family: 'Inter', sans-serif;
}

.header-container {
    max-width: 1200px;
    margin: auto;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-logo {
    display: flex;
    align-items: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-color);
    gap: 10px;
    text-decoration: none;
    transition: var(--transition);
}

.header-logo:hover {
    color: var(--accent-hover);
    transform: translateY(-1px);
}

.header-logo i {
    font-size: 1.75rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.header-user-info {
    display: flex;
    align-items: center;
    background: #f3f4f6;
    padding: 6px 14px;
    border-radius: 24px;
    gap: 10px;
    transition: var(--transition);
}

.header-user-info:hover {
    background: var(--accent-color);
    color: #fff;
}

.header-user-info:hover .user-name,
.header-user-info:hover .user-role {
    color: #fff;
}

.user-avatar {
    width: 36px;
    height: 36px;
    background: var(--accent-color);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.user-details {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.user-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

.user-role {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.header-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--accent-color);
    color: #fff;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
}

.header-btn:hover {
    background: var(--accent-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(249, 115, 22, 0.15);
}

.logout-btn {
    background: var(--danger-color);
}

.logout-btn:hover {
    background: var(--danger-hover);
    box-shadow: 0 6px 18px rgba(231, 76, 60, 0.15);
}

.header-dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 110%;
    right: 0;
    background: var(--bg-color);
    box-shadow: var(--shadow);
    border-radius: 12px;
    min-width: 180px;
    display: none;
    padding: 10px;
    animation: fadeIn 0.3s ease forwards;
}

.dropdown-menu.active {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    font-size: 0.9rem;
    color: var(--text-primary);
    border-radius: 8px;
    text-decoration: none;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: var(--accent-color);
    color: #fff;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px 16px;
    }

    .header-actions {
        width: 100%;
        justify-content: space-between;
        margin-top: 10px;
    }

    .header-logo {
        font-size: 1.25rem;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        font-size: 1rem;
    }

    .header-btn {
        font-size: 0.85rem;
        padding: 6px 12px;
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