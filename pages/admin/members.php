<?php
define('APP_START', true);
session_start();

// Start output buffering to prevent unwanted output
ob_start();

// Disable displaying errors to avoid breaking JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log session state
error_log("members.php: Session: " . print_r($_SESSION, true));

// Check for headers already sent
if (headers_sent()) {
    error_log("members.php: Headers already sent, potential output buffering issue");
    exit;
}

try {
    // Redirect if not admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        error_log("members.php: Unauthorized access, redirecting to login.php");
        header("Location: ../login.php");
        exit;
    }

    require_once '../../includes/header.php';
    echo '<link rel="stylesheet" href="../../assets/css/memberManagement.css">';
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

    // Handle pagination and search
    $items_per_page = isset($_GET['items_per_page']) ? max(5, min(50, (int)$_GET['items_per_page'])) : 10;
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
        $total_members = count($members);
    }

    $members_paginated = array_slice($members, $offset, $items_per_page);
    $total_pages = ceil($total_members / $items_per_page);

    $selected_member = null;
    if (isset($_GET['member_id'])) {
        $selected_member_id = trim($_GET['member_id']);
        $selected_member = $member->getMemberByMemberId($selected_member_id);

        if ($selected_member) {
            $member_id = (int)$selected_member['id'];
            $family = $family->getFamilyDetails($member_id);
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
        try {
            // Handle member deletion
            if (isset($_POST['delete']) && isset($_POST['id'])) {
                $memberId = $_POST['id'];
                
                // Delete family details first
                $family->deleteFamilyDetails($memberId);
                
                // Delete the member
                $result = $member->deleteMember($memberId);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Member deleted successfully'
                    ]);
                } else {
                    throw new Exception('Failed to delete member');
                }
                exit;
            }

            // Handle member update
            if (isset($_POST['update']) && isset($_POST['id'])) {
                $memberId = $_POST['id'];
                $data = [
                    'full_name' => $_POST['full_name'] ?? '',
                    'contact_number' => $_POST['contact_number'] ?? '',
                    'membership_type' => $_POST['membership_type'] ?? 'Individual',
                    'payment_status' => $_POST['payment_status'] ?? 'Active',
                    'member_status' => $_POST['member_status'] ?? 'Active'
                ];
                $result = $member->updateMember($memberId, $data);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Member updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update member');
                }
                exit;
            }

            // Handle member details update
            if (isset($_POST['update_details']) && isset($_POST['id'])) {
                $memberId = $_POST['id'];
                $data = [
                    'full_name' => $_POST['full_name'] ?? '',
                    'date_of_birth' => $_POST['date_of_birth'] ?? '',
                    'gender' => $_POST['gender'] ?? 'Male',
                    'nic_number' => $_POST['nic_number'] ?? '',
                    'address' => $_POST['address'] ?? '',
                    'contact_number' => $_POST['contact_number'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'date_of_joining' => $_POST['date_of_joining'] ?? '',
                    'membership_type' => $_POST['membership_type'] ?? 'Individual',
                    'payment_status' => $_POST['payment_status'] ?? 'Active',
                    'member_status' => $_POST['member_status'] ?? 'Active'
                ];
                $result = $member->updateMember($memberId, $data);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Member details updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update member details');
                }
                exit;
            }

            // Handle family details update
            if (isset($_POST['update_family']) && isset($_POST['member_id'])) {
                $memberId = $_POST['member_id'];
                $spouse_data = null;
                if (!empty($_POST['spouse_name'])) {
                    $spouse_data = [
                        'name' => $_POST['spouse_name'],
                        'dob' => $_POST['spouse_dob'],
                        'gender' => $_POST['spouse_gender']
                    ];
                }

                $children_data = [];
                if (isset($_POST['children']) && is_array($_POST['children'])) {
                    foreach ($_POST['children'] as $child) {
                        if (!empty($child['name'])) {
                            $children_data[] = [
                                'name' => $child['name'],
                                'dob' => $child['dob'],
                                'gender' => $child['gender']
                            ];
                        }
                    }
                }

                $dependents_data = [];
                if (isset($_POST['dependents']) && is_array($_POST['dependents'])) {
                    foreach ($_POST['dependents'] as $dependent) {
                        if (!empty($dependent['name'])) {
                            $dependents_data[] = [
                                'name' => $dependent['name'],
                                'relationship' => $dependent['relationship'],
                                'dob' => $dependent['dob'],
                                'address' => $dependent['address']
                            ];
                        }
                    }
                }

                $result = $family->updateFamilyDetails($memberId, $spouse_data, $children_data, $dependents_data);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Family details updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update family details');
                }
                exit;
            }
        } catch (Exception $e) {
            error_log("members.php: POST error: " . $e->getMessage());
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            } else {
                $_SESSION['error'] = $e->getMessage();
                header('Location: members.php');
            }
            exit;
        }
    }

    // Clear output buffer
    ob_end_flush();
} catch (Exception $e) {
    error_log("members.php: Fatal error: " . $e->getMessage());
    header("Location: ../login.php");
    exit;
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
    <!-- External CSS -->
    <link rel="stylesheet" href="../../assets/css/memberManagement.css">
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main">
        <div class="container">
            <div class="flex justify-between items-center mb-6 animate-slide-in">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 700; color: var(--primary-orange);">
                        <?php echo $selected_member ? 'Member Details' : 'Manage Members'; ?>
                    </h1>
                    <?php if (!$selected_member): ?>
                        <p style="font-size: 0.95rem; color: var(--text-secondary);">View and manage member records efficiently.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Search Bar -->
            <?php if (!$selected_member): ?>
                <form method="GET" class="search-container animate-slide-in" id="search-form" role="search" aria-label="Search Members">
                    <i class="ri-search-line"></i>
                    <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search members..." class="input-field" aria-label="Search members">
                    <i class="ri-close-line" id="clear-search" role="button" aria-label="Clear search"></i>
                    <div class="search-loading" id="search-loading">
                        <div class="spinner"></div>
                    </div>
                    <select name="items_per_page" onchange="this.form.submit()" class="input-field" style="margin-left: 10px; width: auto;">
                        <option value="5" <?php echo $items_per_page == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $items_per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $items_per_page == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </form>
            <?php endif; ?>

            <?php if ($selected_member): ?>
                <!-- Detailed Member View -->
                <div class="card animate-slide-in">
                    <div class="member-header">
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
                        <div class="header-buttons">
                            <button class="btn btn-primary edit-details-btn" data-member='<?php echo htmlspecialchars(json_encode($selected_member), ENT_QUOTES); ?>' title="Edit Member Details" aria-label="Edit Member Details">
                                <i class="ri-edit-line"></i> Edit
                            </button>
                            <button class="btn btn-danger" onclick="window.location.href='members.php';">
                                <i class="ri-arrow-left-line"></i> Back to Members
                            </button>
                        </div>
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
                            <?php if ($family['spouse'] || !empty($family['children']) || !empty($family['dependents'])): ?>
                                <div class="family-details-container">
                                    <!-- Spouse Section -->
                                    <?php if (!empty($family['spouse'])): ?>
                                        <div class="family-section">
                                            <h3><i class="ri-user-heart-line"></i> Spouse</h3>
                                            <div class="family-info">
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($family['spouse']['name']); ?></p>
                                                <p><strong>Date of Birth:</strong> <?php echo date('d M Y', strtotime($family['spouse']['dob'])); ?></p>
                                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($family['spouse']['gender']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Children Section -->
                                    <?php if (!empty($family['children'])): ?>
                                        <div class="family-section">
                                            <h3><i class="ri-parent-line"></i> Children</h3>
                                            <div class="family-list">
                                                <?php foreach ($family['children'] as $child): ?>
                                                    <div class="family-item">
                                                        <i class="ri-user-smile-line"></i>
                                                        <div class="family-info">
                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($child['name']); ?></p>
                                                            <p><strong>Date of Birth:</strong> <?php echo date('d M Y', strtotime($child['dob'])); ?></p>
                                                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($child['gender']); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Dependents Section -->
                                    <?php if (!empty($family['dependents'])): ?>
                                        <div class="family-section">
                                            <h3><i class="ri-group-line"></i> Dependents</h3>
                                            <div class="family-list">
                                                <?php foreach ($family['dependents'] as $dependent): ?>
                                                    <div class="family-item">
                                                        <i class="ri-user-line"></i>
                                                        <div class="family-info">
                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($dependent['name']); ?></p>
                                                            <p><strong>Relationship:</strong> <?php echo htmlspecialchars($dependent['relationship']); ?></p>
                                                            <p><strong>Date of Birth:</strong> <?php echo date('d M Y', strtotime($dependent['dob'])); ?></p>
                                                            <p><strong>Address:</strong> <?php echo htmlspecialchars($dependent['address']); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="family-actions">
                                    <button class="btn btn-primary edit-family-btn" data-family='<?php echo htmlspecialchars(json_encode($family), ENT_QUOTES); ?>' data-member-id="<?php echo htmlspecialchars($selected_member['id']); ?>">
                                        <i class="ri-user-add-line"></i> Update Family Details
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="family-actions">
                                    <p style="color: #7f8c8d; margin-bottom: 10px;">No family details available.</p>
                                    <button class="btn btn-primary edit-family-btn" data-family='<?php echo htmlspecialchars(json_encode($family), ENT_QUOTES); ?>' data-member-id="<?php echo htmlspecialchars($selected_member['id']); ?>">
                                        <i class="ri-user-add-line"></i> Add Family Details
                                    </button>
                                </div>
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
                                        <button class="btn-icon edit-btn" data-member='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>' title="Edit Member" aria-label="Edit Member">
                                            <i class="ri-edit-line"></i>
                                            <span class="tooltip">Edit</span>
                                        </button>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $m['id']; ?>" title="Delete Member" aria-label="Delete Member">
                                            <i class="ri-delete-bin-line" style="color: #e74c3c;"></i>
                                            <span class="tooltip">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($members_paginated)): ?>
                                <tr><td colspan="7" class="no-results">
                                    <i class="ri-search-line"></i><br>
                                    No members found. Try adjusting your search.
                                </td></tr>
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
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&items_per_page=<?php echo $items_per_page; ?>"
                                   class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                                   <?php echo $page <= 1 ? 'onclick="return false;"' : ''; ?>>Previous</a>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&items_per_page=<?php echo $items_per_page; ?>"
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

<!-- Overlay -->
<div class="modal-overlay" id="overlay"></div>

<!-- Popups -->
<div class="popup" id="success-popup" role="alertdialog" aria-modal="true" aria-labelledby="success-message" style="display: none;">
    <div style="text-align: center;">
        <div style="font-size: 3rem; color: #2ecc71;"><i class="ri-checkbox-circle-line"></i></div>
        <h3 style="font-size: 1.5rem; margin: 16px 0;">Success</h3>
        <p id="success-message" style="color: #6b7280;">Operation completed successfully.</p>
        <p style="color: #6b7280; margin-top: 12px;">Redirecting in <span id="success-countdown">3</span> seconds...</p>
    </div>
</div>

<div class="popup" id="error-popup" role="alertdialog" aria-modal="true" aria-labelledby="error-message" style="display: none;">
    <div style="text-align: center;">
        <div style="font-size: 3rem; color: #e74c3c;"><i class="ri-error-warning-line"></i></div>
        <h3 style="font-size: 1.5rem; margin: 16px 0;">Error</h3>
        <p id="error-message" style="color: #6b7280;">An error occurred. Please try again.</p>
    </div>
</div>

<div class="popup" id="cancel-popup" role="alertdialog" aria-modal="true" aria-labelledby="cancel-message" style="display: none;">
    <div style="text-align: center;">
        <div style="font-size: 3rem; color: #6b7280;"><i class="ri-close-circle-line"></i></div>
        <h3 style="font-size: 1.5rem; margin: 16px 0;">Cancelled</h3>
        <p id="cancel-message" style="color: #6b7280;">Operation cancelled.</p>
        <p style="color: #6b7280; margin-top: 12px;">Redirecting in <span id="cancel-countdown">3</span> seconds...</p>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="edit-modal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2>Edit Member</h2>
        <form id="edit-form">
            <input type="hidden" id="edit-id" name="id">
            <div class="form-group">
                <label class="form-label" for="edit-full_name">Full Name</label>
                <input type="text" id="edit-full_name" name="full_name" class="input-field">
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-contact_number">Contact Number</label>
                <input type="text" id="edit-contact_number" name="contact_number" class="input-field">
                <span class="error-text" id="edit-contact_number-error">Invalid contact number (e.g., +94123456789)</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-membership_type">Membership Type</label>
                <select id="edit-membership_type" name="membership_type" class="input-field">
                    <option value="Individual">Individual</option>
                    <option value="Family">Family</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-payment_status">Payment Status</label>
                <select id="edit-payment_status" name="payment_status" class="input-field">
                    <option value="Active">Active</option>
                    <option value="Pending">Pending</option>
                    <option value="Overdue">Overdue</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-member_status">Member Status</label>
                <select id="edit-member_status" name="member_status" class="input-field">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Suspended">Suspended</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Details Modal -->
<div class="modal" id="edit-details-modal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2>Edit Member Details</h2>
        <form id="edit-details-form">
            <input type="hidden" id="edit-details-id" name="id">
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label" for="edit-details-full_name">Full Name</label>
                        <input type="text" id="edit-details-full_name" name="full_name" class="input-field">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-date_of_birth">Date of Birth</label>
                        <input type="date" id="edit-details-date_of_birth" name="date_of_birth" class="input-field">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-gender">Gender</label>
                        <select id="edit-details-gender" name="gender" class="input-field">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-nic_number">NIC Number</label>
                        <input type="text" id="edit-details-nic_number" name="nic_number" class="input-field">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-address">Address</label>
                        <input type="text" id="edit-details-address" name="address" class="input-field">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>Contact Information</h3>
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label" for="edit-details-contact_number">Contact Number</label>
                        <input type="text" id="edit-details-contact_number" name="contact_number" class="input-field">
                        <span class="error-text" id="edit-details-contact_number-error">Invalid contact number (e.g., +94123456789)</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-email">Email</label>
                        <input type="email" id="edit-details-email" name="email" class="input-field">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>Membership Information</h3>
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label" for="edit-details-date_of_joining">Date of Joining</label>
                        <input type="date" id="edit-details-date_of_joining" name="date_of_joining" class="input-field">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-membership_type">Membership Type</label>
                        <select id="edit-details-membership_type" name="membership_type" class="input-field">
                            <option value="Individual">Individual</option>
                            <option value="Family">Family</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-payment_status">Payment Status</label>
                        <select id="edit-details-payment_status" name="payment_status" class="input-field">
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-details-member_status">Member Status</label>
                        <select id="edit-details-member_status" name="member_status" class="input-field">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Family Modal -->
<div class="modal" id="edit-family-modal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2>Edit Family Details</h2>
        <form id="edit-family-form">
            <input type="hidden" id="edit-family-member-id" name="member_id">
            <div class="form-section">
                <h3>Spouse Details</h3>
                <div class="grid">
                    <div class="form-group">
                        <label class="form-label" for="edit-spouse-name">Spouse Name</label>
                        <input type="text" id="edit-spouse-name" name="spouse_name" class="input-field">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-spouse-dob">Date of Birth</label>
                        <input type="date" id="edit-spouse-dob" name="spouse_dob" class="input-field">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-spouse-gender">Gender</label>
                        <select id="edit-spouse-gender" name="spouse_gender" class="input-field">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>Children</h3>
                <div id="children-container" class="dynamic-fields"></div>
                <button type="button" id="add-child" class="btn btn-secondary add-field"><i class="ri-add-line"></i> Add Child</button>
            </div>
            <div class="form-section">
                <h3>Dependents</h3>
                <div id="dependents-container" class="dynamic-fields"></div>
                <button type="button" id="add-dependent" class="btn btn-secondary add-field"><i class="ri-add-line"></i> Add Dependent</button>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal delete-modal" id="delete-modal">
    <div class="modal-content" role="dialog" aria-modal="true">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <i class="ri-error-warning-line"></i>
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete this member? This action cannot be undone.</p>
        <form id="delete-form">
            <input type="hidden" id="delete-id" name="id">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- External JS -->
<script src="../../assets/js/member.js"></script>
</body>
</html>