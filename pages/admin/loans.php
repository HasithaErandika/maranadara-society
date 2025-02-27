<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Loan.php';
require_once '../../classes/Payment.php';

$member = new Member();
$loan = new Loan();
$payment = new Payment();
$conn = (new Database())->getConnection();
$error = $success = '';
$loan_breakdown = null;

$members = $member->getAllMembers();
$selected_member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : null;
$loans = $loan->getAllLoans($selected_member_id);
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = floatval($_POST['amount']);
        $interest_rate = floatval($_POST['interest_rate']);
        $duration = intval($_POST['duration']);
        $start_date = $_POST['start_date'];
        $remarks = $_POST['remarks'] ?: null;

        if (empty($member_id) || $amount <= 0 || $interest_rate < 0 || $duration <= 0 || empty($start_date)) {
            $error = "All fields are required, and values must be valid.";
        } elseif (!strtotime($start_date)) {
            $error = "Invalid start date format.";
        } else {
            if ($loan->addLoan($member_id, $amount, $interest_rate, $duration, $start_date, $remarks)) {
                $success = "Loan application submitted Successfully!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error adding loan. Member may have an unsettled loan.";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT status FROM loans WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['status'] != 'Applied') {
            $error = "Can only delete loans in 'Applied' status.";
        } else {
            $stmt = $conn->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Loan deleted Successfully!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error deleting loan: " . $conn->error;
            }
        }
    } elseif (isset($_POST['calculate'])) {
        $amount = floatval($_POST['amount']);
        $interest_rate = floatval($_POST['interest_rate']);
        $duration = intval($_POST['duration']);

        if ($amount > 0 && $interest_rate >= 0 && $duration > 0) {
            $loan_breakdown = $loan->calculateLoanBreakdown($amount, $interest_rate, $duration);
        } else {
            $error = "Please enter valid values for calculation.";
        }
    } elseif (isset($_POST['approve'])) {
        $id = $_POST['id'];
        if ($loan->approveLoan($id, $_SESSION['user'])) {
            $success = "Loan approved Successfully!";
            $loans = $loan->getAllLoans($selected_member_id);
        } else {
            $error = "Error approving loan or not in 'Applied' status.";
        }
    } elseif (isset($_POST['settle'])) {
        $id = $_POST['id'];
        if ($loan->settleLoan($id, $_SESSION['user'])) {
            $success = "Loan settled Successfully!";
            $loans = $loan->getAllLoans($selected_member_id);
        } else {
            $error = "Error settling loan: Total paid must equal or exceed total payable.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #f0f4f8, #e2e8f0);
            color: #2d3748;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #1e40af;
            color: white;
        }
        .btn-primary:hover {
            background: #1e3a8a;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .sidebar {
            width: 72px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: width 0.3s ease;
            position: fixed;
            top: 80px;
            left: 16px;
            height: calc(100vh - 100px);
            overflow: hidden;
        }
        .sidebar:hover {
            width: 256px;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            color: #2d3748;
            transition: background 0.2s;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background: #f59e0b;
            color: white;
        }
        .sidebar-item i {
            width: 28px;
            text-align: center;
            margin-right: 12px;
        }
        .sidebar-item span {
            display: none;
        }
        .sidebar:hover .sidebar-item span {
            display: inline;
        }
        .main-content {
            margin-left: 100px;
            padding: 2rem;
        }
        .input-field, select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
            transition: border-color 0.2s;
        }
        .input-field:focus, select:focus {
            border-color: #f59e0b;
            outline: none;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            background: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
        }
        th {
            background: #f59e0b;
            color: white;
        }
        tr:nth-child(even) {
            background: #f7fafc;
        }
        tr:hover {
            background: #fef3c7;
        }
        .status-bar {
            display: flex;
            width: 100%;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .status-settled {
            background: #10b981;
        }
        .status-pending {
            background: #6b7280;
        }
        .status-applied {
            background: #d97706;
        }
        .badge {
            padding: 0.35rem 0.85rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-pending {
            background: #6b7280;
            color: white;
        }
        .badge-applied {
            background: #d97706;
            color: white;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="shadow-lg fixed w-full z-10 top-0 bg-white">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="../../index.php" class="text-2xl font-bold text-amber-500 flex items-center">
            <i class="fas fa-hands-helping mr-2"></i>Maranadhara Samithi
        </a>
        <div class="flex items-center space-x-4">
            <span class="text-gray-700 hidden md:inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <button class="md:hidden text-amber-500" id="sidebar-toggle">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <a href="../login.php?logout=1" class="btn btn-primary">Logout</a>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex min-h-screen pt-20">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="mt-4">
            <li class="sidebar-item"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li class="sidebar-item"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item <?php echo $action == 'add' ? 'active' : ''; ?>"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item <?php echo $action == 'view' ? 'active' : ''; ?>"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="main-content">
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-amber-600">Loan Management</h1>
            <p class="text-gray-600 mt-1">View and manage loan details with ease.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($action == 'add'): ?>
            <!-- Loan Calculator -->
            <div class="card mb-6">
                <h2 class="text-xl font-semibold mb-4 text-amber-600">Loan Calculator</h2>
                <form method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="calc_amount" class="block text-sm font-medium mb-1 text-gray-700">Loan Amount (LKR)</label>
                        <input type="number" id="calc_amount" name="amount" step="0.01" class="input-field" required>
                    </div>
                    <div>
                        <label for="calc_interest" class="block text-sm font-medium mb-1 text-gray-700">Interest Rate (%)</label>
                        <input type="number" id="calc_interest" name="interest_rate" step="0.01" class="input-field" required>
                    </div>
                    <div>
                        <label for="calc_duration" class="block text-sm font-medium mb-1 text-gray-700">Duration (Months)</label>
                        <input type="number" id="calc_duration" name="duration" class="input-field" required>
                    </div>
                    <div class="sm:col-span-3 text-center">
                        <button type="submit" name="calculate" class="btn btn-primary">Calculate</button>
                    </div>
                </form>
                <?php if ($loan_breakdown): ?>
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-700"><strong>Loan Amount:</strong> LKR <?php echo $loan_breakdown['amount']; ?></p>
                        <p class="text-gray-700"><strong>Total Interest:</strong> LKR <?php echo $loan_breakdown['interest']; ?></p>
                        <p class="text-gray-700"><strong>Monthly Payment:</strong> LKR <?php echo $loan_breakdown['monthly_payment']; ?></p>
                        <p class="text-gray-700"><strong>Total Payable:</strong> LKR <?php echo $loan_breakdown['total_payable']; ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Loan Form -->
            <div class="card mb-6">
                <h2 class="text-xl font-semibold mb-4 text-amber-600">Add New Loan</h2>
                <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="member_id" class="block text-sm font-medium mb-1 text-gray-700">Member</label>
                        <select id="member_id" name="member_id" class="input-field" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium mb-1 text-gray-700">Amount (LKR)</label>
                        <input type="number" id="amount" name="amount" step="0.01" class="input-field" value="<?php echo $loan_breakdown['amount'] ?? ''; ?>" required>
                    </div>
                    <div>
                        <label for="interest_rate" class="block text-sm font-medium mb-1 text-gray-700">Interest Rate (%)</label>
                        <input type="number" id="interest_rate" name="interest_rate" step="0.01" class="input-field" value="<?php echo $loan_breakdown ? floatval($_POST['interest_rate']) : ''; ?>" required>
                    </div>
                    <div>
                        <label for="duration" class="block text-sm font-medium mb-1 text-gray-700">Duration (Months)</label>
                        <input type="number" id="duration" name="duration" class="input-field" value="<?php echo $loan_breakdown ? intval($_POST['duration']) : ''; ?>" required>
                    </div>
                    <div>
                        <label for="start_date" class="block text-sm font-medium mb-1 text-gray-700">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="input-field" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="remarks" class="block text-sm font-medium mb-1 text-gray-700">Remarks</label>
                        <textarea id="remarks" name="remarks" class="input-field" rows="3"></textarea>
                    </div>
                    <div class="sm:col-span-2 text-center">
                        <button type="submit" name="add" class="btn btn-primary">Submit Loan Application</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Loan List -->
            <div class="card mb-6">
                <h2 class="text-xl font-semibold mb-4 text-amber-600">Loan Records</h2>
                <form method="GET" class="mb-4 flex items-center gap-4">
                    <label for="member_id" class="text-sm font-medium text-gray-700">Filter by Member:</label>
                    <select id="member_id" name="member_id" class="input-field w-full md:w-1/3" onchange="this.form.submit()">
                        <option value="">All Members</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $selected_member_id == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Loan Amount (LKR)</th>
                            <th>Final Payment</th>
                            <th>Total Paid (LKR)</th>
                            <th>Total Payable (LKR)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $settled_count = 0;
                        $pending_count = 0;
                        $applied_count = 0;
                        foreach ($loans as $l) {
                            $final_payment = $l['total_payable']; // Original total payable becomes Final Payment
                            $total_paid = $l['total_paid'];
                            $total_payable = $final_payment - $total_paid; // New Total Payable calculation
                            $status = ($total_payable <= 0) ? 'Settled' : ($l['status'] == 'Applied' ? 'Applied' : 'Pending');

                            if ($status == 'Settled') $settled_count++;
                            elseif ($status == 'Pending') $pending_count++;
                            elseif ($status == 'Applied') $applied_count++;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member->getMemberById($l['member_id'])['member_id']); ?></td>
                                <td><?php echo number_format($l['amount'], 2); ?></td>
                                <td><?php echo number_format($final_payment, 2); ?></td>
                                <td><?php echo number_format($total_paid, 2); ?></td>
                                <td><?php echo number_format($total_payable, 2); ?></td>
                                <td>
                                    <?php if ($status == 'Settled'): ?>
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Settled</span>
                                    <?php elseif ($status == 'Pending'): ?>
                                        <span class="badge badge-pending"><?php echo htmlspecialchars($status); ?></span>
                                    <?php elseif ($status == 'Applied'): ?>
                                        <span class="badge badge-applied"><?php echo htmlspecialchars($status); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="flex space-x-2">
                                    <?php if ($l['status'] == 'Applied'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="approve" class="btn btn-success px-2 py-1"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="delete" class="btn btn-danger px-2 py-1"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php elseif ($status == 'Pending' && $total_payable <= 0): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="settle" class="btn btn-success px-2 py-1">Settle</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($loans)): ?>
                            <tr><td colspan="7" class="text-center text-gray-500 py-4">No loans recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($loans)): ?>
                        <?php
                        $total_loans = count($loans);
                        $settled_width = $total_loans ? ($settled_count / $total_loans) * 100 : 0;
                        $pending_width = $total_loans ? ($pending_count / $total_loans) * 100 : 0;
                        $applied_width = $total_loans ? ($applied_count / $total_loans) * 100 : 0;
                        ?>
                        <div class="mt-4">
                            <h3 class="text-lg font-semibold text-amber-600">Status Overview</h3>
                            <div class="status-bar">
                                <div class="status-settled" style="width: <?php echo $settled_width; ?>%;"></div>
                                <div class="status-pending" style="width: <?php echo $pending_width; ?>%;"></div>
                                <div class="status-applied" style="width: <?php echo $applied_width; ?>%;"></div>
                            </div>
                            <div class="flex justify-between text-sm mt-2 text-gray-700">
                                <span>Settled: <?php echo $settled_count; ?></span>
                                <span>Pending: <?php echo $pending_count; ?></span>
                                <span>Applied: <?php echo $applied_count; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <p class="text-center"><a href="dashboard.php" class="text-amber-600 hover:underline">Back to Dashboard</a></p>
    </main>
</div>

<!-- Footer -->
<footer class="py-6 bg-white">
    <div class="container mx-auto px-6">
        <p class="text-center text-gray-600 text-sm">© 2025 Maranadhara Samithi. All rights reserved.</p>
    </div>
</footer>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-expanded');
    });
</script>
</body>
</html>