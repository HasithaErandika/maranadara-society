<?php
define('APP_START', true);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../../includes/header.php';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #F97316;
            --orange-dark: #C2410C;
            --orange-light: #FED7AA;
            --gray-bg: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --sidebar-width: 100px;
            --sidebar-expanded: 240px; /* Increased for better visibility of expansion */
        }

        body {
            background: var(--gray-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            line-height: 1.5;
            position: relative; /* Needed for absolute positioning of sidebar */
        }

        .main-content {
            margin-left: 300px; /* Initial left margin to avoid overlap with the default sidebar */
            transition: margin-left 0.3s ease; /* You can keep this for other potential margin changes */
            padding: 20px; /* Add some padding around the content */
        }

        .sidebar {
            position: absolute; /* Take it out of the normal flow */
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 10%; /* Make it full height */
            background: var(--card-bg); /* Give it a background */
            box-shadow: var(--shadow);
            transition: width 0.3s ease;
            z-index: 10; /* Ensure it's on top of the content */
        }

        .sidebar:hover, .sidebar.expanded {
            width: var(--sidebar-expanded);
        }

        /* Remove the rules that were shifting the main content */
        /* .sidebar:hover ~ .main-content, .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) - 90px);
        } */

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .stat-card {
            border-left: 4px solid var(--primary-orange);
        }

        .btn-primary {
            background: var(--primary-orange);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--orange-dark);
            transform: translateY(-1px);
        }

        .financial-summary {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .animate-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 16px; /* Adjust spacing for smaller screens */
                padding: 10px; /* Adjust padding for smaller screens */
            }

            .sidebar {
                width: 80px; /* Smaller default width for mobile */
            }

            .sidebar:hover, .sidebar.expanded {
                width: 200px; /* Smaller expanded width for mobile */
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen pt-20">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content p-6 flex-1">
        <div class="mb-8 animate-in">
            <h1 class="text-3xl font-semibold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-600 mt-1">Welcome back! Here's your society overview.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <a href="members.php" class="card stat-card animate-in">
                <div class="flex items-center gap-4">
                    <i class="fas fa-users text-2xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Total Members</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $total_members; ?></p>
                    </div>
                </div>
            </a>
            <div class="card stat-card animate-in">
                <div class="flex items-center gap-4">
                    <i class="fas fa-money-bill text-2xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Membership Fees</p>
                        <p class="text-2xl font-semibold text-gray-900">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                    </div>
                </div>
            </div>
            <a href="loans.php" class="card stat-card animate-in">
                <div class="flex items-center gap-4">
                    <i class="fas fa-hand-holding-usd text-2xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Total Loans</p>
                        <p class="text-2xl font-semibold text-gray-900">LKR <?php echo number_format($total_loans, 2); ?></p>
                    </div>
                </div>
            </a>
            <a href="payments.php" class="card stat-card animate-in">
                <div class="flex items-center gap-4">
                    <i class="fas fa-hand-holding-heart text-2xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Society Payments</p>
                        <p class="text-2xl font-semibold text-gray-900">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                    </div>
                </div>
            </a>
        </div>

        <div class="card mb-8 animate-in">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="add_member.php" class="btn-primary"><i class="fas fa-user-plus mr-2"></i> Add Member</a>
                <a href="incidents.php?action=add" class="btn-primary"><i class="fas fa-file-alt mr-2"></i> Record Incident</a>
                <a href="payments.php" class="btn-primary"><i class="fas fa-money-bill mr-2"></i> Add Payment</a>
                <a href="loans.php?action=add" class="btn-primary"><i class="fas fa-hand-holding-usd mr-2"></i> Add Loan</a>
            </div>
        </div>

        <div class="financial-summary animate-in">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Financial Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="flex items-center gap-4">
                    <i class="fas fa-arrow-down text-xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Membership Fees</p>
                        <p class="text-lg font-medium">LKR <?php echo number_format($total_membership_fees, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <i class="fas fa-arrow-down text-xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Loan Settlements</p>
                        <p class="text-lg font-medium">LKR <?php echo number_format($total_loan_settlements, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <i class="fas fa-percentage text-xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Loan Interest</p>
                        <p class="text-lg font-medium">LKR <?php echo number_format($total_interest_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <i class="fas fa-arrow-up text-xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Society Payments</p>
                        <p class="text-lg font-medium">LKR <?php echo number_format($total_society_payments, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <i class="fas fa-arrow-up text-xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Loans Issued</p>
                        <p class="text-lg font-medium">LKR <?php echo number_format($total_loans, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4 bg-orange-50 p-3 rounded-md">
                    <i class="fas fa-balance-scale text-xl text-orange-500"></i>
                    <div>
                        <p class="text-sm text-gray-600">Net Position</p>
                        <p class="text-xl font-semibold <?php echo $net_position >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            LKR <?php echo number_format($net_position, 2); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- <?php include '../../includes/footer.php'; ?> -->

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        let isExpanded = false;

        sidebar.addEventListener('click', (e) => {
            if (e.target.closest('.sidebar-item')) {
                isExpanded = true;
                sidebar.classList.add('expanded');
            }
        });

        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && isExpanded) {
                isExpanded = false;
                sidebar.classList.remove('expanded');
            }
        });
    });
</script>
</body>
</html>