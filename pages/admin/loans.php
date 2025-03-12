<?php
define('APP_START', true);
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../classes/Member.php';
require_once '../../classes/Loan.php';
require_once '../../classes/Payment.php';
require_once '../../classes/Database.php';

$member = new Member();
$loan = new Loan();
$payment = new Payment();
$error = $success = '';
$loan_breakdown = null;

$members = $member->getAllMembers();
$selected_member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : null;
$loans = $loan->getAllLoans($selected_member_id);
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add'])) {
            $data = [
                'member_id' => intval($_POST['member_id']),
                'amount' => floatval($_POST['amount']),
                'interest_rate' => floatval($_POST['interest_rate']),
                'duration' => intval($_POST['duration']),
                'monthly_payment' => floatval($_POST['monthly_payment']),
                'total_payable' => floatval($_POST['total_payable']),
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'remarks' => $_POST['remarks'] ?: null
            ];

            if ($loan->addLoan($data['member_id'], $data['amount'], $data['interest_rate'],
                $data['duration'], $data['start_date'], $data['remarks'],
                $data['monthly_payment'], $data['total_payable'], $data['end_date'])) {
                $success = "Loan application submitted successfully!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error adding loan. Member may have an unsettled loan.";
            }
        } elseif (isset($_POST['delete'])) {
            $id = intval($_POST['id']);
            $conn = (new Database())->getConnection();
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
                    $error = "Error deleting loan.";
                }
            }
        } elseif (isset($_POST['calculate'])) {
            $loan_breakdown = $loan->calculateLoanBreakdown(
                floatval($_POST['amount']),
                floatval($_POST['interest_rate']),
                intval($_POST['duration']),
                $_POST['start_date'] ?? date('Y-m-d')
            );
        } elseif (isset($_POST['approve'])) {
            if ($loan->approveLoan($_POST['id'], $_SESSION['user'])) {
                $success = "Loan approved successfully!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error approving loan.";
            }
        } elseif (isset($_POST['settle'])) {
            if ($loan->settleLoan($_POST['id'], $_SESSION['user'])) {
                $success = "Loan settlement approved successfully by " . $_SESSION['user'] . "!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error settling loan or insufficient payment.";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'Add Loan' : 'View Loans'; ?> - Maranadhara Samithi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2D3748;
            --secondary: #4A5568;
            --accent: #F97316;
            --success: #10B981;
            --danger: #EF4444;
            --background: #F7FAFC;
            --sidebar-width: 240px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--primary);
            margin: 0;
        }

        .main-content {
            margin-left: calc(var(--sidebar-width) + 2rem);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #DD6B20;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .form-table th, .form-table td {
            padding: 0.75rem;
            vertical-align: middle;
        }

        .form-table th {
            text-align: right;
            width: 30%;
            color: var(--secondary);
            font-weight: 600;
        }

        .form-table td {
            width: 70%;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
            outline: none;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .table th {
            background: #EDF2F7;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--secondary);
        }

        .table td {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid #E2E8F0;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
        }

        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
        }

        .searchable-select {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.5rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .form-table th, .form-table td {
                display: block;
                width: 100%;
                text-align: left;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen pt-20">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content flex-1">
        <div class="container">
            <!-- Add Loan Section -->
            <?php if ($action === 'add'): ?>
                <div class="card">
                    <h1 class="text-2xl font-bold mb-8 text-[var(--accent)]">Add Loan</h1>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Loan Calculator -->
                    <form method="POST" class="mb-8">
                        <h3 class="text-lg font-medium mb-4">Loan Calculator</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="calc_amount">Amount (LKR)</label></th>
                                <td><input type="number" id="calc_amount" name="amount" class="form-control" step="0.01" required></td>
                            </tr>
                            <tr>
                                <th><label for="calc_interest">Interest Rate (%)</label></th>
                                <td><input type="number" id="calc_interest" name="interest_rate" class="form-control" step="0.01" required></td>
                            </tr>
                            <tr>
                                <th><label for="calc_duration">Duration (Months)</label></th>
                                <td><input type="number" id="calc_duration" name="duration" class="form-control" required></td>
                            </tr>
                            <tr>
                                <th><label for="calc_start_date">Start Date</label></th>
                                <td><input type="date" id="calc_start_date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></td>
                            </tr>
                        </table>
                        <div class="text-right">
                            <button type="submit" name="calculate" class="btn btn-primary">
                                <i class="fas fa-calculator"></i> Calculate
                            </button>
                        </div>
                    </form>

                    <?php if ($loan_breakdown): ?>
                        <div class="bg-gray-100 p-4 rounded-lg mb-8">
                            <table class="form-table">
                                <tr>
                                    <th>Loan Amount:</th>
                                    <td>LKR <?php echo $loan_breakdown['amount']; ?></td>
                                </tr>
                                <tr>
                                    <th>Monthly Payment:</th>
                                    <td>LKR <?php echo $loan_breakdown['monthly_payment']; ?></td>
                                </tr>
                                <tr>
                                    <th>Total Interest:</th>
                                    <td>LKR <?php echo $loan_breakdown['interest']; ?></td>
                                </tr>
                                <tr>
                                    <th>Total Payable:</th>
                                    <td>LKR <?php echo $loan_breakdown['total_payable']; ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td><?php echo $loan_breakdown['end_date']; ?></td>
                                </tr>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Loan Application Form -->
                    <form method="POST">
                        <table class="form-table">
                            <tr>
                                <th><label for="member_id">Member</label></th>
                                <td>
                                    <select id="member_id" name="member_id" class="form-control" required>
                                        <option value="">Select Member</option>
                                        <?php foreach ($members as $m): ?>
                                            <option value="<?php echo $m['id']; ?>">
                                                <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="amount">Amount (LKR)</label></th>
                                <td><input type="number" id="amount" name="amount" class="form-control" step="0.01" value="<?php echo $loan_breakdown['amount'] ?? ''; ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="interest_rate">Interest Rate (%)</label></th>
                                <td><input type="number" id="interest_rate" name="interest_rate" class="form-control" step="0.01" value="<?php echo $loan_breakdown ? $_POST['interest_rate'] : ''; ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="duration">Duration (Months)</label></th>
                                <td><input type="number" id="duration" name="duration" class="form-control" value="<?php echo $loan_breakdown ? $_POST['duration'] : ''; ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="monthly_payment">Monthly Payment (LKR)</label></th>
                                <td><input type="number" id="monthly_payment" name="monthly_payment" class="form-control" step="0.01" value="<?php echo $loan_breakdown['monthly_payment'] ?? ''; ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="total_payable">Total Payable (LKR)</label></th>
                                <td><input type="number" id="total_payable" name="total_payable" class="form-control" step="0.01" value="<?php echo $loan_breakdown['total_payable'] ?? ''; ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="start_date">Start Date</label></th>
                                <td><input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo isset($loan_breakdown) && isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d'); ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="end_date">End Date</label></th>
                                <td><input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $loan_breakdown['end_date'] ?? ''; ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="remarks">Remarks</label></th>
                                <td><textarea id="remarks" name="remarks" class="form-control" rows="3"><?php echo $loan_breakdown ? '' : ''; ?></textarea></td>
                            </tr>
                        </table>
                        <div class="text-right">
                            <button type="reset" class="btn btn-danger mr-4">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <button type="submit" name="add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- View Loans Section -->
            <?php if ($action === 'view'): ?>
                <div class="card">
                    <h1 class="text-2xl font-bold mb-8 text-[var(--accent)]">View Loans</h1>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="GET" class="mb-6">
                        <input type="hidden" name="action" value="view">
                        <table class="form-table">
                            <tr>
                                <th><label for="filter_member_id">Filter by Member</label></th>
                                <td>
                                    <div class="searchable-select">
                                        <input type="text" id="member-search" class="search-input" placeholder="Search members...">
                                        <select id="filter_member_id" name="member_id" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Members</option>
                                            <?php foreach ($members as $m): ?>
                                                <option value="<?php echo $m['id']; ?>" <?php echo $selected_member_id == $m['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Amount</th>
                                <th>Monthly</th>
                                <th>Total Paid</th>
                                <th>Total Due</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($loans as $l):
                                $m = $member->getMemberById($l['member_id']);
                                $remaining = $l['total_payable'] - $l['total_paid'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['member_id'] ?? 'Unknown'); ?></td>
                                    <td>LKR <?php echo number_format($l['amount'], 2); ?></td>
                                    <td>LKR <?php echo number_format($l['monthly_payment'], 2); ?></td>
                                    <td>LKR <?php echo number_format($l['total_paid'], 2); ?></td>
                                    <td>LKR <?php echo number_format($remaining, 2); ?></td>
                                    <td>
                                                <span class="px-2 py-1 rounded-full text-sm <?php
                                                echo $l['status'] === 'Settled' ? 'bg-green-100 text-green-800' :
                                                    ($l['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' :
                                                        'bg-blue-100 text-blue-800');
                                                ?>">
                                                    <?php echo $l['status']; ?>
                                                </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($l['confirmed_by'] ?? 'N/A'); ?></td>
                                    <td class="space-x-2">
                                        <button class="btn-icon view-details" data-loan='<?php echo json_encode($l); ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($l['status'] === 'Applied'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="submit" name="approve" class="btn btn-success" onclick="return confirm('Approve this loan to Pending status?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Delete this loan?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($l['status'] === 'Pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="submit" name="settle" class="btn btn-success" onclick="return confirm('Approve settlement of this loan?')" <?php echo $remaining > 0 ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-check-double"></i> Settle
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($loans)): ?>
                                <tr><td colspan="8" class="text-center py-4">No loans found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal -->
<div id="loan-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-6 max-w-lg w-full">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-[var(--accent)]">Loan Details</h2>
            <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="loan-details" class="space-y-3"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Modal functionality
        const modal = document.getElementById('loan-modal');
        const closeModal = document.getElementById('close-modal');
        const loanDetails = document.getElementById('loan-details');
        const viewButtons = document.querySelectorAll('.view-details');

        viewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const loan = JSON.parse(button.getAttribute('data-loan'));
                loanDetails.innerHTML = `
                        <p><strong>Member ID:</strong> ${loan.member_id}</p>
                        <p><strong>Amount:</strong> LKR ${Number(loan.amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Interest Rate:</strong> ${loan.interest_rate}%</p>
                        <p><strong>Duration:</strong> ${loan.duration} months</p>
                        <p><strong>Monthly Payment:</strong> LKR ${Number(loan.monthly_payment).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Total Payable:</strong> LKR ${Number(loan.total_payable).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Total Paid:</strong> LKR ${Number(loan.total_paid).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Start Date:</strong> ${loan.start_date}</p>
                        <p><strong>End Date:</strong> ${loan.end_date}</p>
                        <p><strong>Status:</strong> ${loan.status}</p>
                        <p><strong>Approved By:</strong> ${loan.confirmed_by || 'N/A'}</p>
                        <p><strong>Remarks:</strong> ${loan.remarks || 'N/A'}</p>
                    `;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });

        closeModal.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });

        // Searchable member filter
        const searchInput = document.getElementById('member-search');
        const select = document.getElementById('filter_member_id');
        const options = Array.from(select.options);

        searchInput.addEventListener('input', () => {
            const searchTerm = searchInput.value.toLowerCase();
            options.forEach(option => {
                const text = option.text.toLowerCase();
                option.style.display = text.includes(searchTerm) || option.value === '' ? '' : 'none';
            });
        });
    });
</script>
</body>
</html>