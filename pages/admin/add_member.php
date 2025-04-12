<?php
define('APP_START', true);
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log session state
error_log("add_member.php: Session: " . print_r($_SESSION, true));

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
try {
    $last_member = $member->getLastMember();
    $last_id = $last_member ? (int)$last_member['member_id'] : 0;
    $next_id = $last_id + 1;
} catch (Exception $e) {
    error_log("add_member.php: Error generating member ID: " . $e->getMessage());
    $next_id = 1; // Fallback
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("add_member.php: POST received: " . print_r($_POST, true));
    try {
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

        // Validate inputs
        if (!ctype_digit($member_id) || (int)$member_id <= 0) {
            throw new Exception("Membership ID must be a positive number.");
        }
        if (strlen($nic_number) < 9) {
            throw new Exception("NIC number must be at least 9 characters.");
        }
        if (!preg_match("/^\+94\d{9}$/", $contact_number)) {
            throw new Exception("Contact number must be in the format +94XXXXXXXXX.");
        }
        if (empty($full_name) || empty($address) || !$contribution_amount) {
            throw new Exception("Required fields (Name, Address, Contribution) cannot be empty.");
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        if (empty($date_of_birth) || empty($gender) || empty($date_of_joining) ||
            empty($membership_type) || empty($payment_status) || empty($member_status)) {
            throw new Exception("All required fields must be filled.");
        }

        // Check member_id uniqueness
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE member_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to check member ID uniqueness: " . $conn->error);
        }
        $stmt->bind_param("s", $member_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()[0] > 0) {
            $stmt->close();
            throw new Exception("Membership ID '$member_id' already exists.");
        }
        $stmt->close();

        // Add member
        if (!$member->addMember($member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status)) {
            throw new Exception("Failed to add member to database.");
        }

        // Get new member's ID
        $stmt = $conn->prepare("SELECT id FROM members WHERE member_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to retrieve new member ID: " . $conn->error);
        }
        $stmt->bind_param("s", $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) {
            $stmt->close();
            throw new Exception("New member not found after insertion.");
        }
        $new_member_id = $result['id'];
        $stmt->close();

        // Add family details if provided
        if ($spouse_name || $children_info || $dependents_info) {
            if (!$family->addFamilyDetails($new_member_id, $spouse_name, $children_info, $dependents_info)) {
                throw new Exception("Failed to add family details.");
            }
        }

        $success = "Member '$full_name' added successfully!";
        error_log("add_member.php: Success: $success");
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("add_member.php: Error: $error");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #F97316;
            --orange-dark: #C2410C;
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
            line-height: 1.5;
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
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: var(--primary-orange);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--orange-dark);
        }

        .btn-cancel {
            background: var(--text-secondary);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #4b5563;
        }

        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            width: 100%;
            transition: border-color 0.2s ease;
        }

        .input-field:focus {
            border-color: var(--primary-orange);
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .section-header {
            background: var(--primary-orange);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px 6px 0 0;
            font-weight: 600;
        }

        .error-text {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        .form-group {
            position: relative;
        }

        .form-group i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #10b981;
            display: none;
        }

        .form-group.valid i {
            display: block;
        }

        .animate-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-width: 400px;
            width: 90%;
            animation: popupFadeIn 0.3s ease-out;
        }

        .popup.show {
            display: block;
        }

        .popup-success {
            border-left: 4px solid #10b981;
        }

        .popup-error {
            border-left: 4px solid #dc2626;
        }

        .popup-cancel {
            border-left: 4px solid #6B7280;
        }

        .popup-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .popup-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .popup-message {
            color: #4B5563;
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .popup-overlay.show {
            display: block;
        }

        @keyframes popupFadeIn {
            from { opacity: 0; transform: translate(-50%, -60%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 16px;
            }
            .sidebar.expanded ~ .main-content {
                margin-left: calc(var(--sidebar-expanded) + 16px);
            }
            .popup {
                width: 95%;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<div class="flex min-h-screen pt-20">
    <?php include __DIR__ . '/../../includes/sidepanel.php'; ?>

    <main class="main-content p-6 flex-1">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-semibold text-gray-900 mb-6 animate-in">Add New Member</h1>

            <form method="POST" class="card space-y-6 animate-in" id="add-member-form">
                <div>
                    <div class="section-header">Member Information</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="member_id" class="block text-sm font-medium mb-1">Membership ID <span class="text-red-500">*</span></label>
                            <input type="text" id="member_id" name="member_id" value="<?php echo htmlspecialchars($next_id); ?>" class="input-field" required aria-describedby="member_id-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="member_id-error">Must be a positive number.</span>
                        </div>
                        <div class="form-group">
                            <label for="full_name" class="block text-sm font-medium mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="input-field" required aria-describedby="full_name-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="full_name-error">Full name is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth" class="block text-sm font-medium mb-1">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="input-field" max="<?php echo date('Y-m-d'); ?>" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="gender" class="block text-sm font-medium mb-1">Gender <span class="text-red-500">*</span></label>
                            <select id="gender" name="gender" class="input-field" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="nic_number" class="block text-sm font-medium mb-1">NIC Number <span class="text-red-500">*</span></label>
                            <input type="text" id="nic_number" name="nic_number" class="input-field" required aria-describedby="nic_number-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="nic_number-error">Minimum 9 characters.</span>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="address" class="block text-sm font-medium mb-1">Address <span class="text-red-500">*</span></label>
                            <input type="text" id="address" name="address" class="input-field" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="contact_number" class="block text-sm font-medium mb-1">Contact Number <span class="text-red-500">*</span></label>
                            <input type="text" id="contact_number" name="contact_number" class="input-field" placeholder="+94XXXXXXXXX" required aria-describedby="contact_number-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="contact_number-error">Format: +94 followed by 9 digits.</span>
                        </div>
                        <div class="form-group">
                            <label for="email" class="block text-sm font-medium mb-1">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="input-field">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="occupation" class="block text-sm font-medium mb-1">Occupation (Optional)</label>
                            <input type="text" id="occupation" name="occupation" class="input-field">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="section-header">Membership Details</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="date_of_joining" class="block text-sm font-medium mb-1">Date of Joining <span class="text-red-500">*</span></label>
                            <input type="date" id="date_of_joining" name="date_of_joining" class="input-field" value="<?php echo date('Y-m-d'); ?>" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="membership_type" class="block text-sm font-medium mb-1">Membership Type <span class="text-red-500">*</span></label>
                            <select id="membership_type" name="membership_type" class="input-field" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Individual">Individual</option>
                                <option value="Family">Family</option>
                                <option value="Senior Citizen">Senior Citizen</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="contribution_amount" class="block text-sm font-medium mb-1">Contribution (LKR) <span class="text-red-500">*</span></label>
                            <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" min="0" class="input-field" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="payment_status" class="block text-sm font-medium mb-1">Payment Status <span class="text-red-500">*</span></label>
                            <select id="payment_status" name="payment_status" class="input-field" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="member_status" class="block text-sm font-medium mb-1">Member Status <span class="text-red-500">*</span></label>
                            <select id="member_status" name="member_status" class="input-field" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Active">Active</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Resigned">Resigned</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="section-header">Family Details (Optional)</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="spouse_name" class="block text-sm font-medium mb-1">Spouse's Name</label>
                            <input type="text" id="spouse_name" name="spouse_name" class="input-field" placeholder="e.g., Priya Fernando">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="children_info" class="block text-sm font-medium mb-1">Children (Name:Age)</label>
                            <input type="text" id="children_info" name="children_info" class="input-field" placeholder="e.g., Sahan:12, Nimasha:8">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="dependents_info" class="block text-sm font-medium mb-1">Dependents (Relation:Name)</label>
                            <input type="text" id="dependents_info" name="dependents_info" class="input-field" placeholder="e.g., Mother:Sunila">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center space-x-4">
                    <button type="submit" class="btn-primary">Add Member</button>
                    <a href="#" class="btn-cancel" id="cancel-button">Cancel</a>
                </div>
            </form>

            <p class="text-center mt-6"><a href="dashboard.php" class="text-[var(--primary-orange)] hover:underline">Back to Dashboard</a></p>
        </div>
    </main>
</div>

<!-- Popup Overlay -->
<div class="popup-overlay" id="popup-overlay"></div>

<!-- Success Popup -->
<div class="popup popup-success" id="success-popup">
    <div class="flex justify-center">
        <i class="fas fa-check-circle popup-icon text-green-500"></i>
    </div>
    <h3 class="popup-title text-center">Success!</h3>
    <p class="popup-message text-center"><?php echo htmlspecialchars($success); ?></p>
    <p class="text-center text-sm text-gray-500 mt-2">Redirecting in <span id="success-countdown">3</span> seconds...</p>
</div>

<!-- Error Popup -->
<div class="popup popup-error" id="error-popup">
    <div class="flex justify-center">
        <i class="fas fa-exclamation-circle popup-icon text-red-500"></i>
    </div>
    <h3 class="popup-title text-center">Error</h3>
    <p class="popup-message text-center"><?php echo htmlspecialchars($error); ?></p>
    <p class="text-center text-sm text-gray-500 mt-2">Redirecting in <span id="error-countdown">3</span> seconds...</p>
</div>

<!-- Cancel Popup -->
<div class="popup popup-cancel" id="cancel-popup">
    <div class="flex justify-center">
        <i class="fas fa-times-circle popup-icon text-gray-500"></i>
    </div>
    <h3 class="popup-title text-center">Cancelled</h3>
    <p class="popup-message text-center">The operation has been cancelled.</p>
    <p class="text-center text-sm text-gray-500 mt-2">Redirecting in <span id="cancel-countdown">3</span> seconds...</p>
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

        // Form validation
        const form = document.getElementById('add-member-form');
        const inputs = form.querySelectorAll('.input-field');

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const group = input.closest('.form-group');
                const error = group.querySelector('.error-text');

                if (input.id === 'member_id' && (!/^\d+$/.test(input.value) || parseInt(input.value) <= 0)) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else if (input.id === 'full_name' && !input.value.trim()) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else if (input.id === 'nic_number' && input.value.length < 9) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else if (input.id === 'contact_number' && !/^\+94\d{9}$/.test(input.value)) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else if (input.id === 'email' && input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else if (input.required && !input.value) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else {
                    error.style.display = 'none';
                    group.classList.add('valid');
                }
            });
        });

        // Show popups if messages exist
        <?php if ($success): ?>
        showPopup(successPopup);
        startCountdown('success-countdown');
        setTimeout(() => { window.location.href = 'add_member.php'; }, 3000);
        <?php elseif ($error): ?>
        showPopup(errorPopup);
        startCountdown('error-countdown');
        setTimeout(() => { window.location.href = 'add_member.php'; }, 3000);
        <?php endif; ?>

        // Cancel button
        if (cancelButton) {
            cancelButton.addEventListener('click', (e) => {
                e.preventDefault();
                showPopup(cancelPopup);
                startCountdown('cancel-countdown');
                setTimeout(() => { window.location.href = 'add_member.php'; }, 3000);
            });
        }

        function showPopup(popup) {
            popupOverlay.classList.add('show');
            popup.classList.add('show');
        }

        function startCountdown(elementId) {
            let timeLeft = 3;
            const countdown = document.getElementById(elementId);
            const interval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                }
            }, 1000);
        }
    });
</script>
</body>
</html>