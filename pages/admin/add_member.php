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
require_once '../../classes/Database.php';

$member = new Member();
$family = new Family();
$error = $success = '';

// Auto-generate member ID
$last_member = $member->getLastMember();
$last_id = $last_member ? (int)substr($last_member['member_id'], 3) : 0;
$next_id = sprintf("MS-%03d", $last_id + 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = trim($_POST['member_id']);
    $full_name = trim($_POST['full_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $nic_number = trim($_POST['nic_number']);
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']) ?: null;
    $occupation = trim($_POST['occupation']) ?: null;
    $date_of_joining = $_POST['date_of_joining'];
    $membership_type = $_POST['membership_type'];
    $contribution_amount = (float)$_POST['contribution_amount'];
    $payment_status = $_POST['payment_status'];
    $member_status = $_POST['member_status'];
    $spouse_name = trim($_POST['spouse_name']) ?: null;
    $children_info = trim($_POST['children_info']) ?: null;
    $dependents_info = trim($_POST['dependents_info']) ?: null;

    $conn = (new Database())->getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE member_id = ?");
    $stmt->bind_param("s", $member_id);
    $stmt->execute();
    $member_exists = $stmt->get_result()->fetch_row()[0] > 0;

    if (!preg_match("/^MS-\d{3,}$/", $member_id)) {
        $error = "Membership ID must be in the format 'MS-' followed by at least 3 digits (e.g., MS-001).";
    } elseif ($member_exists) {
        $error = "Membership ID '$member_id' already exists.";
    } elseif (strlen($nic_number) < 9) {
        $error = "NIC number must be at least 9 characters.";
    } elseif (!preg_match("/^\+94\d{9}$/", $contact_number)) {
        $error = "Contact number must be in the format +94XXXXXXXXX.";
    } elseif (empty($full_name) || empty($address) || !$contribution_amount) {
        $error = "Required fields (Name, Address, Contribution) cannot be empty.";
    } else {
        if ($member->addMember($member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status)) {
            $stmt = $conn->prepare("SELECT id FROM members WHERE member_id = ?");
            $stmt->bind_param("s", $member_id);
            $stmt->execute();
            $new_member_id = $stmt->get_result()->fetch_assoc()['id'];

            if ($spouse_name || $children_info || $dependents_info) {
                $family->addFamilyDetails($new_member_id, $spouse_name, $children_info, $dependents_info);
            }

            $success = "Member '$full_name' added successfully! Redirecting in <span id='countdown'>2</span> seconds...";
            header("Refresh: 2; url=dashboard.php");
        } else {
            $error = "Failed to add member. Please try again.";
        }
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
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
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
            transform: translateY(-1px);
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
            transform: translateY(-1px);
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

        .input-field:valid {
            border-color: #10b981;
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
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 16px;
            }
            .sidebar.expanded ~ .main-content {
                margin-left: calc(var(--sidebar-expanded) + 16px);
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen pt-20">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content p-6 flex-1">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-semibold text-gray-900 mb-6 animate-in">Add New Member</h1>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center animate-in">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 flex items-center animate-in">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="card space-y-6 animate-in" id="add-member-form">
                <div>
                    <div class="section-header">Member Information</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="member_id" class="block text-sm font-medium mb-1">Membership ID <span class="text-red-500">*</span></label>
                            <input type="text" id="member_id" name="member_id" value="<?php echo htmlspecialchars($next_id); ?>" class="input-field" required aria-describedby="member_id-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="member_id-error">Format: MS- followed by 3+ digits.</span>
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
                    <a href="dashboard.php" class="btn-cancel">Cancel</a>
                </div>
            </form>

            <p class="text-center mt-6"><a href="dashboard.php" class="text-[var(--primary-orange)] hover:underline">Back to Dashboard</a></p>
        </div>
    </main>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');

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

        const form = document.getElementById('add-member-form');
        const inputs = form.querySelectorAll('.input-field');

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const group = input.closest('.form-group');
                const error = group.querySelector('.error-text');

                if (input.id === 'member_id' && !/^MS-\d{3,}$/.test(input.value)) {
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
                } else if (input.required && !input.value) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                } else {
                    error.style.display = 'none';
                    group.classList.add('valid');
                }
            });
        });

        form.addEventListener('submit', (e) => {
            let valid = true;
            inputs.forEach(input => {
                const group = input.closest('.form-group');
                const error = group.querySelector('.error-text');

                if (input.required && !input.value) {
                    error.style.display = 'block';
                    group.classList.remove('valid');
                    valid = false;
                }
            });

            if (!valid) e.preventDefault();
        });

        if (document.getElementById('countdown')) {
            let timeLeft = 2;
            const countdown = document.getElementById('countdown');
            setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
            }, 1000);
        }
    });
</script>
</body>
</html>