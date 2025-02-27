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

        $society_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Society Issued');
        $membership_fee_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Membership Fee');
        $loan_settlement_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Loan Settlement');

        $total_from_member = array_sum(array_column($membership_fee_payments, 'amount')) + array_sum(array_column($loan_settlement_payments, 'amount'));
        $total_from_society = array_sum(array_column($society_payments, 'amount'));
        $total_society = array_sum(array_column($society_payments, 'amount'));
        $total_membership = array_sum(array_column($membership_fee_payments, 'amount'));
        $total_loan_settlement = array_sum(array_column($loan_settlement_payments, 'amount'));
        $pending_dues = $member_details['contribution_amount'] * (date('Y') - substr($member_details['date_of_joining'], 0, 4) + 1) * 12 - $total_membership;

        $incidents = $incident->getIncidentsByMemberId($member_id);
        $loans = $loan->getLoansByMemberId($member_id);

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
        .btn-delete {
            background-color: #dc2626;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
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
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: calc(var(--sidebar-width) + 16px);
        }
        .sidebar:hover ~ .main-content, .sidebar-expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 16px);
        }
        .table-hover tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .table-hover tbody tr:hover {
            background-color: #fef5e7;
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            border-radius: 8px;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
            outline: none;
        }
        .member-link {
            color: var(--accent-color);
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
            left: 12px;
            transform: translateY(-50%);
            color: #6b7280;
        }
        .stat-icon {
            color: var(--accent-color);
        }
        .accordion-header {
            cursor: pointer;
            padding: 0.5rem;
            background-color: #f97316;
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .accordion-content {
            display: none;
            padding: 1rem;
        }
        .accordion-content.active {
            display: block;
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
            <li class="sidebar-item"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="sidebar-item"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item active"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 main-content" id="main-content">
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-orange-600">Manage Members</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">View and manage member details.</p>
        </div>

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
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID, Name, NIC, or Contact" class="input-field w-full pl-10 pr-4 py-2 rounded-lg" id="search-input">
                <button type="submit" class="ml-2 text-white px-4 py-2 btn-admin"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <?php if (isset($_GET['member_id']) && $selected_member): ?>
            <!-- Detailed Member View -->
            <div class="card mb-6">
                <h2 class="text-xl font-semibold mb-3 text-orange-600">Member Details: <?php echo htmlspecialchars($member_details['member_id']); ?></h2>

                <!-- Member Information -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($member_details['full_name']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($member_details['date_of_birth']); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($member_details['gender']); ?></p>
                    <p><strong>NIC:</strong> <?php echo htmlspecialchars($member_details['nic_number']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($member_details['address']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($member_details['contact_number']); ?></p>
                    <p><strong>Email:</strong> <?php echo $member_details['email'] ? htmlspecialchars($member_details['email']) : 'N/A'; ?></p>
                    <p><strong>Occupation:</strong> <?php echo $member_details['occupation'] ? htmlspecialchars($member_details['occupation']) : 'N/A'; ?></p>
                    <p><strong>Join Date:</strong> <?php echo htmlspecialchars($member_details['date_of_joining']); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($member_details['membership_type']); ?></p>
                    <p><strong>Contribution:</strong> LKR <?php echo number_format($member_details['contribution_amount'], 2); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo htmlspecialchars($member_details['payment_status']); ?></p>
                    <p><strong>Member Status:</strong> <?php echo htmlspecialchars($member_details['member_status']); ?></p>
                </div>
            </div>

            <!-- Family Details -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="family-details">Family Details <i class="fas fa-chevron-down ml-2"></i></div>
                <div class="accordion-content" id="family-details">
                    <?php if ($family_details): ?>
                        <p><strong>Spouse:</strong> <?php echo $family_details['spouse_name'] ? htmlspecialchars($family_details['spouse_name']) : 'N/A'; ?></p>
                        <p><strong>Children:</strong> <?php echo $family_details['children_info'] ? htmlspecialchars($family_details['children_info']) : 'N/A'; ?></p>
                        <p><strong>Dependents:</strong> <?php echo $family_details['dependents_info'] ? htmlspecialchars($family_details['dependents_info']) : 'N/A'; ?></p>
                    <?php else: ?>
                        <p class="text-gray-600 dark:text-gray-400">No family details recorded.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Financial & Payment Records -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="financial-records">Financial & Payment Records <i class="fas fa-chevron-down ml-2"></i></div>
                <div class="accordion-content" id="financial-records">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div class="flex items-center space-x-2">
                            <div class="stat-icon text-2xl"><i class="fas fa-arrow-up"></i></div>
                            <div>
                                <p class="text-sm">From Member</p>
                                <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_from_member, 2); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="stat-icon text-2xl"><i class="fas fa-arrow-down"></i></div>
                            <div>
                                <p class="text-sm">From Society</p>
                                <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_from_society, 2); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="stat-icon text-2xl"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <p class="text-sm">Pending Dues</p>
                                <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format(max(0, $pending_dues), 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-md font-semibold mb-2 text-orange-600">Society Payments (LKR <?php echo number_format($total_society, 2); ?>)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full table-hover">
                                <thead>
                                <tr class="border-b dark:border-gray-600">
                                    <th class="py-2 px-4 text-left">Date</th>
                                    <th class="py-2 px-4 text-left">Amount</th>
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
                                    <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No payments issued.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-md font-semibold mb-2 text-orange-600">Membership Fees (LKR <?php echo number_format($total_membership, 2); ?>)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full table-hover">
                                <thead>
                                <tr class="border-b dark:border-gray-600">
                                    <th class="py-2 px-4 text-left">Date</th>
                                    <th class="py-2 px-4 text-left">Amount</th>
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
                                    <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No payments recorded.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-md font-semibold mb-2 text-orange-600">Loan Settlements (LKR <?php echo number_format($total_loan_settlement, 2); ?>)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full table-hover">
                                <thead>
                                <tr class="border-b dark:border-gray-600">
                                    <th class="py-2 px-4 text-left">Date</th>
                                    <th class="py-2 px-4 text-left">Amount</th>
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
                                    <tr><td colspan="5" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No payments recorded.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Incidents -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="incidents">Incidents <i class="fas fa-chevron-down ml-2"></i></div>
                <div class="accordion-content" id="incidents">
                    <div class="overflow-x-auto">
                        <table class="w-full table-hover">
                            <thead>
                            <tr class="border-b dark:border-gray-600">
                                <th class="py-2 px-4 text-left">ID</th>
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
            </div>

            <!-- Loans -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="loans">Loans Taken <i class="fas fa-chevron-down ml-2"></i></div>
                <div class="accordion-content" id="loans">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div class="flex items-center space-x-2">
                            <div class="stat-icon text-2xl"><i class="fas fa-hand-holding-usd"></i></div>
                            <div>
                                <p class="text-sm">Total Amount</p>
                                <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="stat-icon text-2xl"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <p class="text-sm">Total Dues</p>
                                <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_dues, 2); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="stat-icon text-2xl"><i class="fas fa-percentage"></i></div>
                            <div>
                                <p class="text-sm">Total Interest</p>
                                <p class="text-lg font-bold text-orange-600">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full table-hover">
                            <thead>
                            <tr class="border-b dark:border-gray-600">
                                <th class="py-2 px-4 text-left">Amount</th>
                                <th class="py-2 px-4 text-left">Rate (%)</th>
                                <th class="py-2 px-4 text-left">Duration</th>
                                <th class="py-2 px-4 text-left">Monthly</th>
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
            </div>

            <!-- Documents -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="documents">Documents <i class="fas fa-chevron-down ml-2"></i></div>
                <div class="accordion-content" id="documents">
                    <div class="overflow-x-auto">
                        <table class="w-full table-hover">
                            <thead>
                            <tr class="border-b dark:border-gray-600">
                                <th class="py-2 px-4 text-left">Type</th>
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
            </div>

        <?php else: ?>
            <!-- Members Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="w-full table-hover">
                        <thead>
                        <tr class="border-b dark:border-gray-600">
                            <th class="py-2 px-4 text-left">Member ID</th>
                            <th class="py-2 px-4 text-left">Name</th>
                            <th class="py-2 px-4 text-left">Contact</th>
                            <th class="py-2 px-4 text-left">Type</th>
                            <th class="py-2 px-4 text-left">Payment</th>
                            <th class="py-2 px-4 text-left">Status</th>
                            <th class="py-2 px-4 text-left">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="members-table">
                        <?php foreach ($members as $m): ?>
                            <tr class="border-b dark:border-gray-600">
                                <td class="py-2 px-4"><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" class="member-link"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($m['full_name']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($m['membership_type']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($m['payment_status']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($m['member_status']); ?></td>
                                <td class="py-2 px-4 flex space-x-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($m)); ?>)" class="text-white px-2 py-1 btn-admin"><i class="fas fa-edit"></i></button>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                        <button type="submit" name="delete" class="text-white px-2 py-1 btn-delete"><i class="fas fa-trash"></i></button>
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
            </div>
        <?php endif; ?>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </main>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-40">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-md">
        <h2 class="text-xl font-semibold mb-4 text-orange-600">Edit Member</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Full Name</label>
                <input type="text" name="full_name" id="edit-full_name" class="input-field w-full px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Contact Number</label>
                <input type="text" name="contact_number" id="edit-contact_number" class="input-field w-full px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Membership Type</label>
                <select name="membership_type" id="edit-membership_type" class="input-field w-full px-3 py-2">
                    <option value="Individual">Individual</option>
                    <option value="Family">Family</option>
                    <option value="Senior Citizen">Senior Citizen</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Payment Status</label>
                <select name="payment_status" id="edit-payment_status" class="input-field w-full px-3 py-2">
                    <option value="Active">Active</option>
                    <option value="Pending">Pending</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Member Status</label>
                <select name="member_status" id="edit-member_status" class="input-field w-full px-3 py-2">
                    <option value="Active">Active</option>
                    <option value="Deceased">Deceased</option>
                    <option value="Resigned">Resigned</option>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeEditModal()" class="text-gray-600 px-4 py-2 rounded-lg">Cancel</button>
                <button type="submit" name="update" class="text-white px-4 py-2 btn-admin">Save</button>
            </div>
        </form>
    </div>
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

    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => e.preventDefault());
    }

    function openEditModal(member) {
        document.getElementById('edit-id').value = member.id;
        document.getElementById('edit-full_name').value = member.full_name;
        document.getElementById('edit-contact_number').value = member.contact_number;
        document.getElementById('edit-membership_type').value = member.membership_type;
        document.getElementById('edit-payment_status').value = member.payment_status;
        document.getElementById('edit-member_status').value = member.member_status;
        document.getElementById('edit-modal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('edit-modal').classList.add('hidden');
    }

    const accordions = document.querySelectorAll('.accordion-header');
    accordions.forEach(header => {
        header.addEventListener('click', () => {
            const content = document.getElementById(header.getAttribute('data-target'));
            content.classList.toggle('active');
            const icon = header.querySelector('i');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        });
    });

    const searchInput = document.getElementById('search-input');
    const membersTable = document.getElementById('members-table');
    searchInput.addEventListener('input', () => {
        const search = searchInput.value.toLowerCase();
        const rows = membersTable.getElementsByTagName('tr');
        for (let row of rows) {
            const cells = row.getElementsByTagName('td');
            let match = false;
            for (let cell of cells) {
                if (cell.textContent.toLowerCase().includes(search)) {
                    match = true;
                    break;
                }
            }
            row.style.display = match ? '' : 'none';
        }
    });
</script>
</body>
</html>