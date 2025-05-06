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
        if ($id) {
            try {
                if ($member->deleteMember($id)) {
                    $success = "Member deleted successfully.";
                } else {
                    $error = "Failed to delete member. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Error deleting member: " . $e->getMessage();
            }
        } else {
            $error = "Invalid member ID.";
        }
    } elseif (isset($_POST['update'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $full_name = trim($_POST['full_name'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $membership_type = trim($_POST['membership_type'] ?? '');
        $payment_status = trim($_POST['payment_status'] ?? '');
        $member_status = trim($_POST['member_status'] ?? '');

        if (empty($full_name) || empty($contact_number)) {
            $error = "Full name and contact number are required.";
        } elseif (!in_array($membership_type, ['Individual', 'Family', 'Senior Citizen'])) {
            $error = "Invalid membership type.";
        } elseif (!in_array($payment_status, ['Active', 'Pending', 'Inactive'])) {
            $error = "Invalid payment status.";
        } elseif (!in_array($member_status, ['Active', 'Deceased', 'Resigned'])) {
            $error = "Invalid member status.";
        } else {
            try {
<<<<<<< HEAD
                $data = [
                    'full_name' => $full_name,
                    'contact_number' => $contact_number,
                    'membership_type' => $membership_type,
                    'payment_status' => $payment_status,
                    'member_status' => $member_status
                ];
                
                if ($member->updateMember($id, $data)) {
                    $success = "Member updated successfully.";
                } else {
                    $error = "Failed to update member. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Error updating member: " . $e->getMessage();
=======
                $stmt = $conn->prepare("UPDATE members SET full_name = ?, contact_number = ?, membership_type = ?, payment_status = ?, member_status = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $full_name, $contact_number, $membership_type, $payment_status, $member_status, $id);
                if ($stmt->execute()) {
                    $success = "Member updated successfully.";
                } else {
                    $error = "Error updating member: " . $conn->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
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
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #F97316; /* Vibrant orange for primary actions */
            --primary-dark: #EA580C; /* Darker orange for hover */
            --primary-light: #FDBA74; /* Light orange for subtle accents */
            --secondary: #4B5563; /* Neutral gray for secondary elements */
            --background: #F7F7F7; /* Soft off-white background */
            --card-bg: #FFFFFF; /* Clean white for cards */
            --text-primary: #1F2937; /* Dark gray for primary text */
            --text-secondary: #6B7280; /* Lighter gray for secondary text */
            --error: #EF4444; /* Red for errors */
            --success: #22C55E; /* Green for success */
<<<<<<< HEAD
            --warning: #F59E0B;
=======
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            --shadow: 0 6px 20px rgba(0, 0, 0, 0.05); /* Softer shadow */
            --border: #E5E7EB; /* Light border color */
            --sidebar-width: 64px;
            --sidebar-expanded: 256px;
            --header-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .main-content {
            margin-left: calc(var(--sidebar-width) + 2rem);
            padding: calc(var(--header-height) + 2.5rem) 2.5rem 2.5rem;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--header-height));
        }

        .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 2rem);
        }

        /* Card */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2.5rem;
            animation: fadeIn 0.6s ease-out;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            background: var(--card-bg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th, .table td {
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            background: var(--card-bg);
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            cursor: pointer;
        }

        .table th.sortable:hover {
            background: #FFF7ED;
            color: var(--primary);
            transition: all 0.2s ease;
        }

        .table tbody tr {
            transition: background 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(249, 115, 22, 0.05);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.85rem 1.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .btn-danger {
            background: var(--error);
            color: #FFFFFF;
        }

        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-secondary {
            background: var(--secondary);
            color: #FFFFFF;
        }

        .btn-secondary:hover {
            background: #374151;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(75, 85, 99, 0.2);
        }

        .btn-icon {
            background: none;
            color: var(--text-secondary);
            padding: 0.6rem;
            border-radius: 8px;
            font-size: 1.1rem;
        }

        .btn-icon:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.4s ease, height 0.4s ease;
        }

        .btn:active::after {
            width: 120px;
            hight: 120px;
        }

        /* Input Fields */
        .input-field {
            width: 100%;
            padding: 0.85rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.9rem;
            background: #FFFFFF;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
            background: #FFF7ED;
        }

        .input-field.error {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        /* Search Bar */
        .search-container {
            position: relative;
            max-width: 600px;
        }

        .search-container .ri-search-line {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .search-container .ri-close-line {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.1rem;
            display: none;
            transition: color 0.2s ease;
        }

        .search-container .ri-close-line:hover {
            color: var(--primary);
        }

        .search-container .input-field:not(:placeholder-shown) ~ .ri-close-line {
            display: block;
        }

        .search-container .input-field {
            padding-left: 3rem;
        }

        /* Accordions */
        .accordion-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #FFFFFF;
            padding: 1.25rem 1.75rem;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 1.1rem;
            transition: border-radius 0.3s ease, transform 0.2s ease;
        }

        .accordion-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            padding: 0 1.75rem;
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 10px 10px;
            background: #FAFAFA;
            transition: all 0.4s ease;
        }

        .accordion-content.active {
            max-height: 2000px;
            padding: 1.75rem;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.4s ease-out;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 650px;
            box-shadow: var(--shadow);
            animation: slideIn 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        .modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.75rem;
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .modal-close:hover {
            color: var(--primary);
            transform: scale(1.1);
        }

        .delete-modal-content {
            max-width: 450px;
            text-align: center;
        }

        .delete-modal-content .ri-error-warning-fill {
            font-size: 3.5rem;
            color: var(--error);
            margin-bottom: 1.25rem;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            animation: fadeIn 0.6s ease-out;
        }

        .alert-success {
            background: #DCFCE7;
            color: var(--success);
            border-left: 5px solid var(--success);
        }

        .alert-error {
            background: #FEE2E2;
            color: var(--error);
            border-left: 5px solid var(--error);
        }

        /* Pagination */
        .pagination-btn {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .pagination-btn.disabled {
            background: #F3F4F6;
            color: #9CA3AF;
            cursor: not-allowed;
            border-color: #D1D5DB;
        }

        /* Form Elements */
        .error-text {
            display: none;
            color: var(--error);
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        .error-text.show {
            display: block;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
            display: block;
        }

        .form-group .required {
            color: var(--error);
            font-weight: 600;
        }

        /* Animations */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 1.25rem;
                padding: calc(var(--header-height) + 1.5rem) 1.5rem 1.5rem;
            }

            .sidebar.expanded ~ .main-content {
                margin-left: calc(var(--sidebar-expanded) + 1.25rem);
            }

            .table-container {
                font-size: 0.85rem;
            }

            .table th, .table td {
                padding: 0.75rem;
            }

            .modal-content {
                width: 95%;
                padding: 2rem;
            }

            .delete-modal-content {
                width: 90%;
            }

            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.85rem;
            }

            .accordion-header {
                font-size: 1rem;
                padding: 1rem 1.5rem;
            }

            .accordion-content.active {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .delete-modal-content .ri-error-warning-fill {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="flex justify-between items-center mb-10 animate-in">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold">Manage Members</h1>
                    <p class="text-sm text-[var(--text-secondary)] mt-2">Oversee member records and details efficiently.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error animate-in">
                    <i class="ri-error-warning-fill mr-2 text-lg"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success animate-in">
                    <i class="ri-checkbox-circle-fill mr-2 text-lg"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <form method="GET" class="mb-10 animate-in search-container">
                <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by ID, Name, NIC, or Contact" class="input-field">
                <i class="ri-search-line"></i>
                <i class="ri-close-line" id="clear-search"></i>
            </form>

            <?php if ($selected_member): ?>
                <!-- Detailed Member View -->
                <div class="card mb-8 animate-in">
                    <div class="flex justify-between items-center mb-8">
                        <h2 class="text-2xl font-semibold text-[var(--primary)]">
                            Member: <?php echo htmlspecialchars($selected_member['member_id']); ?>
                        </h2>
                        <a href="members.php" class="btn btn-danger"><i class="ri-arrow-left-line mr-2"></i> Back to List</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <p><strong class="font-semibold">ID:</strong> <?php echo htmlspecialchars($selected_member['member_id']); ?></p>
                            <p><strong class="font-semibold">Name:</strong> <?php echo htmlspecialchars($selected_member['full_name']); ?></p>
                            <p><strong class="font-semibold">Date of Birth:</strong> <?php echo htmlspecialchars($selected_member['date_of_birth']); ?></p>
                            <p><strong class="font-semibold">Gender:</strong> <?php echo htmlspecialchars($selected_member['gender']); ?></p>
                            <p><strong class="font-semibold">NIC:</strong> <?php echo htmlspecialchars($selected_member['nic_number'] ?? 'N/A'); ?></p>
                            <p><strong class="font-semibold">Address:</strong> <?php echo htmlspecialchars($selected_member['address']); ?></p>
                        </div>
                        <div class="space-y-4">
                            <p><strong class="font-semibold">Contact:</strong> <?php echo htmlspecialchars($selected_member['contact_number']); ?></p>
                            <p><strong class="font-semibold">Email:</strong> <?php echo htmlspecialchars($selected_member['email'] ?? 'N/A'); ?></p>
                            <p><strong class="font-semibold">Join Date:</strong> <?php echo htmlspecialchars($selected_member['date_of_joining']); ?></p>
                            <p><strong class="font-semibold">Type:</strong> <?php echo htmlspecialchars($selected_member['membership_type']); ?></p>
                            <p><strong class="font-semibold">Contribution:</strong> LKR <?php echo number_format($selected_member['contribution_amount'], 2); ?></p>
                            <p><strong class="font-semibold">Status:</strong> <?php echo htmlspecialchars($selected_member['member_status']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Accordion Sections -->
                <div class="space-y-8">
                    <div class="card animate-in">
                        <div class="accordion-header" data-target="family-details">Family Details <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="family-details">
                            <?php if ($family_details): ?>
                                <div class="space-y-4">
                                    <p><strong class="font-semibold">Spouse:</strong> <?php echo htmlspecialchars($family_details['spouse_name'] ?? 'N/A'); ?></p>
                                    <p><strong class="font-semibold">Children:</strong> <?php echo htmlspecialchars($family_details['children_info'] ?? 'N/A'); ?></p>
                                    <p><strong class="font-semibold">Dependents:</strong> <?php echo htmlspecialchars($family_details['dependents_info'] ?? 'N/A'); ?></p>
                                </div>
                            <?php else: ?>
                                <p class="text-[var(--text-secondary)]">No family details available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card animate-in">
                        <div class="accordion-header" data-target="financial-records">Financial Records <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="financial-records">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 mb-8">
                                <div class="text-center">
                                    <i class="ri-arrow-up-line text-2xl text-[var(--primary)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)] mt-2">From Member</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_from_member, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="ri-arrow-down-line text-2xl text-[var(--primary)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)] mt-2">From Society</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_from_society, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="ri-alert-line text-2xl text-[var(--primary)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)] mt-2">Pending Dues</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($pending_dues, 2); ?></p>
                                </div>
                            </div>
                            <div class="space-y-8">
                                <?php foreach ([
                                    ['title' => 'Society Payments', 'total' => $total_society, 'payments' => $society_payments],
                                    ['title' => 'Membership Fees', 'total' => $total_membership, 'payments' => $membership_payments],
                                    ['title' => 'Loan Settlements', 'total' => $total_loan_settlement, 'payments' => $loan_settlements]
                                ] as $section): ?>
                                    <div>
                                        <h3 class="text-lg font-semibold text-[var(--primary)] mb-4">
                                            <?php echo $section['title']; ?> (LKR <?php echo number_format($section['total'], 2); ?>)
                                        </h3>
                                        <div class="table-container">
                                            <table class="table">
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
                        <div class="accordion-header" data-target="incidents">Incidents <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="incidents">
                            <div class="table-container">
                                <table class="table">
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
                        <div class="accordion-header" data-target="loans">Loans <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="loans">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 mb-8">
                                <div class="text-center">
                                    <i class="ri-money-dollar-circle-line text-2xl text-[var(--primary)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)] mt-2">Total Amount</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="ri-alert-line text-2xl text-[var(--primary)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)] mt-2">Total Dues</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_dues, 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <i class="ri-percent-line text-2xl text-[var(--primary)]"></i>
                                    <p class="text-sm text-[var(--text-secondary)] mt-2">Total Interest</p>
                                    <p class="text-lg font-semibold">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
                                </div>
                            </div>
                            <div class="table-container">
                                <table class="table">
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
                        <div class="accordion-header" data-target="documents">Documents <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="documents">
                            <div class="table-container">
                                <table class="table">
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
                        <table class="table">
                            <thead>
                            <tr>
                                <th class="sortable">ID</th>
                                <th class="sortable">Name</th>
                                <th class="sortable">Contact</th>
                                <th class="sortable">Type</th>
                                <th class="sortable">Payment</th>
                                <th class="sortable">Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members_paginated as $m): ?>
                                <tr>
                                    <td><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" class="text-[var(--primary)] hover:underline font-medium"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                    <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                                    <td><?php echo htmlspecialchars($m['payment_status']); ?></td>
                                    <td><?php echo htmlspecialchars($m['member_status']); ?></td>
                                    <td class="flex space-x-3">
                                        <button class="btn-icon edit-btn" data-member='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>' title="Edit Member"><i class="ri-edit-line"></i></button>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $m['id']; ?>" title="Delete Member"><i class="ri-delete-bin-line text-[var(--error)]"></i></button>
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
                        <div class="mt-8 flex justify-between items-center">
                            <p class="text-sm text-[var(--text-secondary)]">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_members); ?> of <?php echo $total_members; ?>
                            </p>
                            <div class="flex space-x-3">
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
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2 class="text-2xl font-semibold text-[var(--primary)] mb-8">Edit Member</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="space-y-8">
                <div class="form-group">
                    <label for="edit-full_name" class="block">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" id="edit-full_name" class="input-field" required>
                    <span class="error-text" id="edit-full_name-error">Full name is required.</span>
                </div>
                <div class="form-group">
                    <label for="edit-contact_number" class="block">Contact Number <span class="required">*</span></label>
                    <input type="text" name="contact_number" id="edit-contact_number" class="input-field" required>
                    <span class="error-text" id="edit-contact_number-error">Contact number is required.</span>
                </div>
                <div class="form-group">
                    <label for="edit-membership_type" class="block">Membership Type</label>
                    <select name="membership_type" id="edit-membership_type" class="input-field">
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-payment_status" class="block">Payment Status</label>
                    <select name="payment_status" id="edit-payment_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-member_status" class="block">Member Status</label>
                    <select name="member_status" id="edit-member_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-6 mt-10">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line mr-2"></i> Cancel</button>
                <button type="submit" name="update" class="btn btn-primary"><i class="ri-save-line mr-2"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal-content delete-modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <i class="ri-error-warning-fill"></i>
        <h2 class="text-xl font-semibold text-[var(--text-primary)] mb-4">Confirm Deletion</h2>
        <p class="text-[var(--text-secondary)] mb-8">Are you sure you want to delete this member? This action cannot be undone.</p>
        <form method="POST" id="delete-form">
            <input type="hidden" name="id" id="delete-id">
            <div class="flex justify-center space-x-6">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line mr-2"></i> Cancel</button>
                <button type="submit" name="delete" class="btn btn-danger"><i class="ri-delete-bin-line mr-2"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
<<<<<<< HEAD
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const editModal = document.getElementById('edit-modal');
    const deleteModal = document.getElementById('delete-modal');
    const editButtons = document.querySelectorAll('.edit-btn');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const closeButtons = document.querySelectorAll('.modal-close');
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    const searchInput = document.getElementById('search-input');
    const clearSearch = document.getElementById('clear-search');
    const editForm = document.getElementById('edit-form');

    // Sidebar toggle
    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('expanded');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && sidebar.classList.contains('expanded') &&
                !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('expanded');
=======
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const editModal = document.getElementById('edit-modal');
        const deleteModal = document.getElementById('delete-modal');
        const editButtons = document.querySelectorAll('.edit-btn');
        const deleteButtons = document.querySelectorAll('.delete-btn');
        const closeButtons = document.querySelectorAll('.modal-close');
        const accordionHeaders = document.querySelectorAll('.accordion-header');
        const searchInput = document.getElementById('search-input');
        const clearSearch = document.getElementById('clear-search');
        const editForm = document.getElementById('edit-form');

        // Sidebar toggle
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

        // Edit modal
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                try {
                    const member = JSON.parse(button.getAttribute('data-member'));
                    document.getElementById('edit-id').value = member.id || '';
                    document.getElementById('edit-full_name').value = member.full_name || '';
                    document.getElementById('edit-contact_number').value = member.contact_number || '';
                    document.getElementById('edit-membership_type').value = member.membership_type || 'Individual';
                    document.getElementById('edit-payment_status').value = member.payment_status || 'Active';
                    document.getElementById('edit-member_status').value = member.member_status || 'Active';
                    editModal.style.display = 'flex';
                } catch (e) {
                    console.error('Failed to parse member data:', e);
                    alert('Error loading member data. Please try again.');
                }
            });
        });

        // Delete modal
        deleteButtons.forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                document.getElementById('delete-id').value = id;
                deleteModal.style.display = 'flex';
            });
        });

        // Close modals
        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                editModal.style.display = 'none';
                deleteModal.style.display = 'none';
                editForm.reset();
                clearErrors();
            });
        });

        // Click outside to close modals
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) {
                editModal.style.display = 'none';
                editForm.reset();
                clearErrors();
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
            }
        });
    }

