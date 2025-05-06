<?php
define('APP_START', true);
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get quick stats (you'll need to implement these functions)
$totalMembers = 150; // Example: getTotalMembers();
$activeLoans = 25; // Example: getActiveLoans();
$pendingPayments = 45; // Example: getPendingPayments();
$recentIncidents = 5; // Example: getRecentIncidents();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Maranadhara Samithi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
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
            --sidebar-width: 260px;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Styles */
        .header-nav {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--card-bg);
            box-shadow: var(--shadow);
            padding: 16px 0;
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

        /* Sidebar Styles */
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

        /* Main Content Styles */
        .main-content {
            margin-left: calc(var(--sidebar-width) + 32px);
            margin-top: 80px;
            padding: 24px;
            flex: 1;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(249, 115, 22, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .stat-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .recent-activity {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--bg-color);
            border-radius: 8px;
            transition: var(--transition);
        }

        .activity-item:hover {
            transform: translateX(4px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(249, 115, 22, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-icon i {
            font-size: 20px;
            color: var(--primary-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Footer Styles */
        .footer {
            background: var(--card-bg);
            padding: 32px 0;
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .footer-text {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-link {
            color: var(--primary-color);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Animations */
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

            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-value {
                font-size: 28px;
            }

            .footer-content {
                flex-direction: row;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="header-nav">
        <div class="header-container">
            <a href="../index.php" class="header-logo">
                <i class="ri-hand-heart-line"></i>
                Maranadhara Samithi
            </a>
            <div class="header-actions">
                <span class="header-user">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="../login.php?logout=1" class="header-btn">
                    <i class="ri-logout-box-line"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar">
        <nav class="nav-container">
            <!-- Dashboard -->
            <a href="dashboard.php" class="sidebar-item active">
                <i class="ri-dashboard-line"></i>
                <span>Dashboard</span>
            </a>

            <!-- Member Management -->
            <div class="sidebar-group">
                <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('membersMenu')">
                    <i class="ri-group-line"></i>
                    <span>Member Management</span>
                    <i class="ri-arrow-down-s-line submenu-icon"></i>
                </a>
                <div id="membersMenu" class="submenu">
                    <a href="add_member.php" class="sidebar-item">
                        <i class="ri-user-add-line"></i>
                        <span>Add Member</span>
                    </a>
                    <a href="members.php" class="sidebar-item">
                        <i class="ri-user-settings-line"></i>
                        <span>Manage Members</span>
                    </a>
                </div>
            </div>

            <!-- Financial Management -->
            <div class="sidebar-group">
                <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('financeMenu')">
                    <i class="ri-wallet-line"></i>
                    <span>Financial Management</span>
                    <i class="ri-arrow-down-s-line submenu-icon"></i>
                </a>
                <div id="financeMenu" class="submenu">
                    <a href="payments.php" class="sidebar-item">
                        <i class="ri-money-dollar-circle-line"></i>
                        <span>Manage Payments</span>
                    </a>
                    <a href="loans.php?action=add" class="sidebar-item">
                        <i class="ri-hand-coin-line"></i>
                        <span>Add Loan</span>
                    </a>
                    <a href="loans.php" class="sidebar-item">
                        <i class="ri-bank-line"></i>
                        <span>Manage Loans</span>
                    </a>
                </div>
            </div>

            <!-- Incident Management -->
            <div class="sidebar-group">
                <a href="javascript:void(0);" class="sidebar-toggle" onclick="toggleMenu('incidentsMenu')">
                    <i class="ri-alert-line"></i>
                    <span>Incident Management</span>
                    <i class="ri-arrow-down-s-line submenu-icon"></i>
                </a>
                <div id="incidentsMenu" class="submenu">
                    <a href="incidents.php?action=add" class="sidebar-item">
                        <i class="ri-file-add-line"></i>
                        <span>Record Incident</span>
                    </a>
                    <a href="incidents.php" class="sidebar-item">
                        <i class="ri-file-list-line"></i>
                        <span>Manage Incidents</span>
                    </a>
                </div>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-grid">
            <!-- Total Members Card -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-group-line"></i>
                </div>
                <div class="stat-value"><?php echo $totalMembers; ?></div>
                <div class="stat-label">Total Members</div>
            </div>

            <!-- Active Loans Card -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-bank-line"></i>
                </div>
                <div class="stat-value"><?php echo $activeLoans; ?></div>
                <div class="stat-label">Active Loans</div>
            </div>

            <!-- Pending Payments Card -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-value"><?php echo $pendingPayments; ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>

            <!-- Recent Incidents Card -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-alert-line"></i>
                </div>
                <div class="stat-value"><?php echo $recentIncidents; ?></div>
                <div class="stat-label">Recent Incidents</div>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="recent-activity">
            <h2 class="section-title">
                <i class="ri-time-line"></i>
                Recent Activity
            </h2>
            <div class="activity-list">
                <!-- Example Activity Items -->
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="ri-user-add-line"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">New Member Registration</div>
                        <div class="activity-time">2 hours ago</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Payment Received</div>
                        <div class="activity-time">4 hours ago</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="ri-alert-line"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">New Incident Reported</div>
                        <div class="activity-time">6 hours ago</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-content">
                <p class="footer-text">© <?php echo date('Y'); ?> Maranadhara Samithi. All rights reserved.</p>
                <div class="footer-links">
                    <a href="../index.php" class="footer-link">Home</a>
                    <a href="#" class="footer-link">Privacy Policy</a>
                    <a href="#" class="footer-link">Contact Us</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Toggle menu function
        function toggleMenu(menuId) {
            const menu = document.getElementById(menuId);
            const icon = menu.previousElementSibling.querySelector('.submenu-icon');
            menu.classList.toggle('active');
            icon.classList.toggle('rotate');
        }

        // Add animations on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Add animation to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add animation to activity items
            const activityItems = document.querySelectorAll('.activity-item');
            activityItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html> 