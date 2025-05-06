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
    $selected_member = $member->getMemberByMemberId($selected_member_id);

    if ($selected_member) {
        $member_id = (int)$selected_member['id'];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f6f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section, .card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-section h2, .card h2 {
            font-size: 1.5rem;
            color: #e67e22;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .required-mark, .required {
            color: #e74c3c;
        }

        .input-field {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }

        .input-field:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 2px rgba(230, 126, 34, 0.2);
        }

        .input-field:invalid:not(:placeholder-shown) {
            border-color: #e74c3c;
        }

        .input-field.valid {
            border-color: #2ecc71;
        }

        .error-text {
            display: none;
            color: #e74c3c;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .error-text.show {
            display: block;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background-color: #e67e22;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #d35400;
        }

        .btn-secondary {
            background-color: #7f8c8d;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #6c7a89;
        }

        .btn-danger {
            color: #e74c3c;
            background: none;
        }

        .btn-danger:hover {
            color: #c0392b;
        }

        .btn-icon {
            background: none;
            color: #7f8c8d;
            padding: 8px;
        }

        .btn-icon:hover {
            color: #e67e22;
        }

        .family-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .family-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            position: relative;
            margin: 20px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .modal-overlay.show {
            display: block;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #7f8c8d;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #e67e22;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f9f9f9;
            font-weight: 500;
            font-size: 0.9rem;
            color: #333;
        }

        .table th.sortable:hover {
            background: #f5f5f5;
            color: #e67e22;
            cursor: pointer;
        }

        .table tbody tr:hover {
            background: #f9f9f9;
        }

        .search-container {
            position: relative;
            max-width: 400px;
            margin-bottom: 20px;
        }

        .search-container .ri-search-line {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .search-container .ri-close-line {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            cursor: pointer;
        }

        .search-container .ri-close-line:hover {
            color: #e67e22;
        }

        .search-container .input-field {
            padding-left: 30px;
            padding-right: 30px;
        }

        .accordion-header {
            background: #e67e22;
            color: #fff;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }

        .accordion-header:hover {
            background: #d35400;
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            padding: 0;
            background: #fff;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }

        .accordion-content.active {
            max-height: 2000px;
            padding: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #e6fffa;
            color: #2ecc71;
        }

        .alert-error {
            background: #fff5f5;
            color: #e74c3c;
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #333;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #e67e22;
            color: #fff;
            border-color: #e67e22;
        }

        .pagination-btn.disabled {
            background: #f5f5f5;
            color: #ccc;
            cursor: not-allowed;
        }

        .flex {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .main {
            flex: 1;
            padding: 20px;
            margin-left: 240px;
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 15px;
            }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-slide-in {
            animation: slideIn 0.5s ease-out;
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main">
        <div class="container">
            <div class="flex justify-between items-center mb-6 animate-slide-in">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 700;">Manage Members</h1>
                    <p style="font-size: 0.9rem; color: #7f8c8d;">View and manage member records efficiently.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error animate-slide-in">
                    <i class="ri-error-warning-fill"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success animate-slide-in">
                    <i class="ri-checkbox-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <form method="GET" class="search-container animate-slide-in">
                <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by ID, Name, NIC, or Contact" class="input-field">
                <i class="ri-search-line"></i>
                <i class="ri-close-line" id="clear-search"></i>
            </form>

            <?php if ($selected_member): ?>
                <!-- Detailed Member View -->
                <div class="card animate-slide-in">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <div style="background: #e67e22; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="ri-user-line" style="color: white; font-size: 24px;"></i>
                            </div>
                            <div>
                                <h2 style="font-size: 1.5rem; color: #e67e22; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($selected_member['full_name']); ?>
                                </h2>
                                <p style="color: #7f8c8d; font-size: 0.9rem;">Member ID: <?php echo htmlspecialchars($selected_member['member_id']); ?></p>
                            </div>
                        </div>
                        <a href="members.php" class="btn btn-danger"><i class="ri-arrow-left-line"></i> Back to List</a>
                    </div>

                    <div class="grid">
                        <!-- Personal Information -->
                        <div class="form-section">
                            <h2><i class="ri-user-settings-line"></i> Personal Information</h2>
                            <div style="display: grid; gap: 12px;">
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-calendar-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Date of Birth</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['date_of_birth']); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-user-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Gender</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['gender']); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-id-card-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">NIC</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['nic_number'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-map-pin-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Address</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['address']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="form-section">
                            <h2><i class="ri-contacts-line"></i> Contact Information</h2>
                            <div style="display: grid; gap: 12px;">
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-phone-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Contact Number</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['contact_number']); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-mail-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Email</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['email'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Membership Information -->
                        <div class="form-section">
                            <h2><i class="ri-group-line"></i> Membership Information</h2>
                            <div style="display: grid; gap: 12px;">
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-calendar-event-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Join Date</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['date_of_joining']); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-user-settings-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Membership Type</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['membership_type']); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-money-dollar-circle-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Contribution</span>
                                        <p style="color: #333; margin: 0;">LKR <?php echo number_format($selected_member['contribution_amount'], 2); ?></p>
                                    </div>
                                </div>
                                <div class="flex" style="gap: 12px;">
                                    <i class="ri-checkbox-circle-line" style="color: #e67e22;"></i>
                                    <div>
                                        <span style="color: #7f8c8d; font-size: 0.9rem;">Status</span>
                                        <p style="color: #333; margin: 0;"><?php echo htmlspecialchars($selected_member['member_status']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accordion Sections -->
                <div>
                    <div class="card animate-slide-in">
                        <div class="accordion-header" data-target="family-details">Family Details <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="family-details">
                            <?php if ($family_details): ?>
                                <div class="family-details-container" style="display: grid; gap: 20px; margin: 20px 0;">
                                    <!-- Spouse Section -->
                                    <?php if ($family_details['spouse_name']): ?>
                                        <div class="family-section">
                                            <h3 style="color: #e67e22; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                                <i class="ri-user-heart-line"></i> Spouse
                                            </h3>
                                            <p style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($family_details['spouse_name']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Children Section -->
                                    <?php if ($family_details['children_info']): ?>
                                        <div class="family-section">
                                            <h3 style="color: #e67e22; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                                <i class="ri-parent-line"></i> Children
                                            </h3>
                                            <div style="display: grid; gap: 12px;">
                                                <?php 
                                                $children = explode(', ', $family_details['children_info']);
                                                foreach ($children as $child): 
                                                ?>
                                                    <div style="background: #f9f9f9; padding: 12px; border-radius: 4px; display: flex; align-items: center; gap: 12px;">
                                                        <i class="ri-user-smile-line" style="color: #e67e22; font-size: 1.2rem;"></i>
                                                        <span style="color: #333;"><?php echo htmlspecialchars($child); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Dependents Section -->
                                    <?php if ($family_details['dependents_info']): ?>
                                        <div class="family-section">
                                            <h3 style="color: #e67e22; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                                <i class="ri-group-line"></i> Dependents
                                            </h3>
                                            <div style="display: grid; gap: 12px;">
                                                <?php 
                                                $dependents = explode(', ', $family_details['dependents_info']);
                                                foreach ($dependents as $dependent): 
                                                ?>
                                                    <div style="background: #f9f9f9; padding: 12px; border-radius: 4px; display: flex; align-items: center; gap: 12px;">
                                                        <i class="ri-user-line" style="color: #e67e22; font-size: 1.2rem;"></i>
                                                        <span style="color: #333;"><?php echo htmlspecialchars($dependent); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No family details available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card animate-slide-in">
                        <div class="accordion-header" data-target="financial-records">Financial Records <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="financial-records">
                            <div class="grid" style="margin-bottom: 20px;">
                                <div style="text-align: center;">
                                    <i class="ri-arrow-up-line" style="font-size: 1.5rem; color: #e67e22;"></i>
                                    <p style="font-size: 0.9rem; color: #7f8c8d;">From Member</p>
                                    <p style="font-weight: 600;">LKR <?php echo number_format($total_from_member, 2); ?></p>
                                </div>
                                <div style="text-align: center;">
                                    <i class="ri-arrow-down-line" style="font-size: 1.5rem; color: #e67e22;"></i>
                                    <p style="font-size: 0.9rem; color: #7f8c8d;">From Society</p>
                                    <p style="font-weight: 600;">LKR <?php echo number_format($total_from_society, 2); ?></p>
                                </div>
                                <div style="text-align: center;">
                                    <i class="ri-alert-line" style="font-size: 1.5rem; color: #e67e22;"></i>
                                    <p style="font-size: 0.9rem; color: #7f8c8d;">Pending Dues</p>
                                    <p style="font-weight: 600;">LKR <?php echo number_format($pending_dues, 2); ?></p>
                                </div>
                            </div>
                            <div>
                                <?php foreach ([
                                    ['title' => 'Society Payments', 'total' => $total_society, 'payments' => $society_payments],
                                    ['title' => 'Membership Fees', 'total' => $total_membership, 'payments' => $membership_payments],
                                    ['title' => 'Loan Settlements', 'total' => $total_loan_settlement, 'payments' => $loan_settlements]
                                ] as $section): ?>
                                    <div style="margin-bottom: 20px;">
                                        <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 10px;">
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
                                                    <tr><td colspan="5" style="text-align: center; color: #7f8c8d;">No records</td></tr>
                                                <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card animate-slide-in">
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
                                        <tr><td colspan="4" style="text-align: center; color: #7f8c8d;">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card animate-slide-in">
                        <div class="accordion-header" data-target="loans">Loans <i class="ri-arrow-down-s-line"></i></div>
                        <div class="accordion-content" id="loans">
                            <div class="grid" style="margin-bottom: 20px;">
                                <div style="text-align: center;">
                                    <i class="ri-money-dollar-circle-line" style="font-size: 1.5rem; color: #e67e22;"></i>
                                    <p style="font-size: 0.9rem; color: #7f8c8d;">Total Amount</p>
                                    <p style="font-weight: 600;">LKR <?php echo number_format($total_loan_amount, 2); ?></p>
                                </div>
                                <div style="text-align: center;">
                                    <i class="ri-alert-line" style="font-size: 1.5rem; color: #e67e22;"></i>
                                    <p style="font-size: 0.9rem; color: #7f8c8d;">Total Dues</p>
                                    <p style="font-weight: 600;">LKR <?php echo number_format($total_dues, 2); ?></p>
                                </div>
                                <div style="text-align: center;">
                                    <i class="ri-percent-line" style="font-size: 1.5rem; color: #e67e22;"></i>
                                    <p style="font-size: 0.9rem; color: #7f8c8d;">Total Interest</p>
                                    <p style="font-weight: 600;">LKR <?php echo number_format($total_interest_amount, 2); ?></p>
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
                                        <tr><td colspan="4" style="text-align: center; color: #7f8c8d;">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card animate-slide-in">
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
                                        <tr><td colspan="3" style="text-align: center; color: #7f8c8d;">No records</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Members List -->
                <div class="card animate-slide-in">
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
                                    <td><a href="?member_id=<?php echo htmlspecialchars($m['member_id']); ?>" style="color: #e67e22;"><?php echo htmlspecialchars($m['member_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['contact_number']); ?></td>
                                    <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                                    <td><?php echo htmlspecialchars($m['payment_status']); ?></td>
                                    <td><?php echo htmlspecialchars($m['member_status']); ?></td>
                                    <td class="flex">
                                        <button class="btn-icon edit-btn" data-member='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>' title="Edit Member"><i class="ri-edit-line"></i></button>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $m['id']; ?>" title="Delete Member"><i class="ri-delete-bin-line" style="color: #e74c3c;"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($members_paginated)): ?>
                                <tr><td colspan="7" style="text-align: center; color: #7f8c8d;">No members found</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <p style="font-size: 0.9rem; color: #7f8c8d;">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_members); ?> of <?php echo $total_members; ?>
                            </p>
                            <div class="flex" style="gap: 10px;">
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

<!-- Modal Overlay -->
<div class="modal-overlay"></div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close"><i class="ri-close-line"></i></button>
        <h2 style="font-size: 1.5rem; color: #e67e22; margin-bottom: 20px;">Edit Member</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="grid">
                <div class="form-group">
                    <label for="edit-full_name" class="form-label">Full Name <span class="required-mark">*</span></label>
                    <input type="text" name="full_name" id="edit-full_name" class="input-field" required>
                    <span class="error-text" id="edit-full_name-error">Full name is required.</span>
                </div>
                <div class="form-group">
                    <label for="edit-contact_number" class="form-label">Contact Number <span class="required-mark">*</span></label>
                    <input type="text" name="contact_number" id="edit-contact_number" class="input-field" required>
                    <span class="error-text" id="edit-contact_number-error">Contact number is required.</span>
                </div>
                <div class="form-group">
                    <label for="edit-membership_type" class="form-label">Membership Type</label>
                    <select name="membership_type" id="edit-membership_type" class="input-field">
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-payment_status" class="form-label">Payment Status</label>
                    <select name="payment_status" id="edit-payment_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-member_status" class="form-label">Member Status</label>
                    <select name="member_status" id="edit-member_status" class="input-field">
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>
            <div class="flex" style="justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                <button type="submit" name="update" class="btn btn-primary"><i class="ri-save-line"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close"><i class="ri-close-line"></i></button>
        <div style="text-align: center;">
            <i class="ri-error-warning-fill" style="font-size: 3rem; color: #e74c3c; margin-bottom: 20px;"></i>
            <h3 style="font-size: 1.5rem; font-weight: 700;">Confirm Deletion</h3>
            <p style="color: #7f8c8d; margin-top: 10px;">Are you sure you want to delete this member? This action cannot be undone.</p>
            <form method="POST" id="delete-form">
                <input type="hidden" name="id" id="delete-id">
                <div class="flex" style="justify-content: center; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                    <button type="submit" name="delete" class="btn btn-primary"><i class="ri-delete-bin-line"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const editModal = document.getElementById('edit-modal');
    const deleteModal = document.getElementById('delete-modal');
    const modalOverlay = document.querySelector('.modal-overlay');
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
            document.body.classList.toggle('sidebar-expanded');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && sidebar.classList.contains('expanded') &&
                !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('expanded');
                document.body.classList.remove('sidebar-expanded');
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
                editModal.classList.add('show');
                modalOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
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
            deleteModal.classList.add('show');
            modalOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modals
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            editModal.classList.remove('show');
            deleteModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
            editForm.reset();
            clearErrors();
        });
    });

    // Click outside to close modals
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            editModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
            editForm.reset();
            clearErrors();
        }
    });

    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            deleteModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
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