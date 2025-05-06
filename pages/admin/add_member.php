<?php
define('APP_START', true);
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
        $spouse_name = trim($_POST['spouse_name'] ?? '') ?: null;
        $children_info = trim($_POST['children_info'] ?? '') ?: null;
        $dependents_info = trim($_POST['dependents_info'] ?? '') ?: null;

        // Basic validation
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

            // Add family details if provided
            if ($spouse_name || $children_info || $dependents_info) {
                if (!$family->addFamilyDetails($new_member_id, $spouse_name, $children_info, $dependents_info)) {
                    throw new Exception("Failed to add family details.");
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
            throw $e;
        } finally {
            $db->closeConnection();
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("add_member.php: Error: $error");
}

// Escape messages for JavaScript
$js_success = json_encode($success);
$js_error = json_encode($error);
error_log("JS Success: $js_success");
error_log("JS Error: $js_error");
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

        /* Form */
        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-header {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            position: relative;
        }

        .section-header::after {
            content: '';
            width: 4rem;
            height: 4px;
            background: var(--primary);
            position: absolute;
            bottom: -0.75rem;
            left: 0;
            border-radius: 2px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.75rem;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.6rem;
            transition: color 0.2s ease;
        }

        .form-group label span.required {
            color: var(--error);
            font-weight: 600;
        }

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

        .input-field:invalid:not(:placeholder-shown) {
            border-color: var(--error);
        }

        .input-field.valid {
            border-color: var(--success);
        }

        .input-field ~ .ri-check-line {
            position: absolute;
            right: 1.25rem;
            top: 2.9rem;
            color: var(--success);
            display: none;
        }

        .form-group.valid .ri-check-line {
            display: block;
        }

        .error-text {
            font-size: 0.8rem;
            color: var(--error);
            margin-top: 0.3rem;
            display: none;
        }

        .error-text.show {
            display: block;
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

        .btn-secondary {
            background: var(--secondary);
            color: #FFFFFF;
        }

        .btn-secondary:hover {
            background: #374151;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(75, 85, 99, 0.2);
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
            height: 120px;
        }

        /* Popups */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(5px);
            z-index: 999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s ease;
        }

        .popup-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            z-index: 1000;
            max-width: 450px;
            width: 90%;
            opacity: 0;
            pointer-events: none;
            transition: all 0.4s ease;
        }

        .popup.show {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
            pointer-events: auto;
        }

        .popup-success {
            border-left: 5px solid var(--success);
        }

        .popup-error {
            border-left: 5px solid var(--error);
        }

        .popup-cancel {
            border-left: 5px solid var(--secondary);
        }

        .popup-icon {
            font-size: 3rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .popup-title {
            font-size: 1.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.75rem;
        }

        .popup-message {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.5;
        }

        .popup-countdown {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 1.25rem;
        }

        /* Progress Indicator */
        .progress-bar {
            position: sticky;
            top: var(--header-height);
            height: 5px;
            background: var(--primary);
            width: 0;
            border-radius: 2px;
            transition: width 0.4s ease;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(249, 115, 22, 0.2);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .popup {
                width: 95%;
                padding: 2rem;
            }

            .section-header {
                font-size: 1.25rem;
            }

            .card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.85rem;
            }

            .popup-title {
                font-size: 1.5rem;
            }

            .popup-icon {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include __DIR__ . '/../../includes/sidepanel.php'; ?>

    <main class="main-content">
        <div class="container">
            <h1 class="text-2xl md:text-3xl font-bold mb-8 animate-in">Add New Member</h1>

            <div class="progress-bar" id="progress-bar"></div>

            <form method="POST" class="card" id="add-member-form">
                <!-- Member Information -->
                <div class="form-section">
                    <h2 class="section-header">Member Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="member_id">Membership ID <span class="required">*</span></label>
                            <input type="text" id="member_id" name="member_id" value="<?php echo htmlspecialchars($next_id); ?>" class="input-field" required aria-describedby="member_id-error">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="member_id-error">Must be a positive number.</span>
                        </div>
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="input-field" required aria-describedby="full_name-error">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="full_name-error">Full name is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="input-field" max="<?php echo date('Y-m-d'); ?>" required>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="date_of_birth-error">Date of birth is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender <span class="required">*</span></label>
                            <select id="gender" name="gender" class="input-field" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="gender-error">Gender is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="nic_number">NIC Number <span class="required">*</span></label>
                            <input type="text" id="nic_number" name="nic_number" class="input-field" required aria-describedby="nic_number-error">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="nic_number-error">NIC number is required.</span>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="address">Address <span class="required">*</span></label>
                            <input type="text" id="address" name="address" class="input-field" required>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="address-error">Address is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number <span class="required">*</span></label>
                            <input type="text" id="contact_number" name="contact_number" class="input-field" placeholder="+94XXXXXXXXX" required aria-describedby="contact_number-error">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="contact_number-error">Format: +94 followed by 9 digits.</span>
                        </div>
                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="input-field">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="email-error">Invalid email format.</span>
                        </div>
                        <div class="form-group">
                            <label for="occupation">Occupation (Optional)</label>
                            <input type="text" id="occupation" name="occupation" class="input-field">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="occupation-error"></span>
                        </div>
                    </div>
                </div>

                <!-- Membership Details -->
                <div class="form-section">
                    <h2 class="section-header">Membership Details</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_of_joining">Date of Joining <span class="required">*</span></label>
                            <input type="date" id="date_of_joining" name="date_of_joining" class="input-field" value="<?php echo date('Y-m-d'); ?>" required>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="date_of_joining-error">Date of joining is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="membership_type">Membership Type <span class="required">*</span></label>
                            <select id="membership_type" name="membership_type" class="input-field" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="Individual">Individual</option>
                                <option value="Family">Family</option>
                                <option value="Senior Citizen">Senior Citizen</option>
                            </select>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="membership_type-error">Membership type is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="contribution_amount">Contribution (LKR) <span class="required">*</span></label>
                            <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" min="0" class="input-field" required>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="contribution_amount-error">Must be greater than zero.</span>
                        </div>
                        <div class="form-group">
                            <label for="payment_status">Payment Status <span class="required">*</span></label>
                            <select id="payment_status" name="payment_status" class="input-field" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="payment_status-error">Payment status is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="member_status">Member Status <span class="required">*</span></label>
                            <select id="member_status" name="member_status" class="input-field" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Resigned">Resigned</option>
                            </select>
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="member_status-error">Member status is required.</span>
                        </div>
                    </div>
                </div>

                <!-- Family Details -->
                <div class="form-section">
                    <h2 class="section-header">Family Details (Optional)</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="spouse_name">Spouse's Name</label>
                            <input type="text" id="spouse_name" name="spouse_name" class="input-field" placeholder="e.g., Priya Fernando">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="spouse_name-error"></span>
                        </div>
                        <div class="form-group">
                            <label for="children_info">Children (Name:Age)</label>
                            <input type="text" id="children_info" name="children_info" class="input-field" placeholder="e.g., Sahan:12, Nimasha:8">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="children_info-error"></span>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="dependents_info">Dependents (Relation:Name)</label>
                            <input type="text" id="dependents_info" name="dependents_info" class="input-field" placeholder="e.g., Mother:Sunila">
                            <i class="ri-check-line"></i>
                            <span class="error-text" id="dependents_info-error"></span>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-center gap-6 mt-8">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-user-add-line mr-2"></i> Add Member
                    </button>
                    <a href="#" class="btn btn-secondary" id="cancel-button">
                        <i class="ri-close-line mr-2"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</div>

<!-- Popup Overlay -->
<div class="popup-overlay" id="popup-overlay"></div>

<!-- Success Popup -->
<div class="popup popup-success" id="success-popup">
    <div class="popup-icon"><i class="ri-checkbox-circle-fill text-[var(--success)]"></i></div>
    <h3 class="popup-title">Success!</h3>
    <p class="popup-message" id="success-message"></p>
    <p class="popup-countdown">Redirecting in <span id="success-countdown">3</span> seconds...</p>
</div>

<!-- Error Popup -->
<div class="popup popup-error" id="error-popup">
    <div class="popup-icon"><i class="ri-error-warning-fill text-[var(--error)]"></i></div>
    <h3 class="popup-title">Error</h3>
    <p class="popup-message" id="error-message"></p>
    <p class="popup-countdown">Redirecting in <span id="error-countdown">3</span> seconds...</p>
</div>

<!-- Cancel Popup -->
<div class="popup popup-cancel" id="cancel-popup">
    <div class="popup-icon"><i class="ri-close-circle-fill text-[var(--secondary)]"></i></div>
    <h3 class="popup-title">Cancelled</h3>
    <p class="popup-message">The operation has been cancelled.</p>
    <p class="popup-countdown">Redirecting in <span id="cancel-countdown">3</span> seconds...</p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const cancelButton = document.getElementById('cancel-button');
        const popupOverlay = document.getElementById('popup-overlay');
        const successPopup = document.getElementById('success-popup');
        const errorPopup = document.getElementById('error-popup');
        const cancelPopup = document.getElementById('cancel-popup');
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        const form = document.getElementById('add-member-form');
        const inputs = form.querySelectorAll('.input-field');
        const progressBar = document.getElementById('progress-bar');

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

        // Form validation and progress bar
        const updateProgress = () => {
            const requiredInputs = form.querySelectorAll('.input-field[required]');
            let filled = 0;
            requiredInputs.forEach(input => {
                if (input.value.trim()) filled++;
            });
            const progress = (filled / requiredInputs.length) * 100;
            progressBar.style.width = `${progress}%`;
        };

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const group = input.closest('.form-group');
                const error = group.querySelector('.error-text');
                let isValid = true;

                // Validate
                if (input.id === 'member_id' && (!/^\d+$/.test(input.value) || parseInt(input.value) <= 0)) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'full_name' && !input.value.trim()) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'nic_number' && !input.value.trim()) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'contact_number' && !input.value.trim()) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'email' && input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'address' && !input.value.trim()) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'date_of_birth' && !input.value) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'gender' && !input.value) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'date_of_joining' && !input.value) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'membership_type' && !input.value) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'contribution_amount' && (!input.value || parseFloat(input.value) <= 0)) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'payment_status' && !input.value) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else if (input.id === 'member_status' && !input.value) {
                    error.classList.add('show');
                    group.classList.remove('valid');
                    isValid = false;
                } else {
                    error.classList.remove('show');
                    group.classList.add('valid');
                }

                console.log(`Input ${input.id}: valid=${isValid}`);
                updateProgress();
            });
        });

        // Initial progress update
        updateProgress();

        // Show popups if messages exist
        const successMsg = <?php echo $js_success; ?>;
        const errorMsg = <?php echo $js_error; ?>;

        console.log('Success Msg:', successMsg);
        console.log('Error Msg:', errorMsg);

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
        if (cancelButton) {
            cancelButton.addEventListener('click', (e) => {
                e.preventDefault();
                showPopup(cancelPopup);
                startCountdown('cancel-countdown', 'add_member.php');
            });
        }

        function showPopup(popup) {
            popupOverlay.classList.add('show');
            popup.classList.add('show');
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
    });
</script>
</body>
</html>