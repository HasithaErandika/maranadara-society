<?php
if (!defined('APP_START')) {
    die('No direct script access allowed');
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>

<!-- Include Remix Icon CDN -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="ri-hand-heart-line"></i>
        </div>
        <div class="sidebar-title">Maranadara Society</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-title">Main Menu</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                        <i class="ri-dashboard-line"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <div class="nav-title">Management</div>
            <ul class="nav-list">
                <li class="nav-item <?php echo in_array($currentPage, ['members', 'add_member', 'edit_member']) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="ri-group-line"></i>
                        <span class="nav-text">Member Management</span>
                        <i class="ri-arrow-down-s-line nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="members.php" class="submenu-link <?php echo $currentPage === 'members' ? 'active' : ''; ?>">
                            <i class="ri-user-settings-line"></i>
                            All Members
                        </a>
                        <a href="add_member.php" class="submenu-link <?php echo $currentPage === 'add_member' ? 'active' : ''; ?>">
                            <i class="ri-user-add-line"></i>
                            Add Member
                        </a>
                    </div>
                </li>

                <li class="nav-item <?php echo in_array($currentPage, ['get_loans', 'loans', 'payments']) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="ri-wallet-line"></i>
                        <span class="nav-text">Financial Management</span>
                        <i class="ri-arrow-down-s-line nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="get_loans.php" class="submenu-link <?php echo $currentPage === 'get_loans' ? 'active' : ''; ?>">
                            <i class="ri-line-chart-line"></i>
                            View Loans
                        </a>
                        <a href="loans.php" class="submenu-link <?php echo $currentPage === 'loans' ? 'active' : ''; ?>">
                            <i class="ri-exchange-dollar-line"></i>
                            Loans
                        </a>
                        <a href="payments.php" class="submenu-link <?php echo $currentPage === 'payments' ? 'active' : ''; ?>">
                            <i class="ri-file-chart-line"></i>
                            Payments
                        </a>
                    </div>
                </li>

                <li class="nav-item <?php echo in_array($currentPage, ['incidents', 'add_incident']) ? 'active' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="ri-alert-line"></i>
                        <span class="nav-text">Incident Management</span>
                        <i class="ri-arrow-down-s-line nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="incidents.php" class="submenu-link <?php echo $currentPage === 'incidents' ? 'active' : ''; ?>">
                            <i class="ri-file-list-line"></i>
                            All Incidents
                        </a>
                        <a href="incidents.php" class="submenu-link <?php echo $currentPage === 'incidents' ? 'active' : ''; ?>">
                            <i class="ri-file-add-line"></i>
                            Report Incident
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-version">
            <i class="ri-code-s-slash-line"></i>
            <span>Version 1.0.0</span>
        </div>
    </div>
</div>

<div class="sidebar-overlay"></div>

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
        --header-height: 64px;
    }

    /* Sidebar Styling */
    .sidebar {
        position: fixed;
        left: 0;
        top: var(--header-height);
        bottom: 0;
        width: 270px;
        background: var(--card-bg);
        box-shadow: var(--shadow);
        z-index: 100;
        transition: var(--transition);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) transparent;
        display: flex;
        flex-direction: column;
        height: calc(100vh - var(--header-height));
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background-color: var(--primary-color);
        border-radius: 3px;
    }

    .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--primary-color);
        color: #FFFFFF;
    }

    .sidebar-logo {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .sidebar-title {
        font-size: 18px;
        font-weight: 700;
    }

    .sidebar-nav {
        padding: 24px 16px;
        flex: 1;
        overflow-y: auto;
    }

    .nav-section {
        margin-bottom: 32px;
    }

    .nav-section:last-child {
        margin-bottom: 0;
    }

    .nav-title {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0 12px;
        margin-bottom: 12px;
    }

    .nav-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .nav-item {
        position: relative;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        color: var(--text-primary);
        text-decoration: none;
        border-radius: 12px;
        transition: var(--transition);
    }

    .nav-link:hover {
        background: var(--bg-color);
        color: var(--primary-color);
        transform: translateX(4px);
    }

    .nav-link.active {
        background: var(--primary-color);
        color: #FFFFFF;
    }

    .nav-link i {
        font-size: 20px;
        width: 24px;
        text-align: center;
    }

    .nav-text {
        font-size: 14px;
        font-weight: 500;
    }

    .nav-arrow {
        margin-left: auto;
        font-size: 12px;
        transition: var(--transition);
    }

    .nav-item.active .nav-arrow {
        transform: rotate(180deg);
    }

    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        padding-left: 44px;
    }

    .nav-item.active .submenu {
        max-height: 500px;
    }

    .submenu-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 13px;
        border-radius: 8px;
        transition: var(--transition);
    }

    .submenu-link:hover {
        color: var(--primary-color);
        background: var(--bg-color);
        transform: translateX(4px);
    }

    .submenu-link.active {
        color: var(--primary-color);
        font-weight: 500;
        background: var(--bg-color);
    }

    .submenu-link i {
        font-size: 14px;
        width: 16px;
        text-align: center;
    }

    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        background: var(--bg-color);
        margin-top: auto;
    }

    .sidebar-version {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px;
        border-radius: 12px;
        background: var(--card-bg);
        box-shadow: var(--shadow);
        color: var(--text-secondary);
        font-size: 13px;
    }

    .sidebar-version i {
        font-size: 16px;
        color: var(--primary-color);
    }

    .sidebar-overlay {
        position: fixed;
        top: var(--header-height);
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 99;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }

    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }
    }

    @media (max-width: 640px) {
        .sidebar {
            width: 260px;
        }

        .sidebar-header {
            padding: 20px;
        }

        .sidebar-logo {
            width: 36px;
            height: 36px;
            font-size: 20px;
        }

        .sidebar-title {
            font-size: 16px;
        }

        .nav-link {
            padding: 10px;
        }

        .nav-link i {
            font-size: 18px;
        }

        .nav-text {
            font-size: 13px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const link = item.querySelector('.nav-link');
        const submenu = item.querySelector('.submenu');
        
        if (submenu) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Close other open submenus
                navItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle current submenu
                item.classList.toggle('active');
            });
        }
    });

    // Handle mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebarToggle && sidebar && overlay) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});
</script>