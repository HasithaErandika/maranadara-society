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
error_log("add_member.php: Session: " . print_r($_SESSION, true));

try {
    // Redirect if not admin or credentials missing
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' ||
        !isset($_SESSION['db_username']) || !isset($_SESSION['db_password'])) {
        error_log("add_member.php: Missing session variables, redirecting to admin-login.php");
        header("Location: /admin-login.php");
        exit;
    }

    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../classes/Member.php';
    require_once __DIR__ . '/../../classes/Family.php';
    require_once __DIR__ . '/../../classes/Database.php';

    $member = new Member();
    $family = new Family();
    $error = $success = '';

    // Auto-generate member ID
    $next_id = $member->generateMemberId();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("add_member.php: POST received: " . print_r($_POST, true));

        // Sanitize and validate inputs
        $member_id = trim($_POST['member_id'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $nic_number = trim($_POST['nic_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '') ?: null;
        $occupation = trim($_POST['occupation'] ?? '') ?: null;
        $date_of_joining = $_POST['date_of_joining'] ?? '';
        $membership_type = $_POST['membership_type'] ?? '';
        $contribution_amount = (float)($_POST['contribution_amount'] ?? 0);
        $payment_status = $_POST['payment_status'] ?? '';
        $member_status = $_POST['member_status'] ?? '';

        // Validate mandatory member details
        if (empty($member_id) || !ctype_digit($member_id)) {
            throw new Exception("Membership ID must be a positive number.");
        }
        if (empty($full_name)) {
            throw new Exception("Full name is required.");
        }
        if (empty($nic_number)) {
            throw new Exception("NIC number is required.");
        }
        if (empty($address)) {
            throw new Exception("Address is required.");
        }
        if (empty($contact_number)) {
            throw new Exception("Contact number is required.");
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        if (empty($date_of_birth)) {
            throw new Exception("Date of birth is required.");
        }
        if (empty($gender)) {
            throw new Exception("Gender is required.");
        }
        if (empty($date_of_joining)) {
            throw new Exception("Date of joining is required.");
        }
        if (empty($membership_type)) {
            throw new Exception("Membership type is required.");
        }
        if ($contribution_amount <= 0) {
            throw new Exception("Contribution amount must be greater than zero.");
        }
        if (empty($payment_status)) {
            throw new Exception("Payment status is required.");
        }
        if (empty($member_status)) {
            throw new Exception("Member status is required.");
        }

        // Check member_id and NIC uniqueness
        if (!$member->isMemberIdUnique($member_id)) {
            throw new Exception("Membership ID '$member_id' already exists.");
        }
        if (!$member->isNicUnique($nic_number)) {
            throw new Exception("NIC number '$nic_number' already exists.");
        }

        // Start transaction
        $db = new Database();
        $conn = $db->getConnection();
        $conn->begin_transaction();

        try {
            // Add member
            $member->addMember(
                $member_id,
                $full_name,
                $date_of_birth,
                $gender,
                $nic_number,
                $address,
                $contact_number,
                $email,
                $occupation,
                $date_of_joining,
                $membership_type,
                $contribution_amount,
                $payment_status,
                $member_status
            );

            // Get new member's ID
            $stmt = $conn->prepare("SELECT id FROM members WHERE member_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            $stmt->bind_param("s", $member_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                throw new Exception("New member not found after insertion.");
            }
            $new_member_id = $result['id'];

            // Add spouse details (optional)
            if (isset($_POST['spouse_name']) && !empty(trim($_POST['spouse_name']))) {
                $spouse_name = trim($_POST['spouse_name']);
                $spouse_age = !empty($_POST['spouse_age']) ? (int)$_POST['spouse_age'] : null;
                $spouse_gender = !empty($_POST['spouse_gender']) ? $_POST['spouse_gender'] : null;

                if ($family->getSpouseCount($new_member_id) >= 1) {
                    throw new Exception("Only one spouse is allowed.");
                }

                $spouse_data = [
                    'name' => $spouse_name,
                    'age' => $spouse_age,
                    'gender' => $spouse_gender
                ];

                try {
                    $family->validateSpouseData($spouse_data);
                    if (!$family->addFamilyDetails($new_member_id, $spouse_data)) {
                        throw new Exception("Failed to add spouse details.");
                    }
                } catch (Exception $e) {
                    throw new Exception("Spouse details error: " . $e->getMessage());
                }
            }

            // Add children details (optional)
            if (isset($_POST['children']) && is_array($_POST['children']) && !empty($_POST['children'])) {
                $children_data = [];
                foreach ($_POST['children'] as $child) {
                    if (!empty(trim($child['name'])) && !empty($child['age']) && !empty($child['gender'])) {
                        $children_data[] = [
                            'name' => trim($child['name']),
                            'age' => (int)$child['age'],
                            'gender' => $child['gender']
                        ];
                    }
                }
                if (!empty($children_data)) {
                    if (count($children_data) > 5) {
                        throw new Exception("Maximum of 5 children are allowed.");
                    }
                    try {
                        foreach ($children_data as $child_data) {
                            $family->validateChildData($child_data);
                        }
                        if (!$family->addFamilyDetails($new_member_id, null, $children_data)) {
                            throw new Exception("Failed to add children details.");
                        }
                    } catch (Exception $e) {
                        throw new Exception("Children details error: " . $e->getMessage());
                    }
                }
            }

            // Add dependents details (optional)
            if (isset($_POST['dependents']) && is_array($_POST['dependents']) && !empty($_POST['dependents'])) {
                $dependents_data = [];
                foreach ($_POST['dependents'] as $dependent) {
                    if (!empty(trim($dependent['name'])) && !empty(trim($dependent['relationship']))) {
                        $dependents_data[] = [
                            'name' => trim($dependent['name']),
                            'relationship' => trim($dependent['relationship']),
                            'age' => !empty($dependent['age']) ? (int)$dependent['age'] : null
                        ];
                    }
                }
                if (!empty($dependents_data)) {
                    if (count($dependents_data) > 4) {
                        throw new Exception("Maximum of 4 dependents are allowed.");
                    }
                    try {
                        foreach ($dependents_data as $dependent_data) {
                            $family->validateDependentData($dependent_data);
                        }
                        if (!$family->addFamilyDetails($new_member_id, null, null, $dependents_data)) {
                            throw new Exception("Failed to add dependents details.");
                        }
                    } catch (Exception $e) {
                        throw new Exception("Dependents details error: " . $e->getMessage());
                    }
                }
            }

            // Commit transaction
            $conn->commit();
            $success = "Member '$full_name' added successfully!";
            error_log("add_member.php: Success: $success");

            // Debug: Verify insertion
            $result = $conn->query("SELECT * FROM members WHERE member_id = '$member_id'");
            error_log("Inserted member: " . print_r($result->fetch_assoc(), true));
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Database error: " . $e->getMessage());
        } finally {
            $db->closeConnection();
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("add_member.php: Error: $error");
}

// Check if the request is AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Clear output buffer to prevent any prior output
    ob_end_clean();
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true, 'message' => $success]);
    } else {
        echo json_encode(['success' => false, 'message' => $error]);
    }
    exit;
}

// Escape messages for JavaScript (for synchronous requests)
$js_success = json_encode($success);
$js_error = json_encode($error);
error_log("JS Success: $js_success");
error_log("JS Error: $js_error");

// Clear output buffer for non-AJAX requests
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member - Maranadhara Samithi</title>
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
            padding-top: 50px;
        }

        .container {
            max-width: 1250px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-section h2 {
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

        .required-mark {
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

        .family-content {
            margin-top: 20px;
        }

        .entry-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
            z-index: 50;
        }

        .popup.show {
            opacity: 1;
            pointer-events: auto;
            transform: translate(-50%, -50%) scale(1);
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 40;
        }

        .popup-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        .progress-bar {
            position: sticky;
            top: 0;
            height: 4px;
            background: #e67e22;
            border-radius: 2px;
            width: 0;
            transition: width 0.5s;
            z-index: 50;
        }

        .flex {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
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
<?php include __DIR__ . '/../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include __DIR__ . '/../../includes/sidepanel.php'; ?>
    <main class="main">
        <div class="container">
            <h1 class="animate-slide-in" style="font-size: 2rem; font-weight: 700; margin-bottom: 20px;">Add New Member</h1>
            <div class="progress-bar" id="progress-bar"></div>

            <form method="POST" id="add-member-form" style="margin-top: 20px;">
                <!-- Member Information -->
                <div class="form-section">
                    <h2>Member Information</h2>
                    <div class="grid">
                        <div class="form-group">
                            <label for="member_id" class="form-label">Membership ID<span class="required-mark">*</span></label>
                            <input type="text" id="member_id" name="member_id" value="<?php echo htmlspecialchars($next_id); ?>" class="input-field" required>
                            <span class="error-text" id="member_id-error">Must be a positive number.</span>
                        </div>
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name <span class="required-mark">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="input-field" required>
                            <span class="error-text" id="full_name-error">Full name is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth <span class="required-mark">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="input-field" max="<?php echo date('Y-m-d'); ?>" required>
                            <span class="error-text" id="date_of_birth-error">Date of birth is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="gender" class="form-label">Gender <span class="required-mark">*</span></label>
                            <select id="gender" name="gender" class="input-field" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="error-text" id="gender-error">Gender is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="nic_number" class="form-label">NIC Number <span class="required-mark">*</span></label>
                            <input type="text" id="nic_number" name="nic_number" class="input-field" required>
                            <span class="error-text" id="nic_number-error">NIC number is required.</span>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="address" class="form-label">Address <span class="required-mark">*</span></label>
                            <input type="text" id="address" name="address" class="input-field" required>
                            <span class="error-text" id="address-error">Address is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="contact_number" class="form-label">Contact Number <span class="required-mark">*</span></label>
                            <input type="text" id="contact_number" name="contact_number" class="input-field" placeholder="+94XXXXXXXXX" required>
                            <span class="error-text" id="contact_number-error">Format: +94 followed by 9 digits.</span>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="input-field">
                            <span class="error-text" id="email-error">Invalid email format.</span>
                        </div>
                        <div class="form-group">
                            <label for="occupation" class="form-label">Occupation (Optional)</label>
                            <input type="text" id="occupation" name="occupation" class="input-field">
                            <span class="error-text" id="occupation-error"></span>
                        </div>
                    </div>
                </div>

                <!-- Membership Details -->
                <div class="form-section">
                    <h2>Membership Details</h2>
                    <div class="grid">
                        <div class="form-group">
                            <label for="date_of_joining" class="form-label">Date of Joining <span class="required-mark">*</span></label>
                            <input type="date" id="date_of_joining" name="date_of_joining" class="input-field" value="<?php echo date('Y-m-d'); ?>" required>
                            <span class="error-text" id="date_of_joining-error">Date of joining is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="membership_type" class="form-label">Membership Type <span class="required-mark">*</span></label>
                            <select id="membership_type" name="membership_type" class="input-field" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="Individual">Individual</option>
                                <option value="Family">Family</option>
                                <option value="Senior Citizen">Senior Citizen</option>
                            </select>
                            <span class="error-text" id="membership_type-error">Membership type is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="contribution_amount" class="form-label">Contribution (LKR) <span class="required-mark">*</span></label>
                            <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" min="0" class="input-field" required>
                            <span class="error-text" id="contribution_amount-error">Must be greater than zero.</span>
                        </div>
                        <div class="form-group">
                            <label for="payment_status" class="form-label">Payment Status <span class="required-mark">*</span></label>
                            <select id="payment_status" name="payment_status" class="input-field" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <span class="error-text" id="payment_status-error">Payment status is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="member_status" class="form-label">Member Status <span class="required-mark">*</span></label>
                            <select id="member_status" name="member_status" class="input-field" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Resigned">Resigned</option>
                            </select>
                            <span class="error-text" id="member_status-error">Member status is required.</span>
                        </div>
                    </div>
                </div>

                <!-- Family Details -->
                <div class="form-section">
                    <h2>Family Details (Optional)</h2>
                    <div style="margin-top: 20px;">
                        <!-- Spouse Section -->
                        <div class="family-section">
                            <div class="family-header">
                                <button type="button" id="add-spouse-btn" class="btn btn-primary">
                                    <i class="ri-user-add-line"></i>Add Spouse
                                </button>
                                <span id="spouse-limit" style="font-size: 0.8rem; color: #7f8c8d;">1 Spouse Max</span>
                            </div>
                            <div id="spouse-details" class="family-content" style="display: none;">
                                <div class="grid">
                                    <div class="form-group">
                                        <label for="spouse_name" class="form-label">Spouse Name</label>
                                        <input type="text" id="spouse_name" name="spouse_name" class="input-field">
                                        <span class="error-text" id="spouse_name-error">Spouse name is required if provided.</span>
                                    </div>
                                    <div class="form-group">
                                        <label for="spouse_age" class="form-label">Age</label>
                                        <input type="number" id="spouse_age" name="spouse_age" class="input-field" min="0" max="120">
                                        <span class="error-text" id="spouse_age-error">Age must be between 0 and 120.</span>
                                    </div>
                                    <div class="form-group">
                                        <label for="spouse_gender" class="form-label">Gender</label>
                                        <select id="spouse_gender" name="spouse_gender" class="input-field">
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <span class="error-text" id="spouse_gender-error">Gender is required if provided.</span>
                                    </div>
                                </div>
                                <button type="button" id="remove-spouse-btn" class="btn btn-danger" style="margin-top: 20px;">Remove Spouse</button>
                            </div>
                        </div>

                        <!-- Children Section -->
                        <div class="family-section" style="margin-top: 20px;">
                            <div class="family-header">
                                <button type="button" id="add-child-btn" class="btn btn-primary">
                                    <i class="ri-user-add-line"></i>Add Child
                                </button>
                                <span id="child-limit" style="font-size: 0.8rem; color: #7f8c8d;">5 Children Max</span>
                            </div>
                            <div id="children-details" class="family-content" style="display: none;">
                                <div id="children-list"></div>
                                <button type="button" id="add-another-child-btn" class="btn btn-primary" style="margin-top: 20px;">
                                    <i class="ri-add-line"></i>Add Another Child
                                </button>
                            </div>
                        </div>

                        <!-- Dependents Section -->
                        <div class="family-section" style="margin-top: 20px;">
                            <div class="family-header">
                                <button type="button" id="add-dependent-btn" class="btn btn-primary">
                                    <i class="ri-user-add-line"></i>Add Dependent
                                </button>
                                <span id="dependent-limit" style="font-size: 0.8rem; color: #7f8c8d;">4 Dependents Max</span>
                            </div>
                            <div id="dependents-details" class="family-content" style="display: none;">
                                <div id="dependents-list"></div>
                                <button type="button" id="add-another-dependent-btn" class="btn btn-primary" style="margin-top: 20px;">
                                    <i class="ri-add-line"></i>Add Another Dependent
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-user-add-line"></i>Add Member
                    </button>
                    <button type="button" id="cancel-button" class="btn btn-secondary">
                        <i class="ri-close-line"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('add-member-form');
    const inputs = form.querySelectorAll('.input-field');
    const progressBar = document.getElementById('progress-bar');
    const popupOverlay = document.getElementById('popup-overlay');
    const successPopup = document.getElementById('success-popup');
    const errorPopup = document.getElementById('error-popup');
    const cancelPopup = document.getElementById('cancel-popup');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    const cancelButton = document.getElementById('cancel-button');

    const MAX_SPOUSE = 1;
    const MAX_CHILDREN = 5;
    const MAX_DEPENDENTS = 4;
    let childCount = 0;
    let dependentCount = 0;

    // Update progress bar
    const updateProgress = () => {
        const requiredInputs = form.querySelectorAll('.input-field[required]');
        let filled = 0;
        requiredInputs.forEach(input => {
            if (input.value.trim()) filled++;
        });
        const progress = (filled / requiredInputs.length) * 100;
        progressBar.style.width = `${progress}%`;
    };

    // Input validation
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            const group = input.closest('.form-group');
            const error = group?.querySelector('.error-text');
            let isValid = true;

            if (input.id === 'member_id' && (!/^\d+$/.test(input.value) || parseInt(input.value) <= 0)) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'full_name' && !input.value.trim()) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'nic_number' && !input.value.trim()) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'contact_number' && !/^\+94\d{9}$/.test(input.value)) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'email' && input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'address' && !input.value.trim()) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'date_of_birth' && !input.value) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'gender' && !input.value) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'date_of_joining' && !input.value) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'membership_type' && !input.value) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'contribution_amount' && (!input.value || parseFloat(input.value) <= 0)) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'payment_status' && !input.value) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'member_status' && !input.value) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else if (input.id === 'spouse_age' && input.value && (parseInt(input.value) < 0 || parseInt(input.value) > 120)) {
                error?.classList.add('show');
                group?.classList.remove('valid');
                isValid = false;
            } else {
                error?.classList.remove('show');
                group?.classList.add('valid');
            }

            updateProgress();
        });
    });

    // Show popups if messages exist (for synchronous submissions)
    const successMsg = <?php echo $js_success; ?>;
    const errorMsg = <?php echo $js_error; ?>;

    if (successMsg) {
        successMessage.textContent = successMsg;
        showPopup(successPopup);
        startCountdown('success-countdown', 'add_member.php');
    } else if (errorMsg) {
        errorMessage.textContent = errorMsg;
        showPopup(errorPopup);
        startCountdown('error-countdown', 'add_member.php');
    }

    // Cancel button
    cancelButton.addEventListener('click', (e) => {
        e.preventDefault();
        showPopup(cancelPopup);
        startCountdown('cancel-countdown', 'add_member.php');
    });

    function showPopup(popup) {
        popupOverlay.classList.add('show');
        popup.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function hidePopup(popup) {
        popupOverlay.classList.remove('show');
        popup.classList.remove('show');
        document.body.style.overflow = '';
    }

    function startCountdown(elementId, redirectUrl) {
        let timeLeft = 3;
        const countdown = document.getElementById(elementId);
        if (countdown) {
            const interval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    window.location.href = redirectUrl;
                }
            }, 1000);
        }
    }

    // Spouse handling
    const addSpouseBtn = document.getElementById('add-spouse-btn');
    const spouseDetails = document.getElementById('spouse-details');
    const removeSpouseBtn = document.getElementById('remove-spouse-btn');
    const spouseLimit = document.getElementById('spouse-limit');

    addSpouseBtn.addEventListener('click', () => {
        spouseDetails.style.display = 'block';
        addSpouseBtn.style.display = 'none';
        spouseLimit.style.color = '#e74c3c';
    });

    removeSpouseBtn.addEventListener('click', () => {
        spouseDetails.style.display = 'none';
        addSpouseBtn.style.display = 'inline-flex';
        spouseLimit.style.color = '#7f8c8d';
        document.getElementById('spouse_name').value = '';
        document.getElementById('spouse_age').value = '';
        document.getElementById('spouse_gender').value = '';
    });

    // Children handling
    const addChildBtn = document.getElementById('add-child-btn');
    const addAnotherChildBtn = document.getElementById('add-another-child-btn');
    const childrenDetails = document.getElementById('children-details');
    const childrenList = document.getElementById('children-list');
    const childLimit = document.getElementById('child-limit');

    addChildBtn.addEventListener('click', () => {
        if (childCount < MAX_CHILDREN) {
            childrenDetails.style.display = 'block';
            addChildBtn.style.display = 'none';
            addChildEntry();
            updateChildLimit();
        }
    });

    addAnotherChildBtn.addEventListener('click', () => {
        if (childCount < MAX_CHILDREN) {
            addChildEntry();
            updateChildLimit();
        }
    });

    function addChildEntry() {
        const childDiv = document.createElement('div');
        childDiv.className = 'entry-card';
        childDiv.innerHTML = `
            <button type="button" onclick="removeChildEntry(this)" class="remove-btn">
                <i class="ri-close-circle-line" style="font-size: 1.2rem;"></i>
            </button>
            <div class="grid">
                <div class="form-group">
                    <label class="form-label">Child Name</label>
                    <input type="text" name="children[${childCount}][name]" class="input-field">
                    <span class="error-text">Child name is required if provided.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input type="number" name="children[${childCount}][age]" class="input-field" min="0" max="120">
                    <span class="error-text">Age is required if provided.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="children[${childCount}][gender]" class="input-field">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <span class="error-text">Gender is required if provided.</span>
                </div>
            </div>
        `;
        childrenList.appendChild(childDiv);
        childCount++;
        addAnotherChildBtn.disabled = childCount >= MAX_CHILDREN;
    }

    function removeChildEntry(button) {
        button.closest('.entry-card').remove();
        childCount--;
        addAnotherChildBtn.disabled = childCount >= MAX_CHILDREN;
        if (childCount === 0) {
            childrenDetails.style.display = 'none';
            addChildBtn.style.display = 'inline-flex';
        }
        updateChildLimit();
    }

    function updateChildLimit() {
        childLimit.textContent = `${childCount}/${MAX_CHILDREN} Children`;
        childLimit.style.color = childCount === MAX_CHILDREN ? '#e74c3c' : '#7f8c8d';
    }

    // Dependents handling
    const addDependentBtn = document.getElementById('add-dependent-btn');
    const addAnotherDependentBtn = document.getElementById('add-another-dependent-btn');
    const dependentsDetails = document.getElementById('dependents-details');
    const dependentsList = document.getElementById('dependents-list');
    const dependentLimit = document.getElementById('dependent-limit');

    addDependentBtn.addEventListener('click', () => {
        if (dependentCount < MAX_DEPENDENTS) {
            dependentsDetails.style.display = 'block';
            addDependentBtn.style.display = 'none';
            addDependentEntry();
            updateDependentLimit();
        }
    });

    addAnotherDependentBtn.addEventListener('click', () => {
        if (dependentCount < MAX_DEPENDENTS) {
            addDependentEntry();
            updateDependentLimit();
        }
    });

    function addDependentEntry() {
        const dependentDiv = document.createElement('div');
        dependentDiv.className = 'entry-card';
        dependentDiv.innerHTML = `
            <button type="button" onclick="removeDependentEntry(this)" class="remove-btn">
                <i class="ri-close-circle-line" style="font-size: 1.2rem;"></i>
            </button>
            <div class="grid">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="dependents[${dependentCount}][name]" class="input-field">
                    <span class="error-text">Name is required if provided.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Relationship</label>
                    <select name="dependents[${dependentCount}][relationship]" class="input-field">
                        <option value="">Select Relationship</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Spouse Father">Spouse Father</option>
                        <option value="Spouse Mother">Spouse Mother</option>
                    </select>
                    <span class="error-text">Relationship is required if provided.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input type="number" name="dependents[${dependentCount}][age]" class="input-field" min="0" max="120">
                    <span class="error-text">Age must be between 0 and 120 if provided.</span>
                </div>
            </div>
        `;
        dependentsList.appendChild(dependentDiv);
        dependentCount++;
        addAnotherDependentBtn.disabled = dependentCount >= MAX_DEPENDENTS;
    }

    function removeDependentEntry(button) {
        button.closest('.entry-card').remove();
        dependentCount--;
        addAnotherDependentBtn.disabled = dependentCount >= MAX_DEPENDENTS;
        if (dependentCount === 0) {
            dependentsDetails.style.display = 'none';
            addDependentBtn.style.display = 'inline-flex';
        }
        updateDependentLimit();
    }

    function updateDependentLimit() {
        dependentLimit.textContent = `${dependentCount}/${MAX_DEPENDENTS} Dependents`;
        dependentLimit.style.color = dependentCount === MAX_DEPENDENTS ? '#e74c3c' : '#7f8c8d';
    }

    // Form submission validation
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validate form
        if (!validateForm()) {
            errorMessage.textContent = 'Please fill out all required member fields correctly.';
            showPopup(errorPopup);
            startCountdown('error-countdown', 'add_member.php');
            return;
        }

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            let result;
            try {
                result = await response.json();
            } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                throw new Error('Invalid server response. Please try again.');
            }

            if (result.success) {
                successMessage.textContent = result.message;
                showPopup(successPopup);
                startCountdown('success-countdown', 'add_member.php');
            } else {
                errorMessage.textContent = result.message;
                showPopup(errorPopup);
                startCountdown('error-countdown', 'add_member.php');
            }
        } catch (error) {
            console.error('Submission error:', error);
            errorMessage.textContent = 'An unexpected error occurred: ' + error.message;
            showPopup(errorPopup);
            startCountdown('error-countdown', 'add_member.php');
        }
    });

    // Form validation function
    function validateForm() {
        let isValid = true;

        // Validate mandatory member fields
        const requiredFields = form.querySelectorAll('.input-field[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                const errorElement = field.nextElementSibling;
                if (errorElement && errorElement.classList.contains('error-text')) {
                    errorElement.classList.add('show');
                }
                isValid = false;
            } else {
                const errorElement = field.nextElementSibling;
                if (errorElement && errorElement.classList.contains('error-text')) {
                    errorElement.classList.remove('show');
                }
            }
        });

        // Additional validations for member fields
        const memberId = document.getElementById('member_id');
        if (memberId && (!/^\d+$/.test(memberId.value) || parseInt(memberId.value) <= 0)) {
            const errorElement = memberId.nextElementSibling;
            if (errorElement) errorElement.classList.add('show');
            isValid = false;
        }

        const contactNumber = document.getElementById('contact_number');
        if (contactNumber && !/^\+94\d{9}$/.test(contactNumber.value)) {
            const errorElement = contactNumber.nextElementSibling;
            if (errorElement) errorElement.classList.add('show');
            isValid = false;
        }

        const email = document.getElementById('email');
        if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            const errorElement = email.nextElementSibling;
            if (errorElement) errorElement.classList.add('show');
            isValid = false;
        }

        const contributionAmount = document.getElementById('contribution_amount');
        if (contributionAmount && (!contributionAmount.value || parseFloat(contributionAmount.value) <= 0)) {
            const errorElement = contributionAmount.nextElementSibling;
            if (errorElement) errorElement.classList.add('show');
            isValid = false;
        }

        // Validate spouse details (optional)
        const spouseName = document.getElementById('spouse_name');
        const spouseAge = document.getElementById('spouse_age');
        const spouseGender = document.getElementById('spouse_gender');
        if (spouseName && spouseName.value.trim()) {
            if (!spouseName.value.trim()) {
                const errorElement = spouseName.nextElementSibling;
                if (errorElement) errorElement.classList.add('show');
                isValid = false;
            }
            if (spouseAge.value && (parseInt(spouseAge.value) < 0 || parseInt(spouseAge.value) > 120)) {
                const errorElement = spouseAge.nextElementSibling;
                if (errorElement) errorElement.classList.add('show');
                isValid = false;
            }
        }

        // Validate children details (optional)
        const childrenEntries = document.querySelectorAll('#children-list .entry-card');
        childrenEntries.forEach((entry, index) => {
            const nameInput = entry.querySelector(`input[name="children[${index}][name]"]`);
            const ageInput = entry.querySelector(`input[name="children[${index}][age]"]`);
            const genderInput = entry.querySelector(`select[name="children[${index}][gender]"]`);

            if (nameInput.value.trim() || ageInput.value || genderInput.value) {
                if (!nameInput.value.trim()) {
                    const errorElement = nameInput.nextElementSibling;
                    if (errorElement) errorElement.classList.add('show');
                    isValid = false;
                }
                if (!ageInput.value || parseInt(ageInput.value) < 0 || parseInt(ageInput.value) > 120) {
                    const errorElement = ageInput.nextElementSibling;
                    if (errorElement) errorElement.classList.add('show');
                    isValid = false;
                }
                if (!genderInput.value) {
                    const errorElement = genderInput.nextElementSibling;
                    if (errorElement) errorElement.classList.add('show');
                    isValid = false;
                }
            }
        });

        // Validate dependents details (optional)
        const dependentsEntries = document.querySelectorAll('#dependents-list .entry-card');
        dependentsEntries.forEach((entry, index) => {
            const nameInput = entry.querySelector(`input[name="dependents[${index}][name]"]`);
            const relationshipInput = entry.querySelector(`select[name="dependents[${index}][relationship]"]`);
            const ageInput = entry.querySelector(`input[name="dependents[${index}][age]"]`);

            if (nameInput.value.trim() || relationshipInput.value || ageInput.value) {
                if (!nameInput.value.trim()) {
                    const errorElement = nameInput.nextElementSibling;
                    if (errorElement) errorElement.classList.add('show');
                    isValid = false;
                }
                if (!relationshipInput.value) {
                    const errorElement = relationshipInput.nextElementSibling;
                    if (errorElement) errorElement.classList.add('show');
                    isValid = false;
                }
                if (ageInput.value && (parseInt(ageInput.value) < 0 || parseInt(ageInput.value) > 120)) {
                    const errorElement = ageInput.nextElementSibling;
                    if (errorElement) errorElement.classList.add('show');
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    updateProgress();
});
</script>
</body>
</html>