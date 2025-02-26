<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Family.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Incident.php';
require_once '../../classes/Loan.php';
require_once '../../classes/Document.php';

$member = new Member();
$family = new Family();
$payment = new Payment();
$incident = new Incident();
$loan = new Loan();
$document = new Document();

$member_details = $member->getMemberByUsername($_SESSION['user']);
$member_id = $member_details['id'];

$family_details = $family->getFamilyDetailsByMemberId($member_id);
$all_payments = $payment->getPaymentsByMemberId($member_id);

// Split payments by type
$society_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Society Issued');
$membership_fee_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Membership Fee');
$loan_settlement_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Loan Settlement');

// Calculate totals
$total_from_you = array_sum(array_column($membership_fee_payments, 'amount')) + array_sum(array_column($loan_settlement_payments, 'amount'));
$total_from_society = array_sum(array_column($society_payments, 'amount'));
$total_society = array_sum(array_column($society_payments, 'amount'));
$total_membership = array_sum(array_column($membership_fee_payments, 'amount'));
$total_loan_settlement = array_sum(array_column($loan_settlement_payments, 'amount'));
$pending_dues = $member_details['contribution_amount'] * (date('Y') - substr($member_details['date_of_joining'], 0, 4) + 1) * 12 - $total_membership;

$incidents = $incident->getIncidentsByMemberId($member_id);
$loans = $loan->getLoansByMemberId($member_id);

// Loan calculations
$total_loan_amount = array_sum(array_column($loans, 'amount'));
$total_monthly_payment = array_sum(array_column($loans, 'monthly_payment'));
$total_interest_amount = 0;
$total_dues = 0;
foreach ($loans as $loan) {
    $total_interest_amount += ($loan['amount'] * $loan['interest_rate'] / 100) * ($loan['duration'] / 12);
    $total_dues += ($loan['monthly_payment'] * $loan['duration']) - array_sum(array_column($loan_settlement_payments, 'amount'));
}
$total_dues = max(0, $total_dues); // Ensure no negative dues

