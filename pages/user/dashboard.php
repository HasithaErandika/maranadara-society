<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Loan.php';

// Connect to database
$conn = (new Database())->getConnection();

// Fetch member details linked to the logged-in user
$stmt = $conn->prepare("SELECT m.* FROM members m JOIN users u ON m.id = u.member_id WHERE u.username = ?");
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$member_details = $stmt->get_result()->fetch_assoc();

// Fetch family details
$stmt = $conn->prepare("SELECT * FROM family_details WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$family_details = $stmt->get_result()->fetch_assoc();

// Fetch payments
$stmt = $conn->prepare("SELECT amount, date, payment_mode FROM payments WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_paid = array_sum(array_column($payments, 'amount'));
$pending_dues = $member_details['contribution_amount'] * (date('Y') - substr($member_details['date_of_joining'], 0, 4) + 1) * 12 - $total_paid;

// Fetch funeral benefits
$stmt = $conn->prepare("SELECT * FROM funeral_benefits WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$funeral_benefits = $stmt->get_result()->fetch_assoc();

// Fetch loans
$stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch documents
$stmt = $conn->prepare("SELECT * FROM documents WHERE member_id = ?");
$stmt->bind_param("i", $member_details['id']);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --btn-bg: #2ecc71; /* User green */
            --btn-hover: #27ae60;
            --border-color: #d1d5db;
        }
        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #f3f4f6;
            --card-bg: #374151;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --btn-bg: #27ae60;
            --btn-hover: #219653;
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-user {
            background-color: var(--btn-bg);
            transition: all 0.3s ease;
        }
        .btn-user:hover {
            background-color: var(--btn-hover);
            transform: scale(1.05);
        }
        .table-hover tbody tr:hover {
            background-color: #e8f5e9;
        }
        .sidebar {
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
    </style>
</head>
<body class="bg-gray-100">
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-green-600 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-6">
            <span class="text-gray-700 dark:text-gray-300">Welcome, <?php echo $_SESSION['user']; ?></span>
            <a href="../login.php?logout=1" class="text-white px-5 py-2 rounded-lg btn-user">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="w-64 sidebar p-6 fixed h-full">
        <h3 class="text-xl font-bold mb-6 text-green-600">Member Menu</h3>
        <ul class="space-y-4">
            <li><a href="#member-info" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-id-card mr-2"></i>Member Info</a></li>
            <li><a href="#membership" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-clipboard mr-2"></i>Membership</a></li>
            <li><a href="#family" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-users mr-2"></i>Family</a></li>
            <li><a href="#financial" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-money-bill mr-2"></i>Financial</a></li>
            <li><a href="#funeral" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-hand-holding-heart mr-2"></i>Funeral Benefits</a></li>
            <li><a href="#loans" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i>Loans</a></li>
            <li><a href="#documents" class="text-gray-700 dark:text-gray-300 hover:text-green-600 flex items-center"><i class="fas fa-file-alt mr-2"></i>Documents</a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="flex-1 p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-green-600">Member Dashboard</h1>

        <!-- Member Information -->
        <div id="member-info" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Member Information</h2>
            <?php if ($member_details): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <p><strong>Member ID:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></p>
                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($member_details['full_name']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($member_details['date_of_birth']); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($member_details['gender']); ?></p>
                    <p><strong>NIC Number:</strong> <?php echo htmlspecialchars($member_details['nic_number']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($member_details['address']); ?></p>
                    <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($member_details['contact_number']); ?></p>
                    <p><strong>Email:</strong> <?php echo $member_details['email'] ? htmlspecialchars($member_details['email']) : 'N/A'; ?></p>
                    <p><strong>Occupation:</strong> <?php echo $member_details['occupation'] ? htmlspecialchars($member_details['occupation']) : 'N/A'; ?></p>
                </div>
            <?php else: ?>
                <p class="text-gray-600 dark:text-gray-400">No member information found.</p>
            <?php endif; ?>
        </div>

        <!-- Membership Details -->
        <div id="membership" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Membership Details</h2>
            <?php if ($member_details): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <p><strong>Membership Number:</strong> <?php echo htmlspecialchars($member_details['member_id']); ?></p>
                    <p><strong>Date of Joining:</strong> <?php echo htmlspecialchars($member_details['date_of_joining']); ?></p>
                    <p><strong>Membership Type:</strong> <?php echo htmlspecialchars($member_details['membership_type']); ?></p>
                    <p><strong>Contribution Amount:</strong> LKR <?php echo number_format($member_details['contribution_amount'], 2); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo htmlspecialchars($member_details['payment_status']); ?></p>
                    <p><strong>Member Status:</strong> <?php echo htmlspecialchars($member_details['member_status']); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Family Details -->
        <div id="family" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Family Details</h2>
            <?php if ($family_details): ?>
                <p><strong>Spouse's Name:</strong> <?php echo $family_details['spouse_name'] ? htmlspecialchars($family_details['spouse_name']) : 'N/A'; ?></p>
                <p><strong>Children:</strong> <?php echo $family_details['children_info'] ? htmlspecialchars($family_details['children_info']) : 'N/A'; ?></p>
                <p><strong>Dependents:</strong> <?php echo $family_details['dependents_info'] ? htmlspecialchars($family_details['dependents_info']) : 'N/A'; ?></p>
            <?php else: ?>
                <p class="text-gray-600 dark:text-gray-400">No family details recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Financial & Payment Records -->
        <div id="financial" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Financial & Payment Records</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <p><strong>Total Contributions Paid:</strong> LKR <?php echo number_format($total_paid, 2); ?></p>
                <p><strong>Pending Dues:</strong> LKR <?php echo number_format(max(0, $pending_dues), 2); ?></p>
            </div>
            <h3 class="text-lg font-semibold mb-2">Payment History</h3>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Payment Mode</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['date']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($p['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="3" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Funeral Benefit Details -->
        <div id="funeral" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Funeral Benefit Details</h2>
            <?php if ($funeral_benefits): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <p><strong>Nominee Name:</strong> <?php echo htmlspecialchars($funeral_benefits['nominee_name']); ?></p>
                    <p><strong>Nominee Relationship:</strong> <?php echo htmlspecialchars($funeral_benefits['nominee_relationship']); ?></p>
                    <p><strong>Death Certificate Date:</strong> <?php echo $funeral_benefits['death_certificate_date'] ?: 'N/A'; ?></p>
                    <p><strong>Amount Paid:</strong> LKR <?php echo $funeral_benefits['amount_paid'] ? number_format($funeral_benefits['amount_paid'], 2) : 'N/A'; ?></p>
                    <p><strong>Payment Date:</strong> <?php echo $funeral_benefits['payment_date'] ?: 'N/A'; ?></p>
                </div>
            <?php else: ?>
                <p class="text-gray-600 dark:text-gray-400">No funeral benefits recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Loans -->
        <div id="loans" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Loans Taken</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Amount (LKR)</th>
                        <th class="py-2 px-4 text-left">Interest Rate (%)</th>
                        <th class="py-2 px-4 text-left">Duration (Months)</th>
                        <th class="py-2 px-4 text-left">Monthly Payment (LKR)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loans as $l): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo number_format($l['amount'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['interest_rate'], 2); ?></td>
                            <td class="py-2 px-4"><?php echo $l['duration']; ?></td>
                            <td class="py-2 px-4"><?php echo number_format($l['monthly_payment'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loans)): ?>
                        <tr><td colspan="4" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No loans taken.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Additional Notes & Documents -->
        <div id="documents" class="card p-6 rounded-xl mb-6">
            <h2 class="text-xl font-semibold mb-4">Additional Notes & Documents</h2>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Document Type</th>
                        <th class="py-2 px-4 text-left">Notes</th>
                        <th class="py-2 px-4 text-left">Upload Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($d['document_type']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($d['notes']) ?: 'N/A'; ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($d['upload_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="3" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No documents uploaded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Footer -->
<footer class="py-8 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 dark:text-gray-400">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>
</body>
</html>