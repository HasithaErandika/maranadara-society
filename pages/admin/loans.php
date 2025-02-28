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
                $success = "Loan application submitted successfully!";
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
                $success = "Loan deleted successfully!";
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
            $success = "Loan approved successfully!";
            $loans = $loan->getAllLoans($selected_member_id);
        } else {
            $error = "Error approving loan or not in 'Applied' status.";
        }
    } elseif (isset($_POST['settle'])) {
        $id = $_POST['id'];
        if ($loan->settleLoan($id, $_SESSION['user'])) {
            $success = "Loan settled successfully!";
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
        .btn-success {
            background-color: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-2px);
        }
        .btn-delete {
            background-color: #dc2626;
            color: white;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
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
        .table-container {
            overflow-x: auto;
        }
        .table th, .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .table thead th {
            background-color: var(--accent-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table tbody tr:hover {
            background-color: rgba(249, 115, 22, 0.05);
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            background-color: var(--accent-color);
            color: white;
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
            position: relative;
        }
        .status-pending {
            background: #6b7280;
            position: relative;
        }
        .status-applied {
            background: #d97706;
            position: relative;
        }
        .status-bar div:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #111827;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
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
            <li class="sidebar-item"><a href="add_member.php" class="flex items-center"><i class="fas fa-user-plus"></i><span>Add Member</span></a></li>
            <li class="sidebar-item"><a href="incidents.php?action=add" class="flex items-center"><i class="fas fa-file-alt"></i><span>Record Incident</span></a></li>
            <li class="sidebar-item"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item <?php echo $action == 'add' ? 'active' : ''; ?>"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item <?php echo $action == 'view' ? 'active' : ''; ?>"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 main-content" id="main-content">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-4xl font-extrabold mb-8 text-orange-600">Loan Management</h1>

            <!-- Tabs -->
            <div class="flex flex-wrap space-x-4 mb-6">
                <button class="tab-btn <?php echo $action === 'view' ? 'active' : ''; ?>" onclick="showTab('view')">View Loans</button>
                <button class="tab-btn <?php echo $action === 'add' ? 'active' : ''; ?>" onclick="showTab('add')">Add Loan</button>
            </div>

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

            <!-- Add Loan Tab -->
            <div id="tab-add" class="card <?php echo $action !== 'add' ? 'hidden' : ''; ?>">
                <!-- Loan Calculator -->
                <h2 class="text-2xl font-semibold mb-6 text-orange-600">Loan Calculator</h2>
                <form method="POST" class="space-y-6" id="calculator-form">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="calc_amount" class="block text-sm font-semibold mb-2">Loan Amount (LKR) <span class="text-red-500">*</span></label>
                            <input type="number" id="calc_amount" name="amount" step="0.01" class="input-field" required min="0.01" aria-label="Loan Amount">
                        </div>
                        <div>
                            <label for="calc_interest" class="block text-sm font-semibold mb-2">Interest Rate (%) <span class="text-red-500">*</span></label>
                            <input type="number" id="calc_interest" name="interest_rate" step="0.01" class="input-field" required min="0" aria-label="Interest Rate">
                        </div>
                        <div>
                            <label for="calc_duration" class="block text-sm font-semibold mb-2">Duration (Months) <span class="text-red-500">*</span></label>
                            <input type="number" id="calc_duration" name="duration" class="input-field" required min="1" aria-label="Duration">
                        </div>
                    </div>
                    <div class="flex justify-center space-x-4">
                        <button type="submit" name="calculate" class="btn-admin font-semibold">Calculate</button>
                        <button type="reset" class="btn-delete font-semibold">Reset</button>
                    </div>
                </form>
                <?php if ($loan_breakdown): ?>
                    <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300"><strong>Loan Amount:</strong> LKR <?php echo $loan_breakdown['amount']; ?></p>
                        <p class="text-gray-700 dark:text-gray-300"><strong>Total Interest:</strong> LKR <?php echo $loan_breakdown['interest']; ?></p>
                        <p class="text-gray-700 dark:text-gray-300"><strong>Monthly Payment:</strong> LKR <?php echo $loan_breakdown['monthly_payment']; ?></p>
                        <p class="text-gray-700 dark:text-gray-300"><strong>Total Payable:</strong> LKR <?php echo $loan_breakdown['total_payable']; ?></p>
                    </div>
                <?php endif; ?>

                <!-- Add Loan Form -->
                <h2 class="text-2xl font-semibold mt-8 mb-6 text-orange-600">Add New Loan</h2>
                <form method="POST" class="space-y-6" id="add-loan-form">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="member_id" class="block text-sm font-semibold mb-2">Member <span class="text-red-500">*</span></label>
                            <select id="member_id" name="member_id" class="input-field" required aria-label="Select Member">
                                <option value="" disabled selected>Select Member</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-semibold mb-2">Amount (LKR) <span class="text-red-500">*</span></label>
                            <input type="number" id="amount" name="amount" step="0.01" class="input-field" value="<?php echo $loan_breakdown['amount'] ?? ''; ?>" required min="0.01" aria-label="Loan Amount">
                        </div>
                        <div>
                            <label for="interest_rate" class="block text-sm font-semibold mb-2">Interest Rate (%) <span class="text-red-500">*</span></label>
                            <input type="number" id="interest_rate" name="interest_rate" step="0.01" class="input-field" value="<?php echo $loan_breakdown ? floatval($_POST['interest_rate']) : ''; ?>" required min="0" aria-label="Interest Rate">
                        </div>
                        <div>
                            <label for="duration" class="block text-sm font-semibold mb-2">Duration (Months) <span class="text-red-500">*</span></label>
                            <input type="number" id="duration" name="duration" class="input-field" value="<?php echo $loan_breakdown ? intval($_POST['duration']) : ''; ?>" required min="1" aria-label="Duration">
                        </div>
                        <div>
                            <label for="start_date" class="block text-sm font-semibold mb-2">Start Date <span class="text-red-500">*</span></label>
                            <input type="date" id="start_date" name="start_date" class="input-field" required aria-label="Start Date">
                        </div>
                        <div class="md:col-span-2">
                            <label for="remarks" class="block text-sm font-semibold mb-2">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" class="input-field" rows="3" placeholder="Additional details..." aria-label="Remarks"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-4">
                        <button type="submit" name="add" class="btn-admin font-semibold">Submit Loan Application</button>
                        <button type="reset" class="btn-delete font-semibold">Reset</button>
                    </div>
                </form>
            </div>

            <!-- View Loans Tab -->
            <div id="tab-view" class="card <?php echo $action !== 'view' ? 'hidden' : ''; ?>">
                <h2 class="text-2xl font-semibold mb-6 text-orange-600">Loan Records</h2>
                <form method="GET" class="mb-6 flex flex-col md:flex-row items-center gap-4">
                    <input type="hidden" name="action" value="view">
                    <label for="member_id" class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filter by Member:</label>
                    <select id="member_id" name="member_id" class="input-field w-full md:w-1/3" onchange="this.form.submit()" aria-label="Filter by Member">
                        <option value="">All Members</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $selected_member_id == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <div class="table-container">
                    <table class="w-full table">
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
                            $final_payment = $l['total_payable'];
                            $total_paid = $l['total_paid'];
                            $total_payable = $final_payment - $total_paid;
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
                                            <button type="submit" name="approve" class="btn-success" onclick="return confirm('Approve this loan?');" aria-label="Approve Loan"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="delete" class="btn-delete" onclick="return confirm('Are you sure you want to delete this loan?');" aria-label="Delete Loan"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php elseif ($status == 'Pending' && $total_payable <= 0): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="settle" class="btn-success px-2 py-1" onclick="return confirm('Settle this loan?');" aria-label="Settle Loan">Settle</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($loans)): ?>
                            <tr><td colspan="7" class="py-4 text-center text-gray-500 dark:text-gray-400">No loans recorded.</td></tr>
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
                        <div class="mt-6">
                            <h3 class="text-lg font-semibold text-orange-600">Status Overview</h3>
                            <div class="status-bar" role="progressbar" aria-label="Loan Status Distribution">
                                <div class="status-settled" style="width: <?php echo $settled_width; ?>%;" data-tooltip="Settled: <?php echo $settled_count; ?>"></div>
                                <div class="status-pending" style="width: <?php echo $pending_width; ?>%;" data-tooltip="Pending: <?php echo $pending_count; ?>"></div>
                                <div class="status-applied" style="width: <?php echo $applied_width; ?>%;" data-tooltip="Applied: <?php echo $applied_count; ?>"></div>
                            </div>
                            <div class="flex justify-between text-sm mt-2 text-gray-700 dark:text-gray-300">
                                <span>Settled: <?php echo $settled_count; ?></span>
                                <span>Pending: <?php echo $pending_count; ?></span>
                                <span>Applied: <?php echo $applied_count; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

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
    const calculatorForm = document.getElementById('calculator-form');
    const addLoanForm = document.getElementById('add-loan-form');

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

    // Tab switching
    function showTab(tab) {
        document.querySelectorAll('.card').forEach(card => card.classList.add('hidden'));
        document.getElementById(`tab-${tab}`).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`button[onclick="showTab('${tab}')"]`).classList.add('active');
        window.history.pushState({}, '', `loans.php?action=${tab}${tab === 'view' && <?php echo $selected_member_id ? "document.getElementById('member_id').value" : "''"; ?> ? '&member_id=' + <?php echo $selected_member_id ? "document.getElementById('member_id').value" : "''"; ?> : ''}`);
    }

    // Real-time validation for calculator form
    calculatorForm?.addEventListener('input', (e) => {
        const target = e.target;
        if (target.id === 'calc_amount' && target.value <= 0) {
            target.setCustomValidity('Amount must be greater than 0');
        } else if (target.id === 'calc_interest' && target.value < 0) {
            target.setCustomValidity('Interest rate cannot be negative');
        } else if (target.id === 'calc_duration' && target.value <= 0) {
            target.setCustomValidity('Duration must be greater than 0');
        } else {
            target.setCustomValidity('');
        }
    });

    // Real-time validation for add loan form
    addLoanForm?.addEventListener('input', (e) => {
        const target = e.target;
        if (target.id === 'amount' && target.value <= 0) {
            target.setCustomValidity('Amount must be greater than 0');
        } else if (target.id === 'interest_rate' && target.value < 0) {
            target.setCustomValidity('Interest rate cannot be negative');
        } else if (target.id === 'duration' && target.value <= 0) {
            target.setCustomValidity('Duration must be greater than 0');
        } else {
            target.setCustomValidity('');
        }
    });

    // Initialize tab state
    showTab('<?php echo $action; ?>');
</script>
</body>
</html>