$documents = $document->getDocumentsByMemberId($member_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f0f9ff;
            --text-color: #1e40af;
            --card-bg: #ffffff;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --btn-bg: #3b82f6;
            --btn-hover: #2563eb;
            --border-color: #bfdbfe;
            --accent-color: #60a5fa;
        }
        [data-theme="dark"] {
            --bg-color: #1e3a8a;
            --text-color: #dbeafe;
            --card-bg: #1e40af;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
            --btn-bg: #60a5fa;
            --btn-hover: #93c5fd;
            --border-color: #3b82f6;
            --accent-color: #93c5fd;
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
            border-radius: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.15);
        }
        .btn-user {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-user:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .sidebar {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 1rem;
        }
        .welcome-card {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #dbeafe;
        }
        .stat-icon {
            color: var(--accent-color);
        }
    </style>
</head>
<body class="min-h-screen bg-blue-50">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-blue-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-blue-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-blue-700 dark:text-blue-200">Hello, <?php echo $_SESSION['user']; ?>!</span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-user">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex pt-20">
    <!-- Sidebar -->
    <aside class="w-64 sidebar p-6 fixed h-fit mt-4 ml-6">
        <h3 class="text-xl font-bold mb-6 text-blue-600">Your Menu</h3>
        <ul class="space-y-4">
            <li><a href="#welcome" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-home mr-2"></i>Welcome</a></li>
            <li><a href="#membership" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-id-card mr-2"></i>Membership</a></li>
            <li><a href="#family" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-users mr-2"></i>Family</a></li>
            <li><a href="#payments" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-wallet mr-2"></i>Payments</a></li>
            <li><a href="#incidents" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-exclamation-circle mr-2"></i>Incidents</a></li>
            <li><a href="#loans" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i>Loans</a></li>
            <li><a href="#documents" class="text-blue-700 dark:text-blue-200 hover:text-blue-500 flex items-center"><i class="fas fa-folder mr-2"></i>Documents</a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 ml-72">
        <!-- Welcome Card -->
        <div id="welcome" class="card welcome-card p-6 mb-8">
            <h1 class="text-3xl font-extrabold">Welcome, <?php echo htmlspecialchars($member_details['full_name']); ?>!</h1>
            <p class="mt-2">Your member ID is <?php echo htmlspecialchars($member_details['member_id']); ?>. Here’s everything you need to know about your membership.</p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-arrow-up"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Payments from You</h2>
                    <p class="text-2xl font-bold text-blue-600">LKR <?php echo number_format($total_from_you, 2); ?></p>
                </div>
            </div>
            <div class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Payments from Society</h2>
                    <p class="text-2xl font-bold text-blue-600">LKR <?php echo number_format($total_from_society, 2); ?></p>
                </div>
            </div>
            <div class="card p-6 flex items-center space-x-4">
                <div class="stat-icon text-3xl"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <h2 class="text-lg font-semibold">Pending Dues</h2>
                    <p class="text-2xl font-bold text-blue-600">LKR <?php echo number_format($total_dues, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Membership Details -->
        <div id="membership" class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">Membership Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <p><strong>Membership Number:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></p>
                <p><strong>Date of Joining:</strong> <?php echo htmlspecialchars($member_details['date_of_joining']); ?></p>
                <p><strong>Membership Type:</strong> <?php echo htmlspecialchars($member_details['membership_type']); ?></p>
                <p><strong>Contribution Amount:</strong> LKR <?php echo number_format($member_details['contribution_amount'], 2); ?></p>
                <p><strong>Payment Status:</strong> <?php echo htmlspecialchars($member_details['payment_status']); ?></p>
                <p><strong>Member Status:</strong> <?php echo htmlspecialchars($member_details['member_status']); ?></p>
            </div>
        </div>

        <!-- Family Details -->
        <div id="family" class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">Family Details</h2>
            <?php if ($family_details): ?>
                <p><strong>Spouse's Name:</strong> <?php echo $family_details['spouse_name'] ? htmlspecialchars($family_details['spouse_name']) : 'N/A'; ?></p>
                <p><strong>Children:</strong> <?php echo $family_details['children_info'] ? htmlspecialchars($family_details['children_info']) : 'N/A'; ?></p>
                <p><strong>Dependents:</strong> <?php echo $family_details['dependents_info'] ? htmlspecialchars($family_details['dependents_info']) : 'N/A'; ?></p>
            <?php else: ?>
                <p class="text-blue-600 dark:text-blue-400">No family details recorded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Payments -->
        <div id="payments" class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">Your Payments</h2>

            <!-- Payments Issued from Society -->
            <h3 class="text-lg font-semibold mb-2 text-blue-600">Payments Issued from Society (Total: LKR <?php echo number_format($total_society, 2); ?>)</h3>
            <div class="overflow-x-auto mb-6">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-blue-600">
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Mode</th>
                        <th class="py-2 px-4 text-left">Receipt</th>
                        <th class="py-2 px-4 text-left">Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($society_payments as $p): ?>
                        <tr class="border-b dark:border-blue-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($society_payments)): ?>
                        <tr><td colspan="5" class="py-2 px-4 text-center text-blue-600 dark:text-blue-400">No society payments received yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Payments from Member for Membership Fees -->
            <h3 class="text-lg font-semibold mb-2 text-blue-600">Payments for Membership Fees (Total: LKR <?php echo number_format($total_membership, 2); ?>)</h3>
            <div class="overflow-x-auto mb-6">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-blue-600">
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Mode</th>
                        <th class="py-2 px-4 text-left">Receipt</th>
                        <th class="py-2 px-4 text-left">Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($membership_fee_payments as $p): ?>
                        <tr class="border-b dark:border-blue-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membership_fee_payments)): ?>
                        <tr><td colspan="5" class="py-2 px-4 text-center text-blue-600 dark:text-blue-400">No membership fee payments recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Payments for Loan Settlements -->
            <h3 class="text-lg font-semibold mb-2 text-blue-600">Payments for Loan Settlements (Total: LKR <?php echo number_format($total_loan_settlement, 2); ?>)</h3>
            <div class="overflow-x-auto mb-6">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-blue-600">
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Mode</th>
                        <th class="py-2 px-4 text-left">Receipt</th>
                        <th class="py-2 px-4 text-left">Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loan_settlement_payments as $p): ?>
                        <tr class="border-b dark:border-blue-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loan_settlement_payments)): ?>
                        <tr><td colspan="5" class="py-2 px-4 text-center text-blue-600 dark:text-blue-400">No loan settlement payments recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Incidents -->
        <div id="incidents" class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">Your Incidents</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-blue-600">
                        <th class="py-2 px-4 text-left">Incident ID</th>
                        <th class="py-2 px-4 text-left">Type</th>
                        <th class="py-2 px-4 text-left">Date & Time</th>
                        <th class="py-2 px-4 text-left">Reporter</th>
                        <th class="py-2 px-4 text-left">Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($incidents as $i): ?>
                        <tr class="border-b dark:border-blue-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_id']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_type']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['reporter_name']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['remarks'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($incidents)): ?>
                        <tr><td colspan="5" class="py-2 px-4 text-center text-blue-600 dark:text-blue-400">No incidents recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loans -->
        <div id="loans" class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">Your Loans</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="flex items-center space-x-2">
                    <div class="stat-icon text-2xl"><i class="fas fa-hand-holding-usd"></i></div>
                    <div>
                        <p class="text-blue-600 dark:text-blue-400">Total Loan Amount</p>
                        <p class="text-lg font-bold text-blue-600">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="stat-icon text-2xl"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <p class="text-blue-600 dark:text-blue-400">Total Dues</p>
                        <p class="text-lg font-bold text-blue-600">LKR <?php echo number_format($total_dues, 2); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="stat-icon text-2xl"><i class="fas fa-percentage"></i></div>
                    <div>
                        <p class="text-blue-600 dark:text-blue-400">Total Interest</p>
                        <p class="text-lg font-bold text-blue-600">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-blue-600">
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Interest Rate (%)</th>
                        <th class="py-2 px-4 text-left">Duration (Months)</th>
                        <th class="py-2 px-4 text-left">Monthly Payment (LKR)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loans as $l): ?>
                        <tr class="border-b dark:border-blue-600">
                            <td class="py-2 px-4"><?php echo number_format($l['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['interest_rate'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo $l['duration']; ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['monthly_payment'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loans)): ?>
                        <tr><td colspan="4" class="py-2 px-4 text-center text-blue-600 dark:text-blue-400">No loans taken yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Documents -->
        <div id="documents" class="card p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">Your Documents</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-blue-600">
                        <th class="py-2 px-4 text-left">Document Type</th>
                        <th class="py-2 px-4 text-left">Notes</th>
                        <th class="py-2 px-4 text-left">Upload Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr class="border-b dark:border-blue-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($d['document_type']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($d['notes'] ?? 'N/A'); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($d['upload_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="3" class="py-2 px-4 text-center text-blue-600 dark:text-blue-400">No documents uploaded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-8 bg-white dark:bg-blue-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-blue-600 dark:text-blue-400">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>
</body>
</html>