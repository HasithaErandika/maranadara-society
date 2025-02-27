<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Loan.php';

$member = new Member();
$payment = new Payment();
$loan = new Loan();
$error = $success = '';

$members = $member->getAllMembers();
$society_payments = $payment->getPaymentsByType('Society Issued');
$membership_payments = $payment->getPaymentsByType('Membership Fee');
$loan_payments = $payment->getPaymentsByType('Loan Settlement');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $member_id = $_POST['member_id'];
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_type = $_POST['payment_type'];
        $receipt_number = $_POST['receipt_number'] ?: null;
        $remarks = $_POST['remarks'] ?: null;
        $loan_id = ($payment_type === 'Loan Settlement' && isset($_POST['loan_id']) && !empty($_POST['loan_id'])) ? intval($_POST['loan_id']) : null;
        $is_confirmed = false; // Default, set after entry
        $confirmed_by = null;  // Default, set after entry

        if (empty($member_id) || $amount <= 0 || empty($date) || empty($payment_mode) || empty($payment_type)) {
            $error = "All required fields must be filled with valid values.";
        } elseif ($payment_type === 'Loan Settlement' && !$loan_id) {
            $error = "Please select a loan for Loan Settlement.";
        } else {
            if ($payment->addPayment($member_id, $amount, $date, $payment_mode, $payment_type, $receipt_number, $remarks, $loan_id)) {
                $success = "Payment added successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } else {
                $error = "Error adding payment.";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("SELECT is_confirmed FROM payments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['is_confirmed']) {
            $error = "Cannot delete a confirmed payment.";
        } else {
            $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Payment deleted successfully!";
                $society_payments = $payment->getPaymentsByType('Society Issued');
                $membership_payments = $payment->getPaymentsByType('Membership Fee');
                $loan_payments = $payment->getPaymentsByType('Loan Settlement');
            } else {
                $error = "Error deleting payment: " . $conn->error;
            }
        }
    } elseif (isset($_POST['confirm'])) {
        $id = $_POST['id'];
        if ($payment->confirmPayment($id, $_SESSION['user'])) {
            $success = "Payment confirmed successfully!";
            $society_payments = $payment->getPaymentsByType('Society Issued');
            $membership_payments = $payment->getPaymentsByType('Membership Fee');
            $loan_payments = $payment->getPaymentsByType('Loan Settlement');
        } else {
            $error = "Error confirming payment or already confirmed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Maranadhara Samithi</title>
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
        .badge-success {
            background: #10b981;
            color: white;
            padding: 0.35rem 0.85rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
        }
        #loan-section {
            display: none;
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
            <li class="sidebar-item active"><a href="payments.php" class="flex items-center"><i class="fas fa-money-bill"></i><span>Manage Payments</span></a></li>
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Dashboard Content -->
    <main class="main-content">
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-amber-600">Manage Payments</h1>
            <p class="text-gray-600 mt-1">Track and manage all payment records effortlessly.</p>
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

        <!-- Add Payment Form -->
        <div class="card mb-6">
            <h2 class="text-xl font-semibold mb-4 text-amber-600">Add Payment</h2>
            <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="member_id" class="block text-sm font-medium mb-1 text-gray-700">Member</label>
                    <select id="member_id" name="member_id" class="input-field" required onchange="updateLoans()">
                        <option value="">Select Member</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="amount" class="block text-sm font-medium mb-1 text-gray-700">Amount (LKR)</label>
                    <input type="number" id="amount" name="amount" step="0.01" class="input-field" required>
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium mb-1 text-gray-700">Date</label>
                    <input type="date" id="date" name="date" class="input-field" required>
                </div>
                <div>
                    <label for="payment_mode" class="block text-sm font-medium mb-1 text-gray-700">Payment Mode</label>
                    <select id="payment_mode" name="payment_mode" class="input-field" required>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label for="payment_type" class="block text-sm font-medium mb-1 text-gray-700">Payment Type</label>
                    <select id="payment_type" name="payment_type" class="input-field" required onchange="toggleLoanSection()">
                        <option value="Society Issued">Society Issued</option>
                        <option value="Membership Fee">Membership Fee</option>
                        <option value="Loan Settlement">Loan Settlement</option>
                    </select>
                </div>
                <div>
                    <label for="receipt_number" class="block text-sm font-medium mb-1 text-gray-700">Receipt Number</label>
                    <input type="text" id="receipt_number" name="receipt_number" class="input-field">
                </div>
                <div id="loan-section" class="lg:col-span-3">
                    <label for="loan_id" class="block text-sm font-medium mb-1 text-gray-700">Select Loan</label>
                    <select id="loan_id" name="loan_id" class="input-field">
                        <option value="">Select a Loan</option>
                    </select>
                </div>
                <div class="lg:col-span-3">
                    <label for="remarks" class="block text-sm font-medium mb-1 text-gray-700">Remarks</label>
                    <textarea id="remarks" name="remarks" class="input-field" rows="2"></textarea>
                </div>
                <div class="lg:col-span-3 text-center">
                    <button type="submit" name="add" class="btn btn-primary">Add Payment</button>
                </div>
            </form>
        </div>

        <!-- Payments Issued from Society -->
        <div class="card mb-6">
            <h2 class="text-xl font-semibold mb-4 text-amber-600">Payments Issued from Society</h2>
            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Amount (LKR)</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($society_payments as $p): ?>
                        <?php $m = $member->getMemberById($p['member_id']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($p['is_confirmed']): ?>
                                    <span class="badge-success"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success px-2 py-1"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="flex space-x-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="btn btn-primary px-2 py-1"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger px-2 py-1"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($society_payments)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 py-4">No society payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments from Members for Membership Fees -->
        <div class="card mb-6">
            <h2 class="text-xl font-semibold mb-4 text-amber-600">Membership Fee Payments</h2>
            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Amount (LKR)</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($membership_payments as $p): ?>
                        <?php $m = $member->getMemberById($p['member_id']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($p['is_confirmed']): ?>
                                    <span class="badge-success"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success px-2 py-1"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="flex space-x-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="btn btn-primary px-2 py-1"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger px-2 py-1"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($membership_payments)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 py-4">No membership fee payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments for Loan Settlements -->
        <div class="card mb-6">
            <h2 class="text-xl font-semibold mb-4 text-amber-600">Loan Settlement Payments</h2>
            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Loan ID</th>
                        <th>Amount (LKR)</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loan_payments as $p): ?>
                        <?php $m = $member->getMemberById($p['member_id']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td><?php echo htmlspecialchars($p['loan_id'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['date']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($p['is_confirmed']): ?>
                                    <span class="badge-success"><i class="fas fa-check mr-1"></i>Confirmed by <?php echo htmlspecialchars($p['confirmed_by']); ?></span>
                                <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="confirm" class="btn btn-success px-2 py-1"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="flex space-x-2">
                                <?php if (!$p['is_confirmed']): ?>
                                    <a href="edit_payment.php?id=<?php echo $p['id']; ?>" class="btn btn-primary px-2 py-1"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger px-2 py-1"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loan_payments)): ?>
                        <tr><td colspan="9" class="text-center text-gray-500 py-4">No loan settlement payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
    function toggleLoanSection() {
        const paymentType = document.getElementById('payment_type').value;
        const loanSection = document.getElementById('loan-section');
        loanSection.style.display = paymentType === 'Loan Settlement' ? 'block' : 'none';
    }

    function updateLoans() {
        const memberId = document.getElementById('member_id').value;
        const loanDropdown = document.getElementById('loan_id');

        loanDropdown.innerHTML = '<option value="">Select a Loan</option>'; // Reset dropdown

        if (memberId) {
            fetch(`get_loans.php?member_id=${memberId}`)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(`HTTP error! Status: ${response.status}, Message: ${err.error || 'Unknown error'}`);
                        });
                    }
                    return response.json();
                })
                .then(loans => {
                    console.log('Loans received:', loans);
                    if (loans.length > 0) {
                        loans.forEach(loan => {
                            loanDropdown.innerHTML += `<option value="${loan.id}">Loan #${loan.id} - LKR ${Number(loan.amount).toFixed(2)}</option>`;
                        });
                    } else {
                        loanDropdown.innerHTML += '<option value="">No active loans available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching loans:', error);
                    loanDropdown.innerHTML = '<option value="">Error loading loans: ' + error.message + '</option>';
                });
        }
    }

    // Initialize on page load
    toggleLoanSection();
</script>
</body>
</html>