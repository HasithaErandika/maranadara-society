<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Incident.php';
require_once '../../classes/Loan.php';

// Assume the logged-in user’s username links to their member record
$member = new Member();
$payment = new Payment();
$incident = new Incident();
$loan = new Loan();

// Fetch member details (assuming username matches a member record)
$conn = (new Database())->getConnection();
$stmt = $conn->prepare("SELECT * FROM members WHERE name = ?"); // Adjust if you use a different identifier
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$member_details = $stmt->get_result()->fetch_assoc();

// Fetch payments made by the user (membership fees)
$stmt = $conn->prepare("SELECT amount, date FROM payments WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$membership_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch payments received (incidents, e.g., funeral aid)
$stmt = $conn->prepare("SELECT type, date, payment FROM incidents WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$received_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch loans taken by the user
$stmt = $conn->prepare("SELECT amount, interest_rate, duration, monthly_payment FROM loans WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --btn-bg: #2ecc71; /* User green */
            --btn-hover: #27ae60;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #27ae60;
            --btn-hover: #219653;
            --border-color: #4b5563;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            font-family: 'Noto Sans', sans-serif;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-user {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-user:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .table-hover tbody tr:hover {
            background-color: #e8f5e9; /* Light green */
        }
        .sidebar {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-green-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo $_SESSION['user']; ?></span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-user">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="w-64 sidebar p-6 fixed h-full">
        <h3 class="text-xl font-bold mb-6 text-green-600">Member Menu</h3>
        <ul class="space-y-4">
            <li><a href="#membership" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-id-card mr-2"></i>Membership Details</a></li>
            <li><a href="#payments" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-money-bill mr-2"></i>Payments</a></li>
            <li><a href="#received" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-hand-holding-heart mr-2"></i>Received Payments</a></li>
            <li><a href="#loans" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i>Loans</a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-green-600">Member Dashboard</h1>

        <!-- Membership Details -->
        <div id="membership" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Membership Details</h2>
            <?php if ($member_details): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($member_details['name']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($member_details['address']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($member_details['contact']); ?></p>
            <?php else: ?>
                <p class="text-gray-600 dark:text-gray-400">No membership details found. Contact an admin.</p>
            <?php endif; ?>
        </div>

        <!-- Membership Payments -->
        <div id="payments" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Membership Payments</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($membership_payments as $p): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membership_payments)): ?>
                        <tr>
                            <td colspan="2" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No payments recorded.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments Received -->
        <div id="received" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Payments Received from Society</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Type</th>
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($received_payments as $r): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($r['type']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($r['date']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($r['payment'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($received_payments)): ?>
                        <tr>
                            <td colspan="3" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No payments received.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loans -->
        <div id="loans" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Loans Taken</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Interest Rate (%)</th>
                        <th class="py-2 px-4 text-left">Duration (Months)</th>
                        <th class="py-2 px-4 text-left">Monthly Payment (LKR)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loans as $l): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo number_format($l['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['interest_rate'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo $l['duration']; ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['monthly_payment'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loans)): ?>
                        <tr>
                            <td colspan="4" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No loans taken.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
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