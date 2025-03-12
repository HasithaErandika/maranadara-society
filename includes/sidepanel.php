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
    <nav class="mt-4">
        <!-- Dashboard -->
        <a href="dashboard.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" title="Dashboard">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>

        <!-- Member Management -->
        <div class="sidebar-group">
            <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('membersMenu')" title="Member Management">
                <i class="fas fa-users"></i><span>Member Management</span><i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="membersMenu" class="submenu">
                <a href="add_member.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'add_member.php' ? 'active' : ''; ?>" title="Add Member">
                    <i class="fas fa-user-plus"></i><span>Add Member</span>
                </a>
                <a href="members.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'members.php' ? 'active' : ''; ?>" title="Manage Members">
                    <i class="fas fa-users"></i><span>Manage Members</span>
                </a>
            </div>
        </div>

        <!-- Financial Management -->
        <div class="sidebar-group">
            <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('financeMenu')" title="Financial Management">
                <i class="fas fa-wallet"></i><span>Financial Management</span><i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="financeMenu" class="submenu">
                <a href="payments.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>" title="Manage Payments">
                    <i class="fas fa-money-bill"></i><span>Manage Payments</span>
                </a>
                <a href="loans.php?action=add" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'loans.php' && isset($_GET['action']) && $_GET['action'] === 'add' ? 'active' : ''; ?>" title="Add Loan">
                    <i class="fas fa-hand-holding-usd"></i><span>Add Loan</span>
                </a>
                <a href="loans.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'loans.php' && !isset($_GET['action']) ? 'active' : ''; ?>" title="Manage Loans">
                    <i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span>
                </a>
            </div>
        </div>

        <!-- Incident Management -->
        <div class="sidebar-group">
            <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('incidentsMenu')" title="Incident Management">
                <i class="fas fa-exclamation-triangle"></i><span>Incident Management</span><i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="incidentsMenu" class="submenu">
                <a href="incidents.php?action=add" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'incidents.php' && isset($_GET['action']) && $_GET['action'] === 'add' ? 'active' : ''; ?>" title="Record Incident">
                    <i class="fas fa-file-alt"></i><span>Record Incident</span>
                </a>
                <a href="incidents.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'incidents.php' && !isset($_GET['action']) ? 'active' : ''; ?>" title="Manage Incidents">
                    <i class="fas fa-file-alt"></i><span>Manage Incidents</span>
                </a>
            </div>
        </div>
    </nav>
</aside>

<!-- JavaScript for Toggle Function -->
<script>
    function toggleMenu(menuId) {
        let menu = document.getElementById(menuId);
        menu.classList.toggle("active");
    }
</script>

<style>
    :root {
        --primary-orange: #F97316;
        --orange-dark: #C2410C;
        --gray-bg: #F9FAFB;
        --card-bg: #FFFFFF;
        --text-primary: #111827;
        --text-secondary: #6B7280;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --sidebar-width: 240px;
    }

    /* Sidebar Always Expanded */
    .sidebar {
        width: var(--sidebar-width);
        background: var(--card-bg);
        border-radius: 8px;
        box-shadow: var(--shadow);
        position: fixed;
        top: 80px;
        left: 16px;
        height: calc(100vh - 96px);
        overflow-y: auto;
        padding: 8px 0;
    }

    .sidebar-item {
        padding: 12px 16px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        font-size: 16px;
        font-weight: 500;
    }

    .sidebar-item:hover, .sidebar-item.active {
        background: var(--primary-orange);
        color: white;
        border-radius: 8px;
    }

    .sidebar-item i {
        width: 24px;
        text-align: center;
    }

    /* Sidebar Groups */
    .sidebar-group {
        margin-top: 8px;
    }

    .sidebar-toggle {
        padding: 12px 16px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 16px;
        font-weight: 600;
    }

    .sidebar-toggle:hover {
        background: var(--primary-orange);
        color: white;
        border-radius: 8px;
    }

    .submenu {
        display: none;
        padding-left: 16px;
    }

    .submenu.active {
        display: block;
    }

    .submenu-icon {
        margin-left: auto;
        transition: transform 0.2s ease;
    }

    .submenu.active + .submenu-icon {
        transform: rotate(180deg);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            left: 0;
            height: auto;
            position: relative;
        }
    }
</style>
