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
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #e5e7eb;
            --accent-color: #f97316;
            --sidebar-width: 64px;
            --sidebar-expanded: 240px;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            --btn-bg: #e67e22;
            --btn-hover: #f39c12;
            --border-color: #4b5563;
            --accent-color: #fb923c;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            padding: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.03);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            transition: width 0.3s ease;
            position: fixed;
            top: 80px;
            left: 16px;
            height: calc(100vh - 100px);
            overflow: hidden;
            z-index: 20;
        }
        .sidebar:hover, .sidebar-expanded {
            width: var(--sidebar-expanded);
            z-index: 30;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-color);
            transition: background-color 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background-color: #f97316;
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
        }
        .sidebar-item span {
            display: none;
            white-space: nowrap;
        }
        .sidebar:hover .sidebar-item span, .sidebar-expanded .sidebar-item span {
            display: inline;
        }
        .stat-icon {
            color: var(--accent-color);
        }
        .financial-card {
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            border-radius: 12px;
        }
        .quick-action {
            background-color: var(--btn-bg);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .quick-action:hover {
            background-color: #f97316;
            transform: translateY(-2px);
        }
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: calc(var(--sidebar-width) + 16px);
        }
        .sidebar:hover ~ .main-content, .sidebar-expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 16px);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: 0;
            }
            .sidebar-expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
            .sidebar:hover {
                width: var(--sidebar-width);
            }
            .sidebar:hover ~ .main-content {
                margin-left: calc(var(--sidebar-width) + 16px);
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="md:hidden text-orange-600" id="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <a href="../login.php?logout=1" class="text-white px-4 py-2 rounded-lg btn-admin" aria-label="Logout">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-4">
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
    <main class="flex-1 p-6 main-content" id="main-content">
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-orange-600">Admin Dashboard</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage society operations with ease.</p>
        </div>

        <!-- Overview Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <a href="members.php" class="card flex items-center space-x-3" aria-label="View Members">
                <div class="stat-icon text-2xl"><i class="fas fa-users"></i></div>
                <div>
                    <h2 class="text-sm font-medium">Total Members</h2>
                    <p class="text-xl font-bold text-orange-600"><?php echo $total_members; ?></p>
                </div>
            </a>
            <div class="card flex items-center space-x-3" aria-label="Membership Fees">
                <div class="stat-icon text-2xl"><i class="fas fa-money-bill"></i></div>
                <div>
                    <h2 class="text-sm font-medium">Membership Fees</h2>
                    <p class="text-xl font-bold text-orange-600">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                </div>
            </div>
            <a href="loans.php" class="card flex items-center space-x-3" aria-label="View Loans">
                <div class="stat-icon text-2xl"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <h2 class="text-sm font-medium">Total Loans</h2>
                    <p class="text-xl font-bold text-orange-600">LKR <?php echo number_format($total_loans, 2); ?></p>
                </div>
            </a>
            <a href="payments.php" class="card flex items-center space-x-3" aria-label="View Payments">
                <div class="stat-icon text-2xl"><i class="fas fa-hand-holding-heart"></i></div>
                <div>
                    <h2 class="text-sm font-medium">Society Payments</h2>
                    <p class="text-xl font-bold text-orange-600">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                </div>
            </a>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold mb-3 text-orange-600">Quick Actions</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="add_member.php" class="quick-action" aria-label="Add Member"><i class="fas fa-user-plus mr-1"></i> Add Member</a>
                <a href="incidents.php?action=add" class="quick-action" aria-label="Record Incident"><i class="fas fa-file-alt mr-1"></i> Record Incident</a>
                <a href="payments.php" class="quick-action" aria-label="Add Payment"><i class="fas fa-money-bill mr-1"></i> Add Payment</a>
                <a href="loans.php?action=add" class="quick-action" aria-label="Add Loan"><i class="fas fa-hand-holding-usd mr-1"></i> Add Loan</a>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="financial-card p-6">
            <h2 class="text-xl font-semibold mb-4">Financial Summary</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="stat-icon text-xl"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="text-sm">Membership Fees</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="stat-icon text-xl"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="text-sm">Loan Settlements</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_loan_settlements, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="stat-icon text-xl"><i class="fas fa-percentage"></i></div>
                    <div>
                        <p class="text-sm">Loan Interest</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_interest_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="stat-icon text-xl"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <p class="text-sm">Society Payments</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="stat-icon text-xl"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <p class="text-sm">Loans Issued</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="stat-icon text-xl"><i class="fas fa-balance-scale"></i></div>
                    <div>
                        <p class="text-sm">Net Position</p>
                        <p class="text-lg font-bold <?php echo $net_position >= 0 ? 'text-green-200' : 'text-red-200'; ?>">
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
    const mainContent = document.getElementById('main-content');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-expanded');
    });

    // Prevent hover expansion on mobile
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => e.preventDefault());
    }
</script>
</body>
</html>