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
            --primary-color: #f97316;
            --primary-hover: #ea580c;
            --secondary-color: #1f2937;
            --bg-light: #f9fafb;
            --bg-dark: #111827;
            --card-light: #ffffff;
            --card-dark: #1f2937;
            --text-light: #374151;
            --text-dark: #f3f4f6;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --sidebar-width: 72px;
            --sidebar-expanded: 256px;
        }

        [data-theme="dark"] {
            --primary-color: #fb923c;
            --primary-hover: #f97316;
            --secondary-color: #f3f4f6;
            --bg-light: #111827;
            --bg-dark: #1f2937;
            --card-light: #1f2937;
            --card-dark: #374151;
            --text-light: #f3f4f6;
            --text-dark: #9ca3af;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .card {
            background-color: var(--card-light);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.12);
        }

        .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: #dc2626;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
        }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-light);
            border-radius: 12px;
            box-shadow: var(--shadow);
            position: fixed;
            top: 80px;
            left: 16px;
            height: calc(100vh - 96px);
            transition: width 0.3s ease;
            overflow: hidden;
            z-index: 1000;
        }

        .sidebar:hover, .sidebar-expanded {
            width: var(--sidebar-expanded);
        }

        .sidebar-item {
            padding: 1rem;
            color: var(--text-light);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .sidebar-item:hover, .sidebar-item.active {
            background-color: var(--primary-color);
            color: white;
        }

        .sidebar-item i {
            width: 24px;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        .sidebar-item span {
            display: none;
            font-weight: 500;
        }

        .sidebar:hover .sidebar-item span, .sidebar-expanded .sidebar-item span {
            display: inline;
        }

        .main-content {
            margin-left: calc(var(--sidebar-width) + 24px);
            transition: margin-left 0.3s ease;
        }

        .sidebar:hover ~ .main-content, .sidebar-expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 24px);
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom th {
            background-color: var(--bg-light);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
        }

        .table-custom td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-custom tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .table-custom tr:hover {
            background-color: #fef2e8;
        }

        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
            outline: none;
        }

        .accordion-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .accordion-content {
            display: none;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }

        .accordion-content.active {
            display: block;
        }

        .stat-card {
            background-color: var(--card-light);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
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
                margin-left: calc(var(--sidebar-width) + 24px);
            }
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="fixed top-0 w-full bg-white shadow-lg z-50 dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-500 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i> Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:block">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button id="sidebar-toggle" class="md:hidden text-orange-500 text-2xl">
                <i class="fas fa-bars"></i>
            </button>
            <a href="../login.php?logout=1" class="btn">Logout</a>
        </div>
    </div>
