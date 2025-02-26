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
$total_membership_fees = $payment->getTotalPayments(); // Membership fees only
$total_loans = $loan->getTotalLoans();
$total_society_payments = $payment->getTotalSocietyIssuedPayments();

// Calculate total interest from all loans
$all_loans = $loan->getAllLoans();
$total_interest_loans = 0;
foreach ($all_loans as $loan) {
    $total_interest_loans += ($loan['amount'] * $loan['interest_rate'] / 100) * ($loan['duration'] / 12);
}

$total_loan_settlements = array_sum(array_column($payment->getPaymentsByType('Loan Settlement'), 'amount'));
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
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #d1d5db;
            --accent-color: #f97316;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
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
            border-radius: 0.75rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.15);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .sidebar {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 0.75rem;
        }
        .stat-icon {
            color: var(--accent-color);
        }
        .quick-action {
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .quick-action:hover {
            background-color: #f97316;
            transform: translateY(-3px);
        }
        .financial-card {
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo $_SESSION['user']; ?></span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-admin">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="w-64 sidebar p-6 fixed h-fit mt-4 ml-6">
        <h3 class="text-xl font-bold mb-6 text-orange-600">Admin Menu</h3>
        <ul class="space-y-4">
            <li><a href="add_member.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-user-plus mr-2"></i>Add Member</a></li>
            <li><a href="incidents.php?action=add" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-file-alt mr-2"></i>Record Incident</a></li>
            <li><a href="payments.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-money-bill mr-2"></i>Manage Payments</a></li>
            <li><a href="loans.php?action=add" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i>Add Loan</a></li>
            <li><a href="members.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-users mr-2"></i>Manage Members</a></li>
            <li><a href="loans.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i>Manage Loans</a></li>
            <li><a href="incidents.php" class="text-gray-700 dark:text-gray-300 hover:text-orange-600 flex items-center"><i class="fas fa-file-alt mr-2"></i>Manage Incidents</a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 ml-72">
        <div class="mb-8">
            <h1 class="text-4xl font-extrabold text-orange-600">Admin Dashboard</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Oversee Maranadhara Samithi operations efficiently.</p>
        </div>

        <!-- Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <a href="members.php" class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-users"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Total Members</h2>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $total_members; ?></p>
                </div>
            </a>
            <div class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-money-bill"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Membership Fees</h2>
                    <p class="text-2xl font-bold text-orange-600">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                </div>
            </div>
            <a href="loans.php" class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Total Loans</h2>
                    <p class="text-2xl font-bold text-orange-600">LKR <?php echo number_format($total_loans, 2); ?></p>
                </div>
            </a>
            <a href="payments.php" class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-hand-holding-heart"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Society Payments</h2>
                    <p class="text-2xl font-bold text-orange-600">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                </div>
            </a>
        </div>

        <!-- Quick Actions -->
        <div class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-orange-600">Quick Actions</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="add_member.php" class="quick-action text-white px-4 py-3 rounded-lg btn-admin text-center flex items-center justify-center"><i class="fas fa-user-plus mr-2"></i>Add Member</a>
                <a href="incidents.php?action=add" class="quick-action text-white px-4 py-3 rounded-lg btn-admin text-center flex items-center justify-center"><i class="fas fa-file-alt mr-2"></i>Record Incident</a>
                <a href="payments.php" class="quick-action text-white px-4 py-3 rounded-lg btn-admin text-center flex items-center justify-center"><i class="fas fa-money-bill mr-2"></i>Add Payment</a>
                <a href="loans.php?action=add" class="quick-action text-white px-4 py-3 rounded-lg btn-admin text-center flex items-center justify-center"><i class="fas fa-hand-holding-usd mr-2"></i>Add Loan</a>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card financial-card p-6">
            <h2 class="text-2xl font-semibold mb-4">Financial Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="text-white">Income: Membership Fees</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <p class="text-white">Outgoing: Society Payments</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-hand-holding-usd"></i></div>
                    <div>
                        <p class="text-white">Total Loans Issued</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-percentage"></i></div>
                    <div>
                        <p class="text-white">Total Interest from Loans</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_interest_loans, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <p class="text-white">Income: Loan Settlements</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_loan_settlements, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="stat-icon text-2xl"><i class="fas fa-balance-scale"></i></div>
                    <div>
                        <p class="text-white">Net Financial Position</p>
                        <p class="text-lg font-bold">LKR <?php echo number_format($total_membership_fees + $total_loan_settlements - $total_society_payments, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-8 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>
</body>
</html>