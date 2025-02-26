<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';

$member = new Member();
$conn = (new Database())->getConnection();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate a unique member_id (e.g., MS-001 format)
    $last_member = $conn->query("SELECT member_id FROM members ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $last_num = $last_member ? (int)substr($last_member['member_id'], 3) : 0;
    $new_member_id = 'MS-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);

    $full_name = $_POST['full_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $nic_number = $_POST['nic_number'];
    $address = $_POST['address'];
    $contact_number = $_POST['contact_number'];
    $email = $_POST['email'] ?: null;
    $occupation = $_POST['occupation'] ?: null;
    $date_of_joining = $_POST['date_of_joining'];
    $membership_type = $_POST['membership_type'];
    $contribution_amount = $_POST['contribution_amount'];
    $payment_status = $_POST['payment_status'];
    $member_status = $_POST['member_status'];

    // Insert into members table
    $stmt = $conn->prepare("INSERT INTO members (member_id, full_name, date_of_birth, gender, nic_number, address, contact_number, email, occupation, date_of_joining, membership_type, contribution_amount, payment_status, member_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssdds", $new_member_id, $full_name, $date_of_birth, $gender, $nic_number, $address, $contact_number, $email, $occupation, $date_of_joining, $membership_type, $contribution_amount, $payment_status, $member_status);

    if ($stmt->execute()) {
        $success = "Member '$full_name' added successfully!";
        header("Refresh: 2; url=dashboard.php");
    } else {
        $error = "Error adding member: " . $conn->error;
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
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --btn-bg: #d35400;
            --btn-hover: #b84500;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #e67e22;
            --btn-hover: #f39c12;
            --border-color: #4b5563;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans', sans-serif;
        }
        .card {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
        .btn-admin {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .input-field {
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .input-field:focus {
            border-color: #d35400;
            box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.2);
            outline: none;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-orange-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo $_SESSION['user']; ?></span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-admin">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mx-auto px-6 py-20">
    <div class="max-w-3xl mx-auto card p-6 rounded-xl">
        <h1 class="text-2xl font-bold mb-6 text-orange-600">Add New Member</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?> Redirecting...</div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <!-- Member Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="full_name" class="block font-medium mb-1">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="date_of_birth" class="block font-medium mb-1">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="gender" class="block font-medium mb-1">Gender</label>
                    <select id="gender" name="gender" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="nic_number" class="block font-medium mb-1">NIC Number</label>
                    <input type="text" id="nic_number" name="nic_number" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div class="md:col-span-2">
                    <label for="address" class="block font-medium mb-1">Address</label>
                    <input type="text" id="address" name="address" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="contact_number" class="block font-medium mb-1">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="email" class="block font-medium mb-1">Email (Optional)</label>
                    <input type="email" id="email" name="email" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label for="occupation" class="block font-medium mb-1">Occupation (Optional)</label>
                    <input type="text" id="occupation" name="occupation" class="input-field w-full px-4 py-2 rounded-lg">
                </div>
            </div>

            <!-- Membership Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="date_of_joining" class="block font-medium mb-1">Date of Joining</label>
                    <input type="date" id="date_of_joining" name="date_of_joining" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="membership_type" class="block font-medium mb-1">Membership Type</label>
                    <select id="membership_type" name="membership_type" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Individual">Individual</option>
                        <option value="Family">Family</option>
                        <option value="Senior Citizen">Senior Citizen</option>
                    </select>
                </div>
                <div>
                    <label for="contribution_amount" class="block font-medium mb-1">Contribution Amount (LKR)</label>
                    <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" class="input-field w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label for="payment_status" class="block font-medium mb-1">Payment Status</label>
                    <select id="payment_status" name="payment_status" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="member_status" class="block font-medium mb-1">Member Status</label>
                    <select id="member_status" name="member_status" class="input-field w-full px-4 py-2 rounded-lg" required>
                        <option value="Active">Active</option>
                        <option value="Deceased">Deceased</option>
                        <option value="Resigned">Resigned</option>
                    </select>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="text-center">
                <button type="submit" class="text-white px-6 py-3 rounded-lg font-semibold btn-admin">Add Member</button>
            </div>
        </form>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>
</body>
</html>