</nav>

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

    <!-- Main Content -->
    <main class="main-content flex-1 p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-orange-500">Manage Members</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">View and manage member records efficiently</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <form method="GET" class="mb-8 relative">
            <div class="flex items-center">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by ID, Name, NIC, or Contact"
                       class="input-field w-full pl-10 pr-4 py-3 rounded-lg">
                <button type="submit" class="btn ml-2"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <?php if (isset($_GET['member_id']) && $selected_member): ?>
            <!-- Detailed Member View -->
            <div class="card mb-6">
                <h2 class="text-2xl font-semibold mb-4 text-orange-500">
                    Member Details: <?php echo htmlspecialchars($member_details['member_id']); ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($member_details['full_name']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($member_details['date_of_birth']); ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($member_details['gender']); ?></p>
                        <p><strong>NIC:</strong> <?php echo htmlspecialchars($member_details['nic_number']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($member_details['address']); ?></p>
                    </div>
                    <div class="space-y-2">
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($member_details['contact_number']); ?></p>
                        <p><strong>Email:</strong> <?php echo $member_details['email'] ? htmlspecialchars($member_details['email']) : 'N/A'; ?></p>
                        <p><strong>Join Date:</strong> <?php echo htmlspecialchars($member_details['date_of_joining']); ?></p>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars($member_details['membership_type']); ?></p>
                        <p><strong>Contribution:</strong> LKR <?php echo number_format($member_details['contribution_amount'], 2); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($member_details['member_status']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Family Details -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="family-details">
                    Family Details <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content" id="family-details">
                    <?php if ($family_details): ?>
                        <div class="space-y-2">
                            <p><strong>Spouse:</strong> <?php echo $family_details['spouse_name'] ?: 'N/A'; ?></p>
                            <p><strong>Children:</strong> <?php echo $family_details['children_info'] ?: 'N/A'; ?></p>
                            <p><strong>Dependents:</strong> <?php echo $family_details['dependents_info'] ?: 'N/A'; ?></p>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">No family details available</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Financial Records -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="financial-records">
                    Financial Records <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content" id="financial-records">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="stat-card">
                            <i class="fas fa-arrow-up text-orange-500 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-600">From Member</p>
                            <p class="text-xl font-bold text-orange-500">LKR <?php echo number_format($total_from_member, 2); ?></p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-arrow-down text-orange-500 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-600">From Society</p>
                            <p class="text-xl font-bold text-orange-500">LKR <?php echo number_format($total_from_society, 2); ?></p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-600">Pending Dues</p>
                            <p class="text-xl font-bold text-orange-500">LKR <?php echo number_format(max(0, $pending_dues), 2); ?></p>
                        </div>
                    </div>

                    <!-- Payment Tables -->
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-orange-500 mb-2">Society Payments (LKR <?php echo number_format($total_society, 2); ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="table-custom">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Receipt</th>
                                        <th>Remarks</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($society_payments as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                                            <td><?php echo number_format($p['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($society_payments)): ?>
                                        <tr><td colspan="5" class="text-center text-gray-500">No payments recorded</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-orange-500 mb-2">Membership Fees (LKR <?php echo number_format($total_membership, 2); ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="table-custom">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Receipt</th>
                                        <th>Remarks</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($membership_fee_payments as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                                            <td><?php echo number_format($p['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($membership_fee_payments)): ?>
                                        <tr><td colspan="5" class="text-center text-gray-500">No payments recorded</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-orange-500 mb-2">Loan Settlements (LKR <?php echo number_format($total_loan_settlement, 2); ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="table-custom">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Receipt</th>
                                        <th>Remarks</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($loan_settlement_payments as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                                            <td><?php echo number_format($p['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($loan_settlement_payments)): ?>
                                        <tr><td colspan="5" class="text-center text-gray-500">No payments recorded</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Incidents -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="incidents">
                    Incidents <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content" id="incidents">
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Date & Time</th>
                                <th>Reporter</th>
                                <th>Remarks</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($incidents as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                    <td><?php echo htmlspecialchars($i['incident_type']); ?></td>
                                    <td><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                                    <td><?php echo htmlspecialchars($i['reporter_name']); ?></td>
                                    <td><?php echo htmlspecialchars($i['remarks'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($incidents)): ?>
                                <tr><td colspan="5" class="text-center text-gray-500">No incidents recorded</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Loans -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="loans">
                    Loans <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content" id="loans">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="stat-card">
                            <i class="fas fa-hand-holding-usd text-orange-500 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-600">Total Amount</p>
                            <p class="text-xl font-bold text-orange-500">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-600">Total Dues</p>
                            <p class="text-xl font-bold text-orange-500">LKR <?php echo number_format($total_dues, 2); ?></p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-percentage text-orange-500 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-600">Total Interest</p>
                            <p class="text-xl font-bold text-orange-500">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Rate (%)</th>
                                <th>Duration</th>
                                <th>Monthly</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($loans as $l): ?>
                                <tr>
                                    <td><?php echo number_format($l['amount'], 2); ?></td>
                                    <td><?php echo number_format($l['interest_rate'], 2); ?></td>
                                    <td><?php echo $l['duration']; ?> months</td>
                                    <td><?php echo number_format($l['monthly_payment'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($loans)): ?>
                                <tr><td colspan="4" class="text-center text-gray-500">No loans recorded</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="documents">
                    Documents <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content" id="documents">
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                            <tr>
                                <th>Type</th>
                                <th>Notes</th>
                                <th>Upload Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($d['notes'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($d['upload_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="3" class="text-center text-gray-500">No documents uploaded</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Members List -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="table-custom">
                        <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="members-table">
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" class="text-orange-500 hover:underline"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                                <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                                <td><?php echo htmlspecialchars($m['payment_status']); ?></td>
                                <td><?php echo htmlspecialchars($m['member_status']); ?></td>
                                <td class="flex space-x-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($m)); ?>)" class="btn px-3 py-1"><i class="fas fa-edit"></i></button>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger px-3 py-1"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($members)): ?>
                            <tr><td colspan="7" class="text-center text-gray-500">No members found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <p class="text-center mt-6"><a href="dashboard.php" class="text-orange-500 hover:underline">Back to Dashboard</a></p>
    </main>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="card w-full max-w-lg">
        <h2 class="text-2xl font-semibold mb-6 text-orange-500">Edit Member</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" name="full_name" id="edit-full_name" class="input-field w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Contact Number</label>
                    <input type="text" name="contact_number" id="edit-contact_number" class="input-field w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Membership Type</label>
                    <select name="membership_type" id="edit-membership_type" class="input-field w-full">
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Status</label>
                    <select name="payment_status" id="edit-payment_status" class="input-field w-full">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Member Status</label>
                    <select name="member_status" id="edit-member_status" class="input-field w-full">
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditModal()" class="btn bg-gray-500">Cancel</button>
                <button type="submit" name="update" class="btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<footer class="py-6 bg-white dark:bg-gray-900 mt-8">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>

<script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-expanded');
    });

    // Edit modal
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

    // Accordion
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            const content = document.getElementById(header.dataset.target);
            content.classList.toggle('active');
            const icon = header.querySelector('i');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        });
    });

    // Client-side search
    const searchInput = document.querySelector('input[name="search"]');
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

    // Dark mode toggle (optional)
    const toggleDarkMode = () => {
        document.documentElement.dataset.theme =
            document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    };
</script>
</body>
</html>