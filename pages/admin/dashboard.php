<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Loan.php';
require_once '../../classes/Incident.php';

$member = new Member();
$payment = new Payment();
$loan = new Loan();
$incident = new Incident();

$total_members = count($member->getAllMembers());
$total_membership_fees = $payment->getTotalPayments();
$total_loans = $loan->getTotalLoans();
$total_society_payments = $payment->getTotalSocietyIssuedPayments();

$all_loans = $loan->getAllLoans();
$total_interest_loans = 0;
foreach ($all_loans as $loan) {
    $total_interest_loans += ($loan['amount'] * $loan['interest_rate'] / 100) * ($loan['duration'] / 12);
}

$total_loan_settlements = array_sum(array_column($payment->getPaymentsByType('Loan Settlement'), 'amount'));
$net_position = ($total_membership_fees + $total_loan_settlements + $total_interest_loans) - ($total_society_payments + $total_loans);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f9fafb;
            --text-color: #111827;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --btn-bg: #ea580c;
            --btn-hover: #c2410c;
            --border-color: #e5e7eb;
            --accent-color: #f97316;
            --sidebar-width: 72px;
            --sidebar-expanded: 260px;
        }
        [data-theme="dark"] {
            --bg-color: #111827;
            --text-color: #f9fafb;
            --card-bg: #1f2937;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #f97316;
            --btn-hover: #ea580c;
            --border-color: #374151;
            --accent-color: #fb923c;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            color: white;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: translateY(-2px);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            transition: width 0.3s ease;
            position: fixed;
            top: 84px;
            left: 16px;
            height: calc(100vh - 104px);
            overflow: hidden;
            z-index: 20;
        }
        .sidebar:hover, .sidebar.expanded {
            width: var(--sidebar-expanded);
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--text-color);
            transition: all 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background-color: var(--accent-color);
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 16px;
        }
        .sidebar-item span {
            display: none;
            white-space: nowrap;
        }
        .sidebar:hover .sidebar-item span, .sidebar.expanded .sidebar-item span {
            display: inline;
        }
        .stat-icon {
            color: var(--accent-color);
        }
        .financial-card {
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            border-radius: 16px;
            padding: 2rem;
        }
        .quick-action {
            background-color: var(--btn-bg);
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .quick-action:hover {
            background-color: var(--btn-hover);
            transform: translateY(-2px);
        }
        .main-content {
            margin-left: calc(var(--sidebar-width) + 32px);
            transition: margin-left 0.3s ease;
        }
        .sidebar:hover ~ .main-content, .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 32px);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: 0;
            }
            .sidebar.expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
            .sidebar:hover {
                width: var(--sidebar-width);
            }
            .sidebar:hover ~ .main-content {
                margin-left: calc(var(--sidebar-width) + 32px);
            }
        }
        .theme-toggle {
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            background-color: var(--border-color);
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <button class="md:hidden text-orange-600" id="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <button class="text-white btn-admin" id="pin-sidebar" aria-label="Pin Sidebar">
                <i class="fas fa-thumbtack"></i>
            </button>
            <a href="../login.php?logout=1" class="text-white btn-admin" aria-label="Logout">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-6">
            <li class="sidebar-item active"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="sidebar-item"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-8 main-content" id="main-content">
        <div class="mb-8">
            <h1 class="text-4xl font-extrabold text-orange-600">Admin Dashboard</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Effortlessly oversee society operations.</p>
        </div>

        <!-- Overview Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <a href="members.php" class="card flex items-center space-x-4" aria-label="View Members">
                <div class="stat-icon text-3xl"><i class="fas fa-users"></i></div>
                <div>
                    <h2 class="text-base font-semibold">Total Members</h2>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $total_members; ?></p>
                </div>
            </a>
            <div class="card flex items-center space-x-4" aria-label="Membership Fees">
                <div class="stat-icon text-3xl"><i class="fas fa-money-bill"></i></div>
                <div>
                    <h2 class="text-base font-semibold">Membership Fees</h2>
                    <p class="text-2xl font-bold text-orange-600">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                </div>
            </div>
            <a href="loans.php" class="card flex items-center space-x-4" aria-label="View Loans">
                <div class="stat-icon text-3xl"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <h2 class="text-base font-semibold">Total Loans</h2>
                    <p class="text-2xl font-bold text-orange-600">LKR <?php echo number_format($total_loans, 2); ?></p>
                </div>
            </a>
            <a href="payments.php" class="card flex items-center space-x-4" aria-label="View Payments">
                <div class="stat-icon text-3xl"><i class="fas fa-hand-holding-heart"></i></div>
                <div>
                    <h2 class="text-base font-semibold">Society Payments</h2>
                    <p class="text-2xl font-bold text-orange-600">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                </div>
            </a>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-8">
            <h2 class="text-xl font-semibold mb-4 text-orange-600">Quick Actions</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <a href="add_member.php" class="quick-action" aria-label="Add Member"><i class="fas fa-user-plus mr-2"></i> Add Member</a>
                <a href="incidents.php?action=add" class="quick-action" aria-label="Record Incident"><i class="fas fa-file-alt mr-2"></i> Record Incident</a>
                <a href="payments.php" class="quick-action" aria-label="Add Payment"><i class="fas fa-money-bill mr-2"></i> Add Payment</a>
                <a href="loans.php?action=add" class="quick-action" aria-label="Add Loan"><i class="fas fa-hand-holding-usd mr-2"></i> Add Loan</a>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="financial-card">
            <h2 class="text-2xl font-semibold mb-6">Financial Summary</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="text-base">Membership Fees</p>
                        <p class="text-xl font-bold">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="text-base">Loan Settlements</p>
                        <p class="text-xl font-bold">LKR <?php echo number_format($total_loan_settlements, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-percentage"></i></div>
                    <div>
                        <p class="text-base">Loan Interest</p>
                        <p class="text-xl font-bold">LKR <?php echo number_format($total_interest_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <p class="text-base">Society Payments</p>
                        <p class="text-xl font-bold">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <p class="text-base">Loans Issued</p>
                        <p class="text-xl font-bold">LKR <?php echo number_format($total_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 bg-white/20 p-4 rounded-lg">
                    <div class="stat-icon text-2xl"><i class="fas fa-balance-scale"></i></div>
                    <div>
                        <p class="text-base">Net Position</p>
                        <p class="text-2xl font-bold <?php echo $net_position >= 0 ? 'text-green-200' : 'text-red-200'; ?>">
                            LKR <?php echo number_format($net_position, 2); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-6 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400 text-sm">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const pinSidebar = document.getElementById('pin-sidebar');
    const themeToggle = document.getElementById('theme-toggle');
    let isPinned = false;

    // Sidebar toggle for mobile
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('expanded');
    });

    // Pin sidebar
    pinSidebar.addEventListener('click', () => {
        isPinned = !isPinned;
        sidebar.classList.toggle('expanded', isPinned);
        pinSidebar.querySelector('i').classList.toggle('fa-rotate-45', isPinned);
    });

    // Theme toggle
    themeToggle.addEventListener('click', () => {
        document.body.dataset.theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
        themeToggle.querySelector('i').classList.toggle('fa-moon');
        themeToggle.querySelector('i').classList.toggle('fa-sun');
    });

    // Prevent hover expansion on mobile unless pinned
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => {
            if (!isPinned) e.preventDefault();
        });
    }
</script>
</body>
</html>