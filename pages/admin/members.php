<?php
define('APP_START', true);
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../classes/Member.php';
require_once '../../classes/Family.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Incident.php';
require_once '../../classes/Loan.php';
require_once '../../classes/Document.php';
require_once '../../classes/Database.php'; // Assuming Database class exists

// Initialize classes
$member = new Member();
$family = new Family();
$payment = new Payment();
$incident = new Incident();
$loan = new Loan();
$document = new Document();

$error = '';
$success = '';

// Pagination setup
$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$members = $member->getAllMembers();
$total_members = count($members);

if ($search) {
    $members = array_filter($members, function ($m) use ($search) {
        $search = strtolower($search);
        return stripos(strtolower($m['member_id']), $search) !== false ||
            stripos(strtolower($m['full_name']), $search) !== false ||
            stripos(strtolower($m['nic_number'] ?? ''), $search) !== false ||
            stripos(strtolower($m['contact_number'] ?? ''), $search) !== false;
    });
}

// Apply pagination
$members = array_slice($members, $offset, $items_per_page);
$total_pages = ceil($total_members / $items_per_page);

// Detailed member view
$selected_member = null;
if (isset($_GET['member_id'])) {
    $selected_member_id = trim($_GET['member_id']);
    $selected_member = array_filter($member->getAllMembers(), fn($m) => $m['member_id'] === $selected_member_id);
    $selected_member = reset($selected_member);

    if ($selected_member) {
        $member_id = $selected_member['id'];
        $family_details = $family->getFamilyDetailsByMemberId($member_id);
        $all_payments = $payment->getPaymentsByMemberId($member_id);
        $incidents = $incident->getIncidentsByMemberId($member_id);
        $loans = $loan->getLoansByMemberId($member_id);
        $documents = $document->getDocumentsByMemberId($member_id);

        // Payment categorization
        $society_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Society Issued');
        $membership_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Membership Fee');
        $loan_settlements = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Loan Settlement');

        // Financial calculations
        $total_society = array_sum(array_column($society_payments, 'amount'));
        $total_membership = array_sum(array_column($membership_payments, 'amount'));
        $total_loan_settlement = array_sum(array_column($loan_settlements, 'amount'));
        $total_from_member = $total_membership + $total_loan_settlement;
        $total_from_society = $total_society;

        // Pending dues calculation (assuming monthly contribution)
        $join_date = new DateTime($selected_member['date_of_joining']);
        $current_date = new DateTime();
        $months_since_joining = $join_date->diff($current_date)->m + ($join_date->diff($current_date)->y * 12);
        $expected_contribution = $selected_member['contribution_amount'] * $months_since_joining;
        $pending_dues = max(0, $expected_contribution - $total_membership);

        // Loan calculations
        $total_loan_amount = array_sum(array_column($loans, 'amount'));
        $total_interest_amount = 0;
        $total_dues = 0;
        foreach ($loans as $l) {
            $interest = ($l['amount'] * $l['interest_rate'] / 100) * ($l['duration'] / 12);
            $total_interest_amount += $interest;
            $total_dues += max(0, ($l['monthly_payment'] * $l['duration']) - $total_loan_settlement);
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    if (isset($_POST['delete'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id && $member->deleteMember($id)) { // Assuming deleteMember exists in Member class
            $success = "Member deleted successfully.";
            header("Location: members.php"); // Refresh page
            exit;
        } else {
            $error = "Failed to delete member.";
        }
    } elseif (isset($_POST['update'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $full_name = trim($_POST['full_name']);
        $contact_number = trim($_POST['contact_number']);
        $membership_type = trim($_POST['membership_type']);
        $payment_status = trim($_POST['payment_status']);
        $member_status = trim($_POST['member_status']);

        // Validation
        if (empty($full_name) || empty($contact_number)) {
            $error = "Full name and contact number are required.";
        } elseif (!in_array($membership_type, ['Individual', 'Family', 'Senior Citizen'])) {
            $error = "Invalid membership type.";
        } elseif (!in_array($payment_status, ['Active', 'Pending', 'Inactive'])) {
            $error = "Invalid payment status.";
        } elseif (!in_array($member_status, ['Active', 'Deceased', 'Resigned'])) {
            $error = "Invalid member status.";
        } else {
            $stmt = $conn->prepare("UPDATE members SET full_name = ?, contact_number = ?, membership_type = ?, payment_status = ?, member_status = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $full_name, $contact_number, $membership_type, $payment_status, $member_status, $id);
            if ($stmt->execute()) {
                $success = "Member updated successfully.";
                header("Location: members.php"); // Refresh page
                exit;
            } else {
                $error = "Error updating member: " . $conn->error;
            }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --secondary: #1f2937;
            --bg-light: #f9fafb;
            --card-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --sidebar-width: 80px;
            --sidebar-expanded: 280px;
        }
        body {
            background-color: var(--bg-light);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
        }
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .btn {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-danger {
            background: #dc2626;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            position: fixed;
            top: 80px;
            left: 16px;
            height: calc(100vh - 96px);
            transition: width 0.3s ease;
            overflow: hidden;
            z-index: 20;
        }
        .sidebar.expanded {
            width: var(--sidebar-expanded);
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background: var(--primary);
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 16px;
        }
        .sidebar-item span {
            display: none;
        }
        .sidebar.expanded .sidebar-item span {
            display: inline;
        }
        .main-content {
            margin-left: calc(var(--sidebar-width) + 32px);
            transition: margin-left 0.3s ease;
        }
        .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 32px);
        }
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-custom th {
            background: var(--bg-light);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
        }
        .table-custom td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .table-custom tr:hover {
            background: #fff7ed;
        }
        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
            transition: border-color 0.2s ease;
        }
        .input-field:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }
        .accordion-header {
            background: var(--primary);
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
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 50;
        }
        .modal.active {
            display: flex;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
            }
            .sidebar.expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>

<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-4 flex justify-between items-center">
            <button id="toggle-sidebar" class="text-2xl text-gray-600"><i class="fas fa-bars"></i></button>
        </div>
        <aside class="sidebar">
            <ul class="mt-6">
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
    </aside>

    <!-- Main Content -->
    <main class="main-content flex-1 p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold" style="color: var(--primary);">Manage Members</h1>
            <p class="text-gray-600 mt-2">Efficiently oversee member records and details.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <form method="GET" class="mb-8">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by ID, Name, NIC, or Contact"
                       class="input-field pl-10">
                <button type="submit" class="btn absolute right-0 top-0 h-full px-4"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <?php if ($selected_member): ?>
            <!-- Detailed Member View -->
            <div class="card mb-6">
                <h2 class="text-xl font-semibold mb-4" style="color: var(--primary);">
                    Member: <?php echo htmlspecialchars($selected_member['member_id']); ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($selected_member['member_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_member['full_name']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($selected_member['date_of_birth']); ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($selected_member['gender']); ?></p>
                        <p><strong>NIC:</strong> <?php echo htmlspecialchars($selected_member['nic_number'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($selected_member['address']); ?></p>
                    </div>
                    <div class="space-y-3">
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($selected_member['contact_number']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_member['email'] ?? 'N/A'); ?></p>
                        <p><strong>Join Date:</strong> <?php echo htmlspecialchars($selected_member['date_of_joining']); ?></p>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars($selected_member['membership_type']); ?></p>
                        <p><strong>Contribution:</strong> LKR <?php echo number_format($selected_member['contribution_amount'], 2); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($selected_member['member_status']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Family Details -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="family-details">Family Details <i class="fas fa-chevron-down"></i></div>
                <div class="accordion-content" id="family-details">
                    <?php if ($family_details): ?>
                        <p><strong>Spouse:</strong> <?php echo htmlspecialchars($family_details['spouse_name'] ?? 'N/A'); ?></p>
                        <p><strong>Children:</strong> <?php echo htmlspecialchars($family_details['children_info'] ?? 'N/A'); ?></p>
                        <p><strong>Dependents:</strong> <?php echo htmlspecialchars($family_details['dependents_info'] ?? 'N/A'); ?></p>
                    <?php else: ?>
                        <p class="text-gray-500">No family details available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Financial Records -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="financial-records">Financial Records <i class="fas fa-chevron-down"></i></div>
                <div class="accordion-content" id="financial-records">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="text-center">
                            <i class="fas fa-arrow-up text-2xl" style="color: var(--primary);"></i>
                            <p class="text-sm text-gray-600">From Member</p>
                            <p class="text-lg font-semibold">LKR <?php echo number_format($total_from_member, 2); ?></p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-arrow-down text-2xl" style="color: var(--primary);"></i>
                            <p class="text-sm text-gray-600">From Society</p>
                            <p class="text-lg font-semibold">LKR <?php echo number_format($total_from_society, 2); ?></p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--primary);"></i>
                            <p class="text-sm text-gray-600">Pending Dues</p>
                            <p class="text-lg font-semibold">LKR <?php echo number_format($pending_dues, 2); ?></p>
                        </div>
                    </div>
                    <!-- Payment Tables -->
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--primary);">Society Payments (LKR <?php echo number_format($total_society, 2); ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="table-custom">
                                    <thead>
                                    <tr><th>Date</th><th>Amount</th><th>Mode</th><th>Receipt</th><th>Remarks</th></tr>
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
                                        <tr><td colspan="5" class="text-center text-gray-500">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--primary);">Membership Fees (LKR <?php echo number_format($total_membership, 2); ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="table-custom">
                                    <thead>
                                    <tr><th>Date</th><th>Amount</th><th>Mode</th><th>Receipt</th><th>Remarks</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($membership_payments as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                                            <td><?php echo number_format($p['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($membership_payments)): ?>
                                        <tr><td colspan="5" class="text-center text-gray-500">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--primary);">Loan Settlements (LKR <?php echo number_format($total_loan_settlement, 2); ?>)</h3>
                            <div class="overflow-x-auto">
                                <table class="table-custom">
                                    <thead>
                                    <tr><th>Date</th><th>Amount</th><th>Mode</th><th>Receipt</th><th>Remarks</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($loan_settlements as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                                            <td><?php echo number_format($p['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($loan_settlements)): ?>
                                        <tr><td colspan="5" class="text-center text-gray-500">No records</td></tr>
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
                <div class="accordion-header" data-target="incidents">Incidents <i class="fas fa-chevron-down"></i></div>
                <div class="accordion-content" id="incidents">
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                            <tr><th>ID</th><th>Type</th><th>Date & Time</th><th>Reporter</th><th>Remarks</th></tr>
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
                                <tr><td colspan="5" class="text-center text-gray-500">No records</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Loans -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="loans">Loans <i class="fas fa-chevron-down"></i></div>
                <div class="accordion-content" id="loans">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="text-center">
                            <i class="fas fa-hand-holding-usd text-2xl" style="color: var(--primary);"></i>
                            <p class="text-sm text-gray-600">Total Amount</p>
                            <p class="text-lg font-semibold">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--primary);"></i>
                            <p class="text-sm text-gray-600">Total Dues</p>
                            <p class="text-lg font-semibold">LKR <?php echo number_format($total_dues, 2); ?></p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-percentage text-2xl" style="color: var(--primary);"></i>
                            <p class="text-sm text-gray-600">Total Interest</p>
                            <p class="text-lg font-semibold">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                            <tr><th>Amount</th><th>Rate (%)</th><th>Duration</th><th>Monthly</th></tr>
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
                                <tr><td colspan="4" class="text-center text-gray-500">No records</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card mb-6">
                <div class="accordion-header" data-target="documents">Documents <i class="fas fa-chevron-down"></i></div>
                <div class="accordion-content" id="documents">
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                            <tr><th>Type</th><th>Notes</th><th>Upload Date</th></tr>
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
                                <tr><td colspan="3" class="text-center text-gray-500">No records</td></tr>
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
                        <tr><th>ID</th><th>Name</th><th>Contact</th><th>Type</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody id="members-table">
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" class="text-primary hover:underline"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                                <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                                <td><?php echo htmlspecialchars($m['payment_status']); ?></td>
                                <td><?php echo htmlspecialchars($m['member_status']); ?></td>
                                <td class="flex space-x-2">
                                    <button onclick='openEditModal(<?php echo json_encode($m); ?>)' class="btn px-3 py-1"><i class="fas fa-edit"></i></button>
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
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-4 flex justify-between items-center">
                        <p class="text-sm text-gray-600">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_members); ?> of <?php echo $total_members; ?></p>
                        <div class="space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="btn px-3 py-1">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="btn px-3 py-1">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="text-center mt-6"><a href="dashboard.php" class="text-primary hover:underline">Back to Dashboard</a></p>
    </main>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div class="card w-full max-w-lg">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--primary);">Edit Member</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" name="full_name" id="edit-full_name" class="input-field" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Contact Number</label>
                    <input type="text" name="contact_number" id="edit-contact_number" class="input-field" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Membership Type</label>
                    <select name="membership_type" id="edit-membership_type" class="input-field">
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Status</label>
                    <select name="payment_status" id="edit-payment_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Member Status</label>
                    <select name="member_status" id="edit-member_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditModal()" class="btn bg-gray-500">Cancel</button>
                <button type="submit" name="update" class="btn">Save</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggle-sidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('expanded');
    });

    function openEditModal(member) {
        document.getElementById('edit-id').value = member.id;
        document.getElementById('edit-full_name').value = member.full_name;
        document.getElementById('edit-contact_number').value = member.contact_number;
        document.getElementById('edit-membership_type').value = member.membership_type;
        document.getElementById('edit-payment_status').value = member.payment_status;
        document.getElementById('edit-member_status').value = member.member_status;
        document.getElementById('edit-modal').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('edit-modal').classList.remove('active');
    }

    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            const content = document.getElementById(header.dataset.target);
            content.classList.toggle('active');
            header.querySelector('i').classList.toggle('fa-chevron-down');
            header.querySelector('i').classList.toggle('fa-chevron-up');
        });
    });
</script>
</body>
</html>