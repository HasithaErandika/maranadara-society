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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $db = new Database();
        $conn = $db->getConnection();

        try {
            if (isset($_POST['delete'])) {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    if ($member->deleteMember($id)) {
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Member deleted successfully.']);
                        exit;
                    } else {
                        throw new Exception("Failed to delete member.");
                    }
                } else {
                    throw new Exception("Invalid member ID.");
                }
            } elseif (isset($_POST['update'])) {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $full_name = trim($_POST['full_name'] ?? '');
                $contact_number = trim($_POST['contact_number'] ?? '');
                $membership_type = trim($_POST['membership_type'] ?? '');
                $payment_status = trim($_POST['payment_status'] ?? '');
                $member_status = trim($_POST['member_status'] ?? '');

                if (empty($full_name)) {
                    throw new Exception("Full name is required.");
                }
                if (empty($contact_number) || !preg_match('/^\+94\d{9}$/', $contact_number)) {
                    throw new Exception("Valid contact number is required (format: +94XXXXXXXXX).");
                }
                if (!in_array($membership_type, ['Individual', 'Family', 'Senior Citizen'])) {
                    throw new Exception("Invalid membership type.");
                }
                if (!in_array($payment_status, ['Active', 'Pending', 'Inactive'])) {
                    throw new Exception("Invalid payment status.");
                }
                if (!in_array($member_status, ['Active', 'Deceased', 'Resigned'])) {
                    throw new Exception("Invalid member status.");
                }

                $data = [
                    'full_name' => $full_name,
                    'contact_number' => $contact_number,
                    'membership_type' => $membership_type,
                    'payment_status' => $payment_status,
                    'member_status' => $member_status
                ];

                if ($member->updateMember($id, $data)) {
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Member updated successfully.']);
                    exit;
                } else {
                    throw new Exception("Failed to update member.");
                }
            } elseif (isset($_POST['update_details'])) {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $full_name = trim($_POST['full_name'] ?? '');
                $date_of_birth = trim($_POST['date_of_birth'] ?? '');
                $gender = trim($_POST['gender'] ?? '');
                $nic_number = trim($_POST['nic_number'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $contact_number = trim($_POST['contact_number'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $date_of_joining = trim($_POST['date_of_joining'] ?? '');
                $membership_type = trim($_POST['membership_type'] ?? '');
                $payment_status = trim($_POST['payment_status'] ?? '');
                $member_status = trim($_POST['member_status'] ?? '');

                if (empty($full_name)) {
                    throw new Exception("Full name is required.");
                }
                if (empty($date_of_birth) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
                    throw new Exception("Valid date of birth is required (format: YYYY-MM-DD).");
                }
                if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                    throw new Exception("Invalid gender.");
                }
                if (empty($nic_number) || !preg_match('/^\d{9}[vVxX]|\d{12}$/', $nic_number)) {
                    throw new Exception("Valid NIC number is required.");
                }
                if (empty($address)) {
                    throw new Exception("Address is required.");
                }
                if (empty($contact_number) || !preg_match('/^\+94\d{9}$/', $contact_number)) {
                    throw new Exception("Valid contact number is required (format: +94XXXXXXXXX).");
                }
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Valid email is required.");
                }
                if (empty($date_of_joining) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_joining)) {
                    throw new Exception("Valid join date is required (format: YYYY-MM-DD).");
                }
                if (!in_array($membership_type, ['Individual', 'Family', 'Senior Citizen'])) {
                    throw new Exception("Invalid membership type.");
                }
                if (!in_array($payment_status, ['Active', 'Pending', 'Inactive'])) {
                    throw new Exception("Invalid payment status.");
                }
                if (!in_array($member_status, ['Active', 'Deceased', 'Resigned'])) {
                    throw new Exception("Invalid member status.");
                }

                $data = [
                    'full_name' => $full_name,
                    'date_of_birth' => $date_of_birth,
                    'gender' => $gender,
                    'nic_number' => $nic_number,
                    'address' => $address,
                    'contact_number' => $contact_number,
                    'email' => $email,
                    'date_of_joining' => $date_of_joining,
                    'membership_type' => $membership_type,
                    'payment_status' => $payment_status,
                    'member_status' => $member_status
                ];

                if ($member->updateMember($id, $data)) {
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Member details updated successfully.']);
                    exit;
                } else {
                    throw new Exception("Failed to update member details.");
                }
            } elseif (isset($_POST['update_family'])) {
                $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
                
                // Process spouse data
                $spouse_data = null;
                if (!empty($_POST['spouse_name'])) {
                    $spouse_data = [
                        'name' => trim($_POST['spouse_name']),
                        'age' => !empty($_POST['spouse_age']) ? (int)$_POST['spouse_age'] : null,
                        'gender' => !empty($_POST['spouse_gender']) ? trim($_POST['spouse_gender']) : null
                    ];
                }

                // Process children data
                $children_data = [];
                if (isset($_POST['children']) && is_array($_POST['children'])) {
                    foreach ($_POST['children'] as $child) {
                        if (!empty(trim($child['name']))) {
                            $children_data[] = [
                                'name' => trim($child['name']),
                                'age' => !empty($child['age']) ? (int)$child['age'] : null,
                                'gender' => !empty($child['gender']) ? trim($child['gender']) : null
                            ];
                        }
                    }
                }

                // Process dependents data
                $dependents_data = [];
                if (isset($_POST['dependents']) && is_array($_POST['dependents'])) {
                    foreach ($_POST['dependents'] as $dependent) {
                        if (!empty(trim($dependent['name']))) {
                            $dependents_data[] = [
                                'name' => trim($dependent['name']),
                                'relationship' => !empty($dependent['relationship']) ? trim($dependent['relationship']) : null,
                                'age' => !empty($dependent['age']) ? (int)$dependent['age'] : null
                            ];
                        }
                    }
                }

                if (!$member_id) {
                    throw new Exception("Invalid member ID.");
                }

                try {
                    if ($family->updateFamilyDetails($member_id, $spouse_data, $children_data, $dependents_data)) {
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Family details updated successfully.']);
                    exit;
                } else {
                    throw new Exception("Failed to update family details.");
                    }
                } catch (Exception $e) {
                    throw new Exception("Error updating family details: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // Escape messages for JavaScript (for synchronous requests)
    $js_success = json_encode($success);
    $js_error = json_encode($error);
    error_log("JS Success: $js_success");
    error_log("JS Error: $js_error");

    // Clear output buffer for non-AJAX requests
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
    
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main">
        <div class="container">
            <div class="flex justify-between items-center mb-6 animate-slide-in">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 700;">
                        <?php echo $selected_member ? 'Member Details' : 'Manage Members'; ?>
                    </h1>
                    <?php if (!$selected_member): ?>
                        <p style="font-size: 0.9rem; color: #7f8c8d;">View and manage member records efficiently.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Search Bar -->
            <?php if (!$selected_member): ?>
                <form method="GET" class="search-container animate-slide-in" id="search-form" role="search">
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
                            <?php if (!empty($family_details)): ?>
                                <div class="family-details-container">
                                    <!-- Spouse Section -->
                                    <?php if (!empty($family_details['spouse_name'])): ?>
                                        <div class="family-section">
                                            <h3><i class="ri-user-heart-line"></i> Spouse</h3>
                                            <p><?php echo htmlspecialchars($family_details['spouse_name']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Children Section -->
                                    <?php if (!empty($family_details['children_info'])): ?>
                                        <div class="family-section">
                                            <h3><i class="ri-parent-line"></i> Children</h3>
                                            <div class="family-list">
                                                <?php 
                                                $children = explode(', ', $family_details['children_info']);
                                                foreach ($children as $child): 
                                                ?>
                                                    <div class="family-item">
                                                        <i class="ri-user-smile-line"></i>
                                                        <span><?php echo htmlspecialchars($child); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Dependents Section -->
                                    <?php if (!empty($family_details['dependents_info'])): ?>
                                        <div class="family-section">
                                            <h3><i class="ri-group-line"></i> Dependents</h3>
                                            <div class="family-list">
                                                <?php 
                                                $dependents = explode(', ', $family_details['dependents_info']);
                                                foreach ($dependents as $dependent): 
                                                ?>
                                                    <div class="family-item">
                                                        <i class="ri-user-line"></i>
                                                        <span><?php echo htmlspecialchars($dependent); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="family-actions">
                                    <button class="btn btn-primary edit-family-btn" data-family='<?php echo htmlspecialchars(json_encode($family_details), ENT_QUOTES); ?>' data-member-id="<?php echo htmlspecialchars($selected_member['id']); ?>">
                                        <i class="ri-user-add-line"></i> Update Family Details
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="family-actions">
                                    <p style="color: #7f8c8d; margin-bottom: 10px;">No family details available.</p>
                                    <button class="btn btn-primary edit-family-btn" data-family='<?php echo htmlspecialchars(json_encode([]), ENT_QUOTES); ?>' data-member-id="<?php echo htmlspecialchars($selected_member['id']); ?>">
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
<div class="overlay" id="overlay"></div>

<!-- Popups -->
<div class="popup-overlay" id="popup-overlay"></div>

<div class="popup" id="success-popup">
    <div style="text-align: center;">
        <div style="font-size: 3rem; color: #2ecc71; margin-bottom: 20px;"><i class="ri-checkbox-circle-fill"></i></div>
        <h3 style="font-size: 1.5rem; font-weight: 700;">Success!</h3>
        <p style="color: #7f8c8d; margin-top: 10px;" id="success-message"></p>
        <div style="margin-top: 20px; font-size: 0.8rem; color: #7f8c8d;">
            Redirecting in <span id="success-countdown" style="font-weight: 600;">3</span> seconds...
        </div>
    </div>
</div>

<div class="popup" id="error-popup">
    <div style="text-align: center;">
        <div style="font-size: 3rem; color: #e74c3c; margin-bottom: 20px;"><i class="ri-error-warning-fill"></i></div>
        <h3 style="font-size: 1.5rem; font-weight: 700;">Error</h3>
        <p style="color: #7f8c8d; margin-top: 10px;" id="error-message"></p>
        <div style="margin-top: 20px; font-size: 0.8rem; color: #7f8c8d;">
            Redirecting in <span id="error-countdown" style="font-weight: 600;">3</span> seconds...
        </div>
    </div>
</div>

<div class="popup" id="cancel-popup">
    <div style="text-align: center;">
        <div style="font-size: 3rem; color: #7f8c8d; margin-bottom: 20px;"><i class="ri-close-circle-fill"></i></div>
        <h3 style="font-size: 1.5rem; font-weight: 700;">Cancelled</h3>
        <p style="color: #7f8c8d; margin-top: 10px;">The operation has been cancelled.</p>
        <div style="margin-top: 20px; font-size: 0.8rem; color: #7f8c8d;">
            Redirecting in <span id="cancel-countdown" style="font-weight: 600;">3</span> seconds...
        </div>
    </div>
</div>

<!-- Edit Modal (Manage Members) -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2 style="font-size: 1.5rem; color: #e67e22; margin-bottom: 20px;">Edit Member</h2>
        <form id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="grid">
                <div class="form-group">
                    <label for="edit-full_name" class="form-label">Full Name <span class="required-mark">*</span></label>
                    <input type="text" name="full_name" id="edit-full_name" class="input-field" required aria-describedby="edit-full_name-error">
                    <span class="error-text" id="edit-full_name-error">Full name is required.</span>
                </div>
                <div class="form-group">
                    <label for="edit-contact_number" class="form-label">Contact Number <span class="required-mark">*</span></label>
                    <input type="text" name="contact_number" id="edit-contact_number" class="input-field" required aria-describedby="edit-contact_number-error">
                    <span class="error-text" id="edit-contact_number-error">Valid contact number is required (format: +94XXXXXXXXX).</span>
                </div>
                <div class="form-group">
                    <label for="edit-membership_type" class="form-label">Membership Type</label>
                    <select name="membership_type" id="edit-membership_type" class="input-field" aria-label="Membership Type">
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-payment_status" class="form-label">Payment Status</label>
                    <select name="payment_status" id="edit-payment_status" class="input-field" aria-label="Payment Status">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-member_status" class="form-label">Member Status</label>
                    <select name="member_status" id="edit-member_status" class="input-field" aria-label="Member Status">
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>
            <div class="flex" style="justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Details Modal (Member Details) -->
<div id="edit-details-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2 style="font-size: 1.5rem; color: #e67e22; margin-bottom: 20px;">Edit Member Details</h2>
        <form id="edit-details-form">
            <input type="hidden" name="id" id="edit-details-id">
            <div class="form-section">
                <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 15px;">Personal Information</h3>
                <div class="grid">
                    <div class="form-group">
                        <label for="edit-details-full_name" class="form-label">Full Name <span class="required-mark">*</span></label>
                        <input type="text" name="full_name" id="edit-details-full_name" class="input-field" required aria-describedby="edit-details-full_name-error">
                        <span class="error-text" id="edit-details-full_name-error">Full name is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-date_of_birth" class="form-label">Date of Birth <span class="required-mark">*</span></label>
                        <input type="date" name="date_of_birth" id="edit-details-date_of_birth" class="input-field" required aria-describedby="edit-details-date_of_birth-error">
                        <span class="error-text" id="edit-details-date_of_birth-error">Valid date of birth is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-gender" class="form-label">Gender <span class="required-mark">*</span></label>
                        <select name="gender" id="edit-details-gender" class="input-field" required aria-describedby="edit-details-gender-error">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="error-text" id="edit-details-gender-error">Gender is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-nic_number" class="form-label">NIC <span class="required-mark">*</span></label>
                        <input type="text" name="nic_number" id="edit-details-nic_number" class="input-field" required aria-describedby="edit-details-nic_number-error">
                        <span class="error-text" id="edit-details-nic_number-error">Valid NIC number is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-address" class="form-label">Address <span class="required-mark">*</span></label>
                        <input type="text" name="address" id="edit-details-address" class="input-field" required aria-describedby="edit-details-address-error">
                        <span class="error-text" id="edit-details-address-error">Address is required.</span>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 15px;">Contact Information</h3>
                <div class="grid">
                    <div class="form-group">
                        <label for="edit-details-contact_number" class="form-label">Contact Number <span class="required-mark">*</span></label>
                        <input type="text" name="contact_number" id="edit-details-contact_number" class="input-field" required aria-describedby="edit-details-contact_number-error">
                        <span class="error-text" id="edit-details-contact_number-error">Valid contact number is required (format: +94XXXXXXXXX).</span>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-email" class="form-label">Email</label>
                        <input type="email" name="email" id="edit-details-email" class="input-field" aria-describedby="edit-details-email-error">
                        <span class="error-text" id="edit-details-email-error">Valid email is required.</span>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 15px;">Membership Information</h3>
                <div class="grid">
                    <div class="form-group">
                        <label for="edit-details-date_of_joining" class="form-label">Join Date <span class="required-mark">*</span></label>
                        <input type="date" name="date_of_joining" id="edit-details-date_of_joining" class="input-field" required aria-describedby="edit-details-date_of_joining-error">
                        <span class="error-text" id="edit-details-date_of_joining-error">Valid join date is required.</span>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-membership_type" class="form-label">Membership Type</label>
                        <select name="membership_type" id="edit-details-membership_type" class="input-field" aria-label="Membership Type">
                            <option value="Individual">Individual</option>
                            <option value="Family">Family</option>
                            <option value="Senior Citizen">Senior Citizen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-payment_status" class="form-label">Payment Status</label>
                        <select name="payment_status" id="edit-details-payment_status" class="input-field" aria-label="Payment Status">
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-details-member_status" class="form-label">Member Status</label>
                        <select name="member_status" id="edit-details-member_status" class="input-field" aria-label="Member Status">
                            <option value="Active">Active</option>
                            <option value="Deceased">Deceased</option>
                            <option value="Resigned">Resigned</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex" style="justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Family Modal -->
<div id="edit-family-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2 style="font-size: 1.5rem; color: #e67e22; margin-bottom: 20px;">Edit Family Details</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 0.9rem;">All family details are optional. You can add or remove family members as needed.</p>
        <form id="edit-family-form">
            <input type="hidden" name="member_id" id="edit-family-member-id">
            
            <!-- Spouse Section -->
            <div class="form-section">
                <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 15px;">Spouse Information (Optional)</h3>
                <div class="grid">
                <div class="form-group">
                    <label for="edit-spouse-name" class="form-label">Spouse Name</label>
                        <input type="text" name="spouse_name" id="edit-spouse-name" class="input-field">
                </div>
                    <div class="form-group">
                        <label for="edit-spouse-age" class="form-label">Spouse Age</label>
                        <input type="number" name="spouse_age" id="edit-spouse-age" class="input-field" min="0" max="120">
            </div>
                    <div class="form-group">
                        <label for="edit-spouse-gender" class="form-label">Spouse Gender</label>
                        <select name="spouse_gender" id="edit-spouse-gender" class="input-field">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Children Section -->
            <div class="form-section">
                <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 15px;">Children (Optional)</h3>
                <div id="children-container" class="dynamic-fields">
                    <!-- Dynamic children inputs will be added here -->
                </div>
                <button type="button" class="btn btn-secondary add-field" id="add-child"><i class="ri-add-line"></i> Add Child</button>
            </div>

            <!-- Dependents Section -->
            <div class="form-section">
                <h3 style="font-size: 1.2rem; color: #e67e22; margin-bottom: 15px;">Dependents (Optional)</h3>
                <div id="dependents-container" class="dynamic-fields">
                    <!-- Dynamic dependents inputs will be added here -->
                </div>
                <button type="button" class="btn btn-secondary add-field" id="add-dependent"><i class="ri-add-line"></i> Add Dependent</button>
            </div>

            <div class="flex" style="justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <div style="text-align: center;">
            <i class="ri-error-warning-fill" style="font-size: 3rem; color: #e74c3c; margin-bottom: 20px;"></i>
            <h3 style="font-size: 1.5rem; font-weight: 700;">Confirm Deletion</h3>
            <p style="color: #7f8c8d; margin-top: 10px;">Are you sure you want to delete this member? This action cannot be undone.</p>
            <form id="delete-form">
                <input type="hidden" name="id" id="delete-id">
                <div class="flex" style="justify-content: center; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-delete-bin-line"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Pass PHP variables to JavaScript
    window.successMsg = '<?php echo json_encode($js_success); ?>';
    window.errorMsg = '<?php echo json_encode($js_error); ?>';
</script>
<script src="../../assets/js/member.js"></script>
</body>
</html>