<<<<<<< HEAD
    // Edit modal
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            try {
                const member = JSON.parse(button.getAttribute('data-member'));
                document.getElementById('edit-id').value = member.id || '';
                document.getElementById('edit-full_name').value = member.full_name || '';
                document.getElementById('edit-contact_number').value = member.contact_number || '';
                document.getElementById('edit-membership_type').value = member.membership_type || 'Individual';
                document.getElementById('edit-payment_status').value = member.payment_status || 'Active';
                document.getElementById('edit-member_status').value = member.member_status || 'Active';
                editModal.style.display = 'flex';
            } catch (e) {
                console.error('Failed to parse member data:', e);
                alert('Error loading member data. Please try again.');
            }
=======
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });

        // Form validation for edit
        editForm.addEventListener('submit', (e) => {
            let hasError = false;
            clearErrors();

            const fullName = document.getElementById('edit-full_name');
            const contactNumber = document.getElementById('edit-contact_number');

            if (!fullName.value.trim()) {
                showError('edit-full_name-error', fullName);
                hasError = true;
            }
            if (!contactNumber.value.trim()) {
                showError('edit-contact_number-error', contactNumber);
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });

        function showError(id, input) {
            const errorElement = document.getElementById(id);
            if (errorElement) {
                errorElement.classList.add('show');
                if (input) input.classList.add('error');
            }
        }

        function clearErrors() {
            document.querySelectorAll('.error-text').forEach(error => error.classList.remove('show'));
            document.querySelectorAll('.input-field').forEach(input => input.classList.remove('error'));
        }

        // Accordion
        accordionHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const content = document.getElementById(header.getAttribute('data-target'));
                const icon = header.querySelector('i');
                const isActive = content.classList.contains('active');

                document.querySelectorAll('.accordion-content').forEach(c => c.classList.remove('active'));
                document.querySelectorAll('.accordion-header i').forEach(i => i.classList.replace('ri-arrow-up-s-line', 'ri-arrow-down-s-line'));

                if (!isActive) {
                    content.classList.add('active');
                    icon.classList.replace('ri-arrow-down-s-line', 'ri-arrow-up-s-line');
                }
            });
