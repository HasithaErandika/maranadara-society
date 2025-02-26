<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
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
$error = $success = '';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$members = $member->getAllMembers();
if ($search) {
    $members = array_filter($members, function($m) use ($search) {
        $search = strtolower($search);
        return stripos(strtolower($m['member_id']), $search) !== false ||
            stripos(strtolower($m['full_name']), $search) !== false ||
            stripos(strtolower($m['nic_number'] ?? ''), $search) !== false ||
            stripos(strtolower($m['contact_number'] ?? ''), $search) !== false;
    });
}

if (isset($_GET['member_id'])) {
    $selected_member_id = $_GET['member_id'];
    $selected_member = array_filter($members, function($m) use ($selected_member_id) {
        return $m['member_id'] == $selected_member_id;
    });
    $selected_member = reset($selected_member);
    if ($selected_member) {
        $member_details = $selected_member;
        $member_id = $member_details['id'];
        $family_details = $family->getFamilyDetailsByMemberId($member_id);
        $all_payments = $payment->getPaymentsByMemberId($member_id);

        // Split payments by type
        $society_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Society Issued');
        $membership_fee_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Membership Fee');
        $loan_settlement_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Loan Settlement');

        // Calculate totals
        $total_from_member = array_sum(array_column($membership_fee_payments, 'amount')) + array_sum(array_column($loan_settlement_payments, 'amount'));
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
        $total_dues = max(0, $total_dues);

        $documents = $document->getDocumentsByMemberId($member_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Member deleted successfully!";
            $members = $member->getAllMembers();
        } else {
            $error = "Error deleting member: " . $conn->error;
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $full_name = $_POST['full_name'];
        $contact_number = $_POST['contact_number'];
        $membership_type = $_POST['membership_type'];
        $payment_status = $_POST['payment_status'];
        $member_status = $_POST['member_status'];

        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("UPDATE members SET full_name = ?, contact_number = ?, membership_type = ?, payment_status = ?, member_status = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $full_name, $contact_number, $membership_type, $payment_status, $member_status, $id);
        if ($stmt->execute()) {
            $success = "Member updated successfully!";
            $members = $member->getAllMembers();
        } else {
            $error = "Error updating member: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - Maranadhara Samithi</title>
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
        .btn-delete {
            background-color: #dc2626;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
        }
        .table-hover tbody tr:hover {
            background-color: #fef5e7;
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
            outline: none;
        }
        .member-link {
            color: var(--accent-color);
            text-decoration: underline;
            transition: color 0.3s ease;
        }
        .member-link:hover {
            color: var(--btn-hover);
        }
        .search-bar {
            position: relative;
        }
        .search-bar i {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            color: #6b7280;
        }
        .stat-icon {
            color: var(--accent-color);
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
<div class="container mx-auto px-6 py-20">
    <div class="card p-6">
        <h1 class="text-3xl font-extrabold mb-6 text-orange-600">Manage Members</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Search Bar -->
        <form method="GET" class="mb-6 search-bar">
            <div class="relative flex items-center">
                <i class="fas fa-search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Member ID, Name, NIC, or Contact" class="input-field w-full pl-10 pr-4 py-2 rounded-lg">
                <button type="submit" class="ml-2 text-white px-4 py-2 rounded-lg btn-admin"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>

        <?php if (isset($_GET['member_id']) && $selected_member): ?>
            <!-- Detailed Member View -->
            <h2 class="text-2xl font-semibold mb-4 text-orange-600">Member Details: <?php echo htmlspecialchars($member_details['member_id']); ?></h2>

            <!-- Member Information -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Member Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <p><strong>Member ID:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></p>
                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($member_details['full_name']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($member_details['date_of_birth']); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($member_details['gender']); ?></p>
                    <p><strong>NIC Number:</strong> <?php echo htmlspecialchars($member_details['nic_number']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($member_details['address']); ?></p>
                    <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($member_details['contact_number']); ?></p>
                    <p><strong>Email:</strong> <?php echo $member_details['email'] ? htmlspecialchars($member_details['email']) : 'N/A'; ?></p>
                    <p><strong>Occupation:</strong> <?php echo $member_details['occupation'] ? htmlspecialchars($member_details['occupation']) : 'N/A'; ?></p>
                </div>
            </div>

            <!-- Membership Details -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Membership Details</h3>
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
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Family Details</h3>
                <?php if ($family_details): ?>
                    <p><strong>Spouse's Name:</strong> <?php echo $family_details['spouse_name'] ? htmlspecialchars($family_details['spouse_name']) : 'N/A'; ?></p>
                    <p><strong>Children:</strong> <?php echo $family_details['children_info'] ? htmlspecialchars($family_details['children_info']) : 'N/A'; ?></p>
                    <p><strong>Dependents:</strong> <?php echo $family_details['dependents_info'] ? htmlspecialchars($family_details['dependents_info']) : 'N/A'; ?></p>
                <?php else: ?>
                    <p class="text-gray-600 dark:text-gray-400">No family details recorded.</p>
                <?php endif; ?>
            </div>

            <!-- Financial & Payment Records -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Financial & Payment Records</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="flex items-center space-x-2">
                        <div class="stat-icon text-2xl"><i class="fas fa-arrow-up"></i></div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Payments from Member</p>
                            <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_from_member, 2); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="stat-icon text-2xl"><i class="fas fa-arrow-down"></i></div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Payments from Society</p>
                            <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_from_society, 2); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="stat-icon text-2xl"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Pending Dues</p>
                            <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format(max(0, $pending_dues), 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Payments Issued from Society -->
                <h4 class="text-md font-semibold mb-2 text-orange-600">Payments Issued from Society (Total: LKR <?php echo number_format($total_society, 2); ?>)</h4>
                <div class="overflow-x-auto mb-6">
                    <table class="w-full table-hover">
                        <thead>
                        <tr class="border-b dark:border-gray-600">
                            <th class="py-2 px-4 text-left">Date</th>
                            <th class="py-2 px-4 text-left">Amount (LKR)</th>
                            <th class="py-2 px-4 text-left">Mode</th>
                            <th class="py-2 px-4 text-left">Receipt</th>
                            <th class="py-2 px-4 text-left">Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($society_payments as $p): ?>
                            <tr class="border-b dark:border-gray-600">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                                <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($society_payments)): ?>
                            <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No society payments issued.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Payments from Member for Membership Fees -->
                <h4 class="text-md font-semibold mb-2 text-orange-600">Payments for Membership Fees (Total: LKR <?php echo number_format($total_membership, 2); ?>)</h4>
                <div class="overflow-x-auto mb-6">
                    <table class="w-full table-hover">
                        <thead>
                        <tr class="border-b dark:border-gray-600">
                            <th class="py-2 px-4 text-left">Date</th>
                            <th class="py-2 px-4 text-left">Amount (LKR)</th>
                            <th class="py-2 px-4 text-left">Mode</th>
                            <th class="py-2 px-4 text-left">Receipt</th>
                            <th class="py-2 px-4 text-left">Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($membership_fee_payments as $p): ?>
                            <tr class="border-b dark:border-gray-600">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                                <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($membership_fee_payments)): ?>
                            <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No membership fee payments recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Payments for Loan Settlements -->
                <h4 class="text-md font-semibold mb-2 text-orange-600">Payments for Loan Settlements (Total: LKR <?php echo number_format($total_loan_settlement, 2); ?>)</h4>
                <div class="overflow-x-auto mb-6">
                    <table class="w-full table-hover">
                        <thead>
                        <tr class="border-b dark:border-gray-600">
                            <th class="py-2 px-4 text-left">Date</th>
                            <th class="py-2 px-4 text-left">Amount (LKR)</th>
                            <th class="py-2 px-4 text-left">Mode</th>
                            <th class="py-2 px-4 text-left">Receipt</th>
                            <th class="py-2 px-4 text-left">Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($loan_settlement_payments as $p): ?>
                            <tr class="border-b dark:border-gray-600">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                                <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($loan_settlement_payments)): ?>
                            <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No loan settlement payments recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Incidents -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Incidents</h3>
                <div class="overflow-x-auto">
                    <table class="w-full table-hover">
                        <thead>
                        <tr class="border-b dark:border-gray-600">
                            <th class="py-2 px-4 text-left">Incident ID</th>
                            <th class="py-2 px-4 text-left">Type</th>
                            <th class="py-2 px-4 text-left">Date & Time</th>
                            <th class="py-2 px-4 text-left">Reporter</th>
                            <th class="py-2 px-4 text-left">Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidents as $i): ?>
                            <tr class="border-b dark:border-gray-600">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_type']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($i['reporter_name']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($i['remarks'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($incidents)): ?>
                            <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No incidents recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Loans -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Loans Taken</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="flex items-center space-x-2">
                        <div class="stat-icon text-2xl"><i class="fas fa-hand-holding-usd"></i></div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Total Loan Amount</p>
                            <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="stat-icon text-2xl"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Total Dues</p>
                            <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_dues, 2); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="stat-icon text-2xl"><i class="fas fa-percentage"></i></div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Total Interest</p>
                            <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                        </div>
                    </div>
                </div>
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
                            <tr><td colspan="4" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No loans taken.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Documents -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-2 text-orange-600">Documents</h3>
                <div class="overflow-x-auto">
                    <table class="w-full table-hover">
                        <thead>
                        <tr class="border-b dark:border-gray-600">
                            <th class="py-2 px-4 text-left">Document Type</th>
                            <th class="py-2 px-4 text-left">Notes</th>
                            <th class="py-2 px-4 text-left">Upload Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr class="border-b dark:border-gray-600">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($d['document_type']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($d['notes'] ?? 'N/A'); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($d['upload_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($documents)): ?>
                            <tr><td colspan="3" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No documents uploaded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Members Table -->
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Member ID</th>
                        <th class="py-2 px-4 text-left">Full Name</th>
                        <th class="py-2 px-4 text-left">Contact</th>
                        <th class="py-2 px-4 text-left">Membership Type</th>
                        <th class="py-2 px-4 text-left">Payment Status</th>
                        <th class="py-2 px-4 text-left">Member Status</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" class="member-link"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                            <td class="py-2 px-4">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($m['full_name']); ?>" class="input-field w-full px-2 py-1 rounded-lg">
                            </td>
                            <td class="py-2 px-4">
                                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($m['contact_number']); ?>" class="input-field w-full px-2 py-1 rounded-lg">
                            </td>
                            <td class="py-2 px-4">
                                <select name="membership_type" class="input-field w-full px-2 py-1 rounded-lg">
                                    <option value="Individual" <?php echo $m['membership_type'] == 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                    <option value="Family" <?php echo $m['membership_type'] == 'Family' ? 'selected' : ''; ?>>Family</option>
                                    <option value="Senior Citizen" <?php echo $m['membership_type'] == 'Senior Citizen' ? 'selected' : ''; ?>>Senior Citizen</option>
                                </select>
                            </td>
                            <td class="py-2 px-4">
                                <select name="payment_status" class="input-field w-full px-2 py-1 rounded-lg">
                                    <option value="Active" <?php echo $m['payment_status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Pending" <?php echo $m['payment_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Inactive" <?php echo $m['payment_status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </td>
                            <td class="py-2 px-4">
                                <select name="member_status" class="input-field w-full px-2 py-1 rounded-lg">
                                    <option value="Active" <?php echo $m['member_status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Deceased" <?php echo $m['member_status'] == 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                                    <option value="Resigned" <?php echo $m['member_status'] == 'Resigned' ? 'selected' : ''; ?>>Resigned</option>
                                </select>
                            </td>
                            <td class="py-2 px-4 flex space-x-2">
                                <button type="submit" name="update" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-save"></i></button>
                                <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="7" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No members found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>
</body>
</html>