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
    echo '<link rel="stylesheet" href="../../assets/css/add_member.css">';
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

// For non-AJAX requests, continue with normal page rendering
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
    // Pass PHP variables to JavaScript
    window.successMsg = '<?php echo $js_success; ?>';
    window.errorMsg = '<?php echo $js_error; ?>';
</script>
<script src="../../assets/js/add_member.js"></script>
</body>
</html>