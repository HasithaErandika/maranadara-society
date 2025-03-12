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
require_once '../../classes/Database.php';

$member = new Member();
$family = new Family();
$payment = new Payment();
$incident = new Incident();
$loan = new Loan();
$document = new Document();

$error = $success = '';

$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

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

$members_paginated = array_slice($members, $offset, $items_per_page);
$total_pages = ceil($total_members / $items_per_page);

$selected_member = null;
if (isset($_GET['member_id'])) {
    $selected_member_id = trim($_GET['member_id']);
    $selected_member = array_filter($members, fn($m) => $m['member_id'] === $selected_member_id);
    $selected_member = reset($selected_member);

    if ($selected_member) {
        $member_id = $selected_member['id'];
        $family_details = $family->getFamilyDetailsByMemberId($member_id);
        $all_payments = $payment->getPaymentsByMemberId($member_id);
        $incidents = $incident->getIncidentsByMemberId($member_id);
        $loans = $loan->getLoansByMemberId($member_id);
        $documents = $document->getDocumentsByMemberId($member_id);

        $society_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Society Issued');
        $membership_payments = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Membership Fee');
        $loan_settlements = array_filter($all_payments, fn($p) => $p['payment_type'] === 'Loan Settlement');

        $total_society = array_sum(array_column($society_payments, 'amount'));
        $total_membership = array_sum(array_column($membership_payments, 'amount'));
        $total_loan_settlement = array_sum(array_column($loan_settlements, 'amount'));
        $total_from_member = $total_membership + $total_loan_settlement;
        $total_from_society = $total_society;

        $join_date = new DateTime($selected_member['date_of_joining']);
        $current_date = new DateTime();
        $months_since_joining = $join_date->diff($current_date)->m + ($join_date->diff($current_date)->y * 12);
        $expected_contribution = $selected_member['contribution_amount'] * $months_since_joining;
        $pending_dues = max(0, $expected_contribution - $total_membership);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    if (isset($_POST['delete'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id && $member->deleteMember($id)) {
            $success = "Member deleted successfully.";
            header("Location: members.php");
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
                header("Location: members.php");
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
            --primary-orange: #F97316;
            --orange-dark: #C2410C;
            --orange-light: #FED7AA;
            --gray-bg: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --sidebar-width: 64px;
            --sidebar-expanded: 240px;
        }

        body {
            background: var(--gray-bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            line-height: 1.6;
        }

        .main-content {
            margin-left: calc(var(--sidebar-width) + 32px);
            transition: margin-left 0.3s ease;
        }

        .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 32px);
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-orange);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: var(--orange-dark);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-icon {
            background: none;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-icon:hover {
            background: var(--orange-light);
            color: var(--primary-orange);
        }

        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            transition: border-color 0.2s ease;
            background: #fff;
        }

        .input-field:focus {
            border-color: var(--primary-orange);
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            background: var(--card-bg);
        }

        .table th, .table td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table thead th {
            background: var(--card-bg);
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: rgba(249, 115, 22, 0.05);
        }

        .accordion-header {
            background: var(--primary-orange);
            color: white;
            padding: 14px 20px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        .accordion-content {
            display: none;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            background: #fafafa;
        }

        .accordion-content.active {
            display: block;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow);
            position: relative;
            animation: modalIn 0.3s ease-out;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-close:hover {
            color: var(--primary-orange);
        }

        .pagination-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            background: #e5e7eb;
            color: var(--text-primary);
        }

        .pagination-btn:hover {
            background: var(--primary-orange);
            color: white;
        }

        .pagination-btn.disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 16px;
            }
            .sidebar.expanded ~ .main-content {
                margin-left: calc(var(--sidebar-expanded) + 16px);
            }
            .card {
                padding: 16px;
            }
            .table th, .table td {
                padding: 10px;
            }
            .modal-content {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen pt-20">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content p-6 flex-1">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-8 animate-in">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900">Manage Members</h1>
                    <p class="text-sm text-[var(--text-secondary)] mt-2">Oversee member records and details efficiently.</p>
                </div>
                <a href="add_member.php" class="btn-primary"><i class="fas fa-user-plus"></i> Add New Member</a>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center animate-in">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 flex items-center animate-in">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <form method="GET" class="mb-8 animate-in">
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-[var(--text-secondary)]"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by ID, Name, NIC, or Contact" class="input-field pl-12 pr-4 py-3">
                    <button type="submit" class="btn-primary absolute right-0 top-0 h-full px-4 rounded-l-none"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <?php if ($selected_member): ?>
                <!-- Detailed Member View -->
                <div class="card mb-6 animate-in">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-[var(--primary-orange)]">
                            Member: <?php echo htmlspecialchars($selected_member['member_id']); ?>
                        </h2>
                        <a href="members.php" class="btn-danger"><i class="fas fa-arrow-left"></i> Back to List</a>
                    </div>
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

                <!-- Accordion Sections -->
                <div class="space-y-6">
                    <div class="card animate-in">
                        <div class="accordion-header" data-target="family-details">Family Details <i class="fas fa-chevron-down"></i></div>
                        <div class="accordion-content" id="family-details">
                            <?php if ($family_details): ?>
                                <p><strong>Spouse:</strong> <?php echo htmlspecialchars($family_details['spouse_name'] ?? 'N/A'); ?></p>
                                <p><strong>Children:</strong> <?php echo htmlspecialchars($family_details['children_info'] ?? 'N/A'); ?></p>
                                <p><strong>Dependents:</strong> <?php echo htmlspecialchars($family_details['dependents_info'] ?? 'N/A'); ?></p>
                            <?php else: ?>
                                <p class="text-[var(--text-secondary)]">No family details available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card animate-in">
                        <div class="accordion-header" data-target="financial-records">Financial Records <i class="fas fa-chevron-down"></i></div>
                        <div class="accordion-content" id="financial-records">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                                <div class="text-center">
                                    <i class="fas fa-arrow-up text-2xl text-[var(--primary-orange)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)]">From Member</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_from_member, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-arrow-down text-2xl text-[var(--primary-orange)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)]">From Society</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_from_society, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-exclamation-triangle text-2xl text-[var(--primary-orange)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)]">Pending Dues</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($pending_dues, 2); ?></p>
                                </div>
                            </div>
                            <div class="space-y-6">
                                <?php foreach ([
                                                   ['title' => 'Society Payments', 'total' => $total_society, 'payments' => $society_payments],
                                                   ['title' => 'Membership Fees', 'total' => $total_membership, 'payments' => $membership_payments],
                                                   ['title' => 'Loan Settlements', 'total' => $total_loan_settlement, 'payments' => $loan_settlements]
                                               ] as $section): ?>
                                    <div>
                                        <h3 class="text-lg font-semibold text-[var(--primary-orange)] mb-4">
                                            <?php echo $section['title']; ?> (LKR <?php echo number_format($section['total'], 2); ?>)
                                        </h3>
                                        <div class="table-container">
                                            <table class="w-full table">
                                                <thead>
                                                <tr><th>Date</th><th>Amount</th><th>Mode</th><th>Receipt</th><th>Remarks</th></tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($section['payments'] as $p): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($p['date']); ?></td>
                                                        <td><?php echo number_format($p['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                                        <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($section['payments'])): ?>
                                                    <tr><td colspan="5" class="text-center text-[var(--text-secondary)]">No records</td></tr>
                                                <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card animate-in">
                        <div class="accordion-header" data-target="incidents">Incidents <i class="fas fa-chevron-down"></i></div>
                        <div class="accordion-content" id="incidents">
                            <div class="table-container">
                                <table class="w-full table">
                                    <thead>
                                    <tr><th>ID</th><th>Type</th><th>Date & Time</th><th>Remarks</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($incidents as $i): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                            <td><?php echo htmlspecialchars($i['incident_type']); ?></td>
                                            <td><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                                            <td><?php echo htmlspecialchars($i['remarks'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($incidents)): ?>
                                        <tr><td colspan="4" class="text-center text-[var(--text-secondary)]">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card animate-in">
                        <div class="accordion-header" data-target="loans">Loans <i class="fas fa-chevron-down"></i></div>
                        <div class="accordion-content" id="loans">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                                <div class="text-center">
                                    <i class="fas fa-hand-holding-usd text-2xl text-[var(--primary-orange)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)]">Total Amount</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-exclamation-triangle text-2xl text-[var(--primary-orange)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)]">Total Dues</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_dues, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-percentage text-2xl text-[var(--primary-orange)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)]">Total Interest</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                                </div>
                            </div>
                            <div class="table-container">
                                <table class="w-full table">
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
                                        <tr><td colspan="4" class="text-center text-[var(--text-secondary)]">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card animate-in">
                        <div class="accordion-header" data-target="documents">Documents <i class="fas fa-chevron-down"></i></div>
                        <div class="accordion-content" id="documents">
                            <div class="table-container">
                                <table class="w-full table">
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
                                        <tr><td colspan="3" class="text-center text-[var(--text-secondary)]">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Members List -->
                <div class="card animate-in">
                    <div class="table-container">
                        <table class="w-full table">
                            <thead>
                            <tr><th>ID</th><th>Name</th><th>Contact</th><th>Type</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members_paginated as $m): ?>
                                <tr>
                                    <td><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" class="text-[var(--primary-orange)] hover:underline"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                    <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                                    <td><?php echo htmlspecialchars($m['payment_status']); ?></td>
                                    <td><?php echo htmlspecialchars($m['member_status']); ?></td>
                                    <td class="flex space-x-2">
                                        <button class="btn-icon edit-btn" data-member='<?php echo json_encode($m); ?>' title="Edit Member"><i class="fas fa-edit"></i></button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                            <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                            <button type="submit" name="delete" class="btn-icon text-red-600" title="Delete Member"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($members_paginated)): ?>
                                <tr><td colspan="7" class="text-center text-[var(--text-secondary)]">No members found</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="mt-6 flex justify-between items-center">
                            <p class="text-sm text-[var(--text-secondary)]">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_members); ?> of <?php echo $total_members; ?>
                            </p>
                            <div class="flex space-x-2">
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"
                                   class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                                    <?php echo $page <= 1 ? 'onclick="return false;"' : ''; ?>>Previous</a>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"
                                   class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                                    <?php echo $page >= $total_pages ? 'onclick="return false;"' : ''; ?>>Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="text-center mt-6 animate-in"><a href="dashboard.php" class="text-[var(--primary-orange)] hover:underline">Back to Dashboard</a></p>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close">×</button>
        <h2 class="text-xl font-semibold text-[var(--primary-orange)] mb-6">Edit Member</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="space-y-6">
                <div>
                    <label for="edit-full_name" class="block text-sm font-medium mb-2">Full Name</label>
                    <input type="text" name="full_name" id="edit-full_name" class="input-field" required>
                </div>
                <div>
                    <label for="edit-contact_number" class="block text-sm font-medium mb-2">Contact Number</label>
                    <input type="text" name="contact_number" id="edit-contact_number" class="input-field" required>
                </div>
                <div>
                    <label for="edit-membership_type" class="block text-sm font-medium mb-2">Membership Type</label>
                    <select name="membership_type" id="edit-membership_type" class="input-field">
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div>
                    <label for="edit-payment_status" class="block text-sm font-medium mb-2">Payment Status</label>
                    <select name="payment_status" id="edit-payment_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="edit-member_status" class="block text-sm font-medium mb-2">Member Status</label>
                    <select name="member_status" id="edit-member_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" class="btn-danger modal-close"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" name="update" class="btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const modal = document.getElementById('edit-modal');
        const editButtons = document.querySelectorAll('.edit-btn');
        const closeButtons = document.querySelectorAll('.modal-close');
        const accordionHeaders = document.querySelectorAll('.accordion-header');

        if (sidebar && sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('expanded');
            });

            document.addEventListener('click', (e) => {
                if (window.innerWidth < 768 && sidebar.classList.contains('expanded') &&
                    !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('expanded');
                }
            });
        }

        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const member = JSON.parse(button.getAttribute('data-member'));
                document.getElementById('edit-id').value = member.id;
                document.getElementById('edit-full_name').value = member.full_name;
                document.getElementById('edit-contact_number').value = member.contact_number;
                document.getElementById('edit-membership_type').value = member.membership_type;
                document.getElementById('edit-payment_status').value = member.payment_status;
                document.getElementById('edit-member_status').value = member.member_status;
                modal.style.display = 'flex';
            });
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        accordionHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const content = document.getElementById(header.getAttribute('data-target'));
                content.classList.toggle('active');
                const icon = header.querySelector('i');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            });
        });
    });
</script>
</body>
</html>