>>>>>>> ac090992e1619ec8c9b073484cfcf95e22c4eba0
        });

        // Clear search
        if (clearSearch && searchInput) {
            clearSearch.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.focus();
                window.location.href = 'members.php';
            });
        }
    });

    // Delete modal
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            document.getElementById('delete-id').value = id;
            deleteModal.style.display = 'flex';
        });
    });

    // Close modals
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            editModal.style.display = 'none';
            deleteModal.style.display = 'none';
            editForm.reset();
            clearErrors();
        });
    });

    // Click outside to close modals
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            editModal.style.display = 'none';
            editForm.reset();
            clearErrors();
        }
    });

    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            deleteModal.style.display = 'none';
        }
    });

    // Form validation for edit
    editForm.addEventListener('submit', (e) => {
        let hasError = false;
        clearErrors();

        const fullName = document.getElementById('edit-full_name');
        const contactNumber = document.getElementById('edit-contact_number');

        if (!fullName.value.trim()) {
            showError('edit-full_name-error', fullName);
            hasError = true;
        }
        if (!contactNumber.value.trim()) {
            showError('edit-contact_number-error', contactNumber);
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
        }
    });

    function showError(id, input) {
        const errorElement = document.getElementById(id);
        if (errorElement) {
            errorElement.classList.add('show');
            if (input) input.classList.add('error');
        }
    }

    function clearErrors() {
        document.querySelectorAll('.error-text').forEach(error => error.classList.remove('show'));
        document.querySelectorAll('.input-field').forEach(input => input.classList.remove('error'));
    }

    // Accordion
    accordionHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const content = document.getElementById(header.getAttribute('data-target'));
            const icon = header.querySelector('i');
            const isActive = content.classList.contains('active');

            document.querySelectorAll('.accordion-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.accordion-header i').forEach(i => i.classList.replace('ri-arrow-up-s-line', 'ri-arrow-down-s-line'));

            if (!isActive) {
                content.classList.add('active');
                icon.classList.replace('ri-arrow-down-s-line', 'ri-arrow-up-s-line');
            }
        });
    });

    // Clear search
    if (clearSearch && searchInput) {
        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.focus();
            window.location.href = 'members.php';
        });
    }
});
</script>
</body>
</html>