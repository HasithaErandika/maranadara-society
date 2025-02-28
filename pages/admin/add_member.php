<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Family.php';

$member = new Member();
$family = new Family();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    $contribution_amount = $_POST['contribution_amount'];
    $payment_status = $_POST['payment_status'];
    $member_status = $_POST['member_status'];
    $spouse_name = trim($_POST['spouse_name']) ?: null;
    $children_info = trim($_POST['children_info']) ?: null;
    $dependents_info = trim($_POST['dependents_info']) ?: null;

    // Server-side validation
    $conn = (new Database())->getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE member_id = ?");
    $stmt->bind_param("s", $member_id);
    $stmt->execute();
    $member_exists = $stmt->get_result()->fetch_row()[0] > 0;

    if (!preg_match("/^MS-\d{3,}$/", $member_id)) {
        $error = "Membership ID must start with 'MS-' followed by at least 3 digits (e.g., MS-001).";
    } elseif ($member_exists) {
        $error = "Membership ID '$member_id' already exists.";
    } elseif (strlen($nic_number) < 9) {
        $error = "NIC number must be at least 9 characters.";
    } elseif (!preg_match("/^\+94\d{9}$/", $contact_number)) {
        $error = "Contact number must be in the format +94XXXXXXXXX.";
    } else {
        if ($member->addMember($member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status)) {
            $stmt = $conn->prepare("SELECT id FROM members WHERE member_id = ?");
            $stmt->bind_param("s", $member_id);
            $stmt->execute();
            $member_result = $stmt->get_result()->fetch_assoc();
            $new_member_id = $member_result['id'];

            if ($spouse_name || $children_info || $dependents_info) {
                $family->addFamilyDetails($new_member_id, $spouse_name, $children_info, $dependents_info);
            }

            $success = "Member '$full_name' added successfully! Redirecting in <span id='countdown'>2</span> seconds...";
            header("Refresh: 2; url=dashboard.php");
        } else {
            $error = "Error adding member.";
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
    <style>
        :root {
            --bg-color: #f9fafb;
            --text-color: #111827;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --btn-bg: #ea580c;
            --btn-hover: #c2410c;
            --border-color: #e5e7eb;
            --accent-color: #f97316;
            --sidebar-width: 72px;
            --sidebar-expanded: 260px;
        }
        [data-theme="dark"] {
            --bg-color: #111827;
            --text-color: #f9fafb;
            --card-bg: #1f2937;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #f97316;
            --btn-hover: #ea580c;
            --border-color: #374151;
            --accent-color: #fb923c;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            color: white;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: translateY(-2px);
        }
        .btn-cancel {
            background-color: #6b7280;
            color: white;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-cancel:hover {
            background-color: #4b5563;
            transform: translateY(-2px);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            transition: width 0.3s ease;
            position: fixed;
            top: 84px;
            left: 16px;
            height: calc(100vh - 104px);
            overflow: hidden;
            z-index: 20;
        }
        .sidebar:hover, .sidebar.expanded {
            width: var(--sidebar-expanded);
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--text-color);
            transition: all 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background-color: var(--accent-color);
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 16px;
        }
        .sidebar-item span {
            display: none;
            white-space: nowrap;
        }
        .sidebar:hover .sidebar-item span, .sidebar.expanded .sidebar-item span {
            display: inline;
        }
        .main-content {
            margin-left: calc(var(--sidebar-width) + 32px);
            transition: margin-left 0.3s ease;
        }
        .sidebar:hover ~ .main-content, .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 32px);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            width: 100%;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
            outline: none;
        }
        .input-field:valid {
            border-color: #10b981;
        }
        .section-header {
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px 8px 0 0;
            font-size: 1.125rem;
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
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: 0;
            }
            .sidebar.expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
            .sidebar:hover {
                width: var(--sidebar-width);
            }
            .sidebar:hover ~ .main-content {
                margin-left: calc(var(--sidebar-width) + 32px);
            }
            .card {
                padding: 1.5rem;
            }
        }
        .theme-toggle {
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            background-color: var(--border-color);
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="theme-toggle" id="theme-toggle" aria-label="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <button class="md:hidden text-orange-600" id="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <a href="../login.php?logout=1" class="text-white btn-admin" aria-label="Logout">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-6">
            <li class="sidebar-item"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="sidebar-item active"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Form Content -->
    <main class="flex-1 p-8 main-content" id="main-content">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl font-extrabold mb-8 text-orange-600">Add New Member</h1>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-8 flex items-center animate-fade-in">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-8 flex items-center animate-fade-in">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="card space-y-8" id="add-member-form">
                <!-- Member Information -->
                <div>
                    <div class="section-header">Member Information</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="member_id" class="block text-sm font-semibold mb-2">Membership ID <span class="text-red-500">*</span></label>
                            <input type="text" id="member_id" name="member_id" class="input-field" placeholder="e.g., MS-001" required aria-describedby="member_id-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="member_id-error">Must be 'MS-' followed by at least 3 digits.</span>
                        </div>
                        <div class="form-group">
                            <label for="full_name" class="block text-sm font-semibold mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="input-field" required aria-describedby="full_name-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="full_name-error">Full name is required.</span>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth" class="block text-sm font-semibold mb-2">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="input-field" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="gender" class="block text-sm font-semibold mb-2">Gender <span class="text-red-500">*</span></label>
                            <select id="gender" name="gender" class="input-field" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="nic_number" class="block text-sm font-semibold mb-2">NIC Number <span class="text-red-500">*</span></label>
                            <input type="text" id="nic_number" name="nic_number" class="input-field" required aria-describedby="nic_number-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="nic_number-error">Must be at least 9 characters.</span>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="address" class="block text-sm font-semibold mb-2">Address <span class="text-red-500">*</span></label>
                            <input type="text" id="address" name="address" class="input-field" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="contact_number" class="block text-sm font-semibold mb-2">Contact Number <span class="text-red-500">*</span></label>
                            <input type="text" id="contact_number" name="contact_number" class="input-field" placeholder="+94XXXXXXXXX" required aria-describedby="contact_number-error">
                            <i class="fas fa-check"></i>
                            <span class="error-text" id="contact_number-error">Format: +94 followed by 9 digits.</span>
                        </div>
                        <div class="form-group">
                            <label for="email" class="block text-sm font-semibold mb-2">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="input-field" placeholder="example@domain.com">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="occupation" class="block text-sm font-semibold mb-2">Occupation (Optional)</label>
                            <input type="text" id="occupation" name="occupation" class="input-field">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <!-- Membership Details -->
                <div>
                    <div class="section-header">Membership Details</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="date_of_joining" class="block text-sm font-semibold mb-2">Date of Joining <span class="text-red-500">*</span></label>
                            <input type="date" id="date_of_joining" name="date_of_joining" class="input-field" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="membership_type" class="block text-sm font-semibold mb-2">Membership Type <span class="text-red-500">*</span></label>
                            <select id="membership_type" name="membership_type" class="input-field" required>
                                <option value="" disabled selected>Select Type</option>
                                <option value="Individual">Individual</option>
                                <option value="Family">Family</option>
                                <option value="Senior Citizen">Senior Citizen</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="contribution_amount" class="block text-sm font-semibold mb-2">Contribution (LKR) <span class="text-red-500">*</span></label>
                            <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" class="input-field" required>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="payment_status" class="block text-sm font-semibold mb-2">Payment Status <span class="text-red-500">*</span></label>
                            <select id="payment_status" name="payment_status" class="input-field" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="member_status" class="block text-sm font-semibold mb-2">Member Status <span class="text-red-500">*</span></label>
                            <select id="member_status" name="member_status" class="input-field" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="Active">Active</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Resigned">Resigned</option>
                            </select>
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <!-- Family Details -->
                <div>
                    <div class="section-header">Family Details (Optional)</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                        <div class="form-group">
                            <label for="spouse_name" class="block text-sm font-semibold mb-2">Spouse's Name</label>
                            <input type="text" id="spouse_name" name="spouse_name" class="input-field" placeholder="e.g., Priya Fernando">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group">
                            <label for="children_info" class="block text-sm font-semibold mb-2">Children (Name:Age)</label>
                            <input type="text" id="children_info" name="children_info" class="input-field" placeholder="e.g., Sahan:12, Nimasha:8">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="form-group md:col-span-2">
                            <label for="dependents_info" class="block text-sm font-semibold mb-2">Dependents (Relation:Name)</label>
                            <input type="text" id="dependents_info" name="dependents_info" class="input-field" placeholder="e.g., Mother:Sunila">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-center space-x-4">
                    <button type="submit" class="btn-admin font-semibold">Add Member</button>
                    <a href="dashboard.php" class="btn-cancel font-semibold">Cancel</a>
                </div>
            </form>
            <p class="text-center mt-6"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-6 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400 text-sm">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const themeToggle = document.getElementById('theme-toggle');
    const form = document.getElementById('add-member-form');

    // Sidebar toggle for mobile
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('expanded');
    });

    // Theme toggle
    themeToggle.addEventListener('click', () => {
        document.body.dataset.theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
        themeToggle.querySelector('i').classList.toggle('fa-moon');
        themeToggle.querySelector('i').classList.toggle('fa-sun');
    });

    // Prevent hover on mobile
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => e.preventDefault());
    }

    // Real-time validation
    const inputs = form.querySelectorAll('.input-field');
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            const group = input.closest('.form-group');
            const error = input.nextElementSibling.nextElementSibling;

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

    // Form submission validation
    form.addEventListener('submit', (e) => {
        let valid = true;
        inputs.forEach(input => {
            const group = input.closest('.form-group');
            const error = input.nextElementSibling.nextElementSibling;

            if (input.required && !input.value) {
                error.style.display = 'block';
                group.classList.remove('valid');
                valid = false;
            }
        });

        if (!valid) e.preventDefault();
    });

    // Countdown timer
    if (document.getElementById('countdown')) {
        let timeLeft = 2;
        const countdown = document.getElementById('countdown');
        setInterval(() => {
            timeLeft--;
            countdown.textContent = timeLeft;
        }, 1000);
    }
</script>
</body>
</html>