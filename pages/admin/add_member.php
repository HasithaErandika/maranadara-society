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
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #e5e7eb;
            --accent-color: #f97316;
            --sidebar-width: 64px;
            --sidebar-expanded: 240px;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            --btn-bg: #e67e22;
            --btn-hover: #f39c12;
            --border-color: #4b5563;
            --accent-color: #fb923c;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            padding: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.03);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            transition: width 0.3s ease;
            position: fixed;
            top: 80px;
            left: 16px;
            height: calc(100vh - 100px);
            overflow: hidden;
            z-index: 20;
        }
        .sidebar:hover, .sidebar-expanded {
            width: var(--sidebar-expanded);
            z-index: 30;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-color);
            transition: background-color 0.2s ease;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background-color: #f97316;
            color: white;
        }
        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
        }
        .sidebar-item span {
            display: none;
            white-space: nowrap;
        }
        .sidebar:hover .sidebar-item span, .sidebar-expanded .sidebar-item span {
            display: inline;
        }
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: calc(var(--sidebar-width) + 16px);
        }
        .sidebar:hover ~ .main-content, .sidebar-expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 16px);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            border-radius: 8px;
        }
        .input-field:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
            outline: none;
        }
        .section-header {
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px 8px 0 0;
            margin-bottom: 1rem;
        }
        .error-text {
            color: #dc2626;
            font-size: 0.875rem;
            display: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: 0;
            }
            .sidebar-expanded {
                width: var(--sidebar-expanded);
            }
            .main-content {
                margin-left: 16px;
            }
            .sidebar:hover {
                width: var(--sidebar-width);
            }
            .sidebar:hover ~ .main-content {
                margin-left: calc(var(--sidebar-width) + 16px);
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center" aria-label="Home">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 dark:text-gray-300 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="md:hidden text-orange-600" id="sidebar-toggle" aria-label="Toggle Sidebar">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <a href="../login.php?logout=1" class="text-white px-4 py-2 rounded-lg btn-admin" aria-label="Logout">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-4">
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
    <main class="flex-1 p-6 main-content" id="main-content">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-extrabold mb-6 text-orange-600">Add New Member</h1>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" class="card space-y-6" id="add-member-form">
                <!-- Member Information -->
                <div>
                    <div class="section-header">Member Information</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4">
                        <div>
                            <label for="member_id" class="block text-sm font-medium mb-1">Membership ID</label>
                            <input type="text" id="member_id" name="member_id" class="input-field w-full px-3 py-2" placeholder="e.g., MS-001" required>
                            <span class="error-text" id="member_id-error">Must be 'MS-' followed by at least 3 digits.</span>
                        </div>
                        <div>
                            <label for="full_name" class="block text-sm font-medium mb-1">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="input-field w-full px-3 py-2" required>
                            <span class="error-text" id="full_name-error">Full name is required.</span>
                        </div>
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium mb-1">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="input-field w-full px-3 py-2" required>
                        </div>
                        <div>
                            <label for="gender" class="block text-sm font-medium mb-1">Gender</label>
                            <select id="gender" name="gender" class="input-field w-full px-3 py-2" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="nic_number" class="block text-sm font-medium mb-1">NIC Number</label>
                            <input type="text" id="nic_number" name="nic_number" class="input-field w-full px-3 py-2" required>
                            <span class="error-text" id="nic_number-error">Must be at least 9 characters.</span>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium mb-1">Address</label>
                            <input type="text" id="address" name="address" class="input-field w-full px-3 py-2" required>
                        </div>
                        <div>
                            <label for="contact_number" class="block text-sm font-medium mb-1">Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number" class="input-field w-full px-3 py-2" placeholder="+94XXXXXXXXX" required>
                            <span class="error-text" id="contact_number-error">Format: +94 followed by 9 digits.</span>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1">Email (Optional)</label>
                            <input type="email" id="email" name="email" class="input-field w-full px-3 py-2">
                        </div>
                        <div>
                            <label for="occupation" class="block text-sm font-medium mb-1">Occupation (Optional)</label>
                            <input type="text" id="occupation" name="occupation" class="input-field w-full px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Membership Details -->
                <div>
                    <div class="section-header">Membership Details</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4">
                        <div>
                            <label for="date_of_joining" class="block text-sm font-medium mb-1">Date of Joining</label>
                            <input type="date" id="date_of_joining" name="date_of_joining" class="input-field w-full px-3 py-2" required>
                        </div>
                        <div>
                            <label for="membership_type" class="block text-sm font-medium mb-1">Membership Type</label>
                            <select id="membership_type" name="membership_type" class="input-field w-full px-3 py-2" required>
                                <option value="Individual">Individual</option>
                                <option value="Family">Family</option>
                                <option value="Senior Citizen">Senior Citizen</option>
                            </select>
                        </div>
                        <div>
                            <label for="contribution_amount" class="block text-sm font-medium mb-1">Contribution (LKR)</label>
                            <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" class="input-field w-full px-3 py-2" required>
                        </div>
                        <div>
                            <label for="payment_status" class="block text-sm font-medium mb-1">Payment Status</label>
                            <select id="payment_status" name="payment_status" class="input-field w-full px-3 py-2" required>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label for="member_status" class="block text-sm font-medium mb-1">Member Status</label>
                            <select id="member_status" name="member_status" class="input-field w-full px-3 py-2" required>
                                <option value="Active">Active</option>
                                <option value="Deceased">Deceased</option>
                                <option value="Resigned">Resigned</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Family Details -->
                <div>
                    <div class="section-header">Family Details (Optional)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4">
                        <div>
                            <label for="spouse_name" class="block text-sm font-medium mb-1">Spouse's Name</label>
                            <input type="text" id="spouse_name" name="spouse_name" class="input-field w-full px-3 py-2">
                        </div>
                        <div>
                            <label for="children_info" class="block text-sm font-medium mb-1">Children (Name:Age, ...)</label>
                            <input type="text" id="children_info" name="children_info" class="input-field w-full px-3 py-2" placeholder="e.g., Sahan:12, Nimasha:8">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="dependents_info" class="block text-sm font-medium mb-1">Dependents (Relation:Name, ...)</label>
                            <input type="text" id="dependents_info" name="dependents_info" class="input-field w-full px-3 py-2" placeholder="e.g., Mother:Sunila">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="submit" class="text-white px-6 py-3 btn-admin font-semibold">Add Member</button>
                </div>
            </form>
            <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
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
    const mainContent = document.getElementById('main-content');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-expanded');
    });

    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', (e) => e.preventDefault());
    }

    // Client-side validation
    const form = document.getElementById('add-member-form');
    form.addEventListener('submit', (e) => {
        let valid = true;
        const memberId = document.getElementById('member_id');
        const fullName = document.getElementById('full_name');
        const nicNumber = document.getElementById('nic_number');
        const contactNumber = document.getElementById('contact_number');

        if (!/ ^MS-\d{3,}$/.test(memberId.value)) {
            document.getElementById('member_id-error').style.display = 'block';
            valid = false;
        } else {
            document.getElementById('member_id-error').style.display = 'none';
        }

        if (!fullName.value.trim()) {
            document.getElementById('full_name-error').style.display = 'block';
            valid = false;
        } else {
            document.getElementById('full_name-error').style.display = 'none';
        }

        if (nicNumber.value.length < 9) {
            document.getElementById('nic_number-error').style.display = 'block';
            valid = false;
        } else {
            document.getElementById('nic_number-error').style.display = 'none';
        }

        if (!/^\+94\d{9}$/.test(contactNumber.value)) {
            document.getElementById('contact_number-error').style.display = 'block';
            valid = false;
        } else {
            document.getElementById('contact_number-error').style.display = 'none';
        }

        if (!valid) e.preventDefault();
    });

    // Countdown timer for success redirect
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