<?php
if (!defined('APP_START')) {
    die('No direct script access allowed');
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>
<aside class="sidebar">
    <nav class="nav-container">
        <!-- Dashboard -->
        <a href="dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" title="Dashboard">
            <i class="ri-dashboard-line"></i><span>Dashboard</span>
        </a>

        <!-- Member Management -->
        <div class="sidebar-group">
            <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('membersMenu')" title="Member Management">
                <i class="ri-group-line"></i><span>Member Management</span>
                <i class="ri-arrow-down-s-line submenu-icon"></i>
            </a>
            <div id="membersMenu" class="submenu">
                <a href="add_member.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'add_member.php' ? 'active' : ''; ?>" title="Add Member">
                    <i class="ri-user-add-line"></i><span>Add Member</span>
                </a>
                <a href="members.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'members.php' ? 'active' : ''; ?>" title="Manage Members">
                    <i class="ri-user-settings-line"></i><span>Manage Members</span>
                </a>
            </div>
        </div>

        <!-- Financial Management -->
        <div class="sidebar-group">
            <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('financeMenu')" title="Financial Management">
                <i class="ri-wallet-line"></i><span>Financial Management</span>
                <i class="ri-arrow-down-s-line submenu-icon"></i>
            </a>
            <div id="financeMenu" class="submenu">
                <a href="payments.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>" title="Manage Payments">
                    <i class="ri-money-dollar-circle-line"></i><span>Manage Payments</span>
                </a>
                <a href="loans.php?action=add" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'loans.php' && isset($_GET['action']) && $_GET['action'] === 'add' ? 'active' : ''; ?>" title="Add Loan">
                    <i class="ri-hand-coin-line"></i><span>Add Loan</span>
                </a>
                <a href="loans.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'loans.php' && !isset($_GET['action']) ? 'active' : ''; ?>" title="Manage Loans">
                    <i class="ri-bank-line"></i><span>Manage Loans</span>
                </a>
            </div>
        </div>

        <!-- Incident Management -->
        <div class="sidebar-group">
            <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('incidentsMenu')" title="Incident Management">
                <i class="ri-alert-line"></i><span>Incident Management</span>
                <i class="ri-arrow-down-s-line submenu-icon"></i>
            </a>
            <div id="incidentsMenu" class="submenu">
                <a href="incidents.php?action=add" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'incidents.php' && isset($_GET['action']) && $_GET['action'] === 'add' ? 'active' : ''; ?>" title="Record Incident">
                    <i class="ri-file-add-line"></i><span>Record Incident</span>
                </a>
                <a href="incidents.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'incidents.php' && !isset($_GET['action']) ? 'active' : ''; ?>" title="Manage Incidents">
                    <i class="ri-file-list-line"></i><span>Manage Incidents</span>
                </a>
            </div>
        </div>
    </nav>
</aside>

<!-- JavaScript for Toggle Function -->
<script>
    function toggleMenu(menuId) {
        const menu = document.getElementById(menuId);
        const icon = menu.previousElementSibling.querySelector('.submenu-icon');
        menu.classList.toggle('active');
        icon.classList.toggle('rotate');
    }
</script>

<style>
    :root {
        --primary-color: #F97316;
        --primary-hover: #C2410C;
        --bg-color: #F9FAFB;
        --card-bg: #FFFFFF;
        --text-primary: #1F2A44;
        --text-secondary: #6B7280;
        --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        --sidebar-width: 260px;
        --transition: all 0.3s ease;
    }

    /* Sidebar Styling */
    .sidebar {
        width: var(--sidebar-width);
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: var(--shadow);
        position: fixed;
        top: 80px;
        left: 16px;
        height: calc(100vh - 100px);
        overflow-y: auto;
        padding: 16px 0;
        font-family: 'Inter', sans-serif;
        transition: var(--transition);
    }

    .nav-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .sidebar-item {
        padding: 12px 20px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        font-size: 15px;
        font-weight: 500;
        border-radius: 8px;
        margin: 0 12px;
        transition: var(--transition);
    }

    .sidebar-item:hover,
    .sidebar-item.active {
        background: var(--primary-color);
        color: #FFFFFF;
        transform: translateX(4px);
    }

    .sidebar-item i {
        font-size: 20px;
        width: 24px;
        text-align: center;
    }

    /* Sidebar Groups */
    .sidebar-group {
        margin: 8px 0;
    }

    .sidebar-toggle {
        padding: 12px 20px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        border-radius: 8px;
        margin: 0 12px;
        transition: var(--transition);
    }

    .sidebar-toggle:hover {
        background: var(--primary-color);
        color: #FFFFFF;
    }

    .submenu {
        display: none;
        padding-left: 24px;
        margin: 8px 0;
    }

    .submenu.active {
        display: block;
        animation: slideDown 0.3s ease;
    }

    .submenu-icon {
        margin-left: auto;
        font-size: 18px;
        transition: transform 0.3s ease;
    }

    .submenu-icon.rotate {
        transform: rotate(180deg);
    }

    /* Animation for Submenu */
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

    /* Responsive Design */
    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            left: 0;
            height: auto;
            position: relative;
            top: 0;
            border-radius: 0;
            box-shadow: none;
            padding: 12px 0;
        }

        .sidebar-item,
        .sidebar-toggle {
            margin: 0 8px;
        }
    }
</style>

<!-- Include Remix Icon CDN -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">