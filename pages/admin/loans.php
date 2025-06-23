<?php
define('APP_START', true);
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt in loans.php - Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
    header("Location: ../../admin-login.php");
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
            try {
                if ($loan->deleteLoan($id)) {
                    error_log("Loan ID $id deleted by user {$_SESSION['db_username']}");
                    $success = "Loan deleted successfully!";
                    $loans = $loan->getAllLoans($selected_member_id);
                } else {
                    throw new Exception("Failed to delete loan. It may not exist or is not in 'Applied' status.");
                }
            } catch (Exception $e) {
                error_log("Error in loans.php: " . $e->getMessage());
                $error = "Error: " . htmlspecialchars($e->getMessage());
            }
        } elseif (isset($_POST['calculate'])) {
            $loan_breakdown = $loan->calculateLoanBreakdown(
                floatval($_POST['amount']),
                floatval($_POST['interest_rate']),
                intval($_POST['duration']),
                $_POST['start_date'] ?? date('Y-m-d')
            );
            $success = "Loan calculation completed successfully!";
        } elseif (isset($_POST['approve'])) {
            if ($loan->approveLoan($_POST['id'], $_SESSION['db_username'])) {
                $success = "Loan approved successfully!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error approving loan.";
            }
        } elseif (isset($_POST['settle'])) {
            if ($loan->settleLoan($_POST['id'], $_SESSION['db_username'])) {
                $success = "Loan settlement approved successfully!";
                $loans = $loan->getAllLoans($selected_member_id);
            } else {
                $error = "Error settling loan or insufficient payment.";
            }
        } elseif (isset($_POST['download_csv'])) {
            $from_date = $_POST['from_date'] ?? '';
            $to_date = $_POST['to_date'] ?? '';
            $conn = (new Database())->getConnection();
            $query = "
                SELECT l.*, m.member_id as member_code, m.full_name, COALESCE(SUM(p.amount), 0) as total_paid
                FROM loans l
                LEFT JOIN members m ON l.member_id = m.id
                LEFT JOIN payments p ON l.id = p.loan_id 
                    AND p.payment_type = 'Loan Settlement' 
                    AND p.is_confirmed = TRUE
                WHERE l.start_date BETWEEN ? AND ?
                GROUP BY l.id
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $from_date, $to_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $loans_for_csv = $result->fetch_all(MYSQLI_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="loans_' . date('Ymd') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Member ID', 'Member Name', 'Amount', 'Interest Rate', 'Duration', 'Monthly Payment', 'Total Payable', 'Total Paid', 'Start Date', 'End Date', 'Status', 'Confirmed By (DB Username)', 'Remarks']);
            foreach ($loans_for_csv as $loan) {
                fputcsv($output, [
                    $loan['member_code'],
                    $loan['full_name'],
                    $loan['amount'],
                    $loan['interest_rate'],
                    $loan['duration'],
                    $loan['monthly_payment'],
                    $loan['total_payable'],
                    $loan['total_paid'],
                    $loan['start_date'],
                    $loan['end_date'],
                    $loan['status'],
                    $loan['confirmed_by'] ?? 'N/A',
                    $loan['remarks'] ?? 'N/A'
                ]);
            }
            fclose($output);
            exit;
        }
    } catch (Exception $e) {
        error_log("Error in loans.php: " . $e->getMessage());
        $error = "Error: " . htmlspecialchars($e->getMessage());
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
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/incidents.css">
    
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen pt-16">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content flex-1">
        <div class="container">
            <!-- Add Loan Section -->
            <?php if ($action === 'add'): ?>
                <div class="card">
                    <h1 class="text-3xl font-bold mb-6 text-[var(--accent)]">Add New Loan</h1>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Loan Calculator -->
                    <form method="POST" class="mb-8" id="calculator-form">
                        <h3 class="text-xl font-semibold mb-4 text-[var(--primary)]">Loan Calculator</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="calc_amount" class="form-label">
                                    <i class="ri-money-dollar-circle-line text-gray-400"></i> Amount (LKR)
                                </label>
                                <input type="number" id="calc_amount" name="amount" class="form-control" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="calc_interest" class="form-label">
                                    <i class="ri-percent-line text-gray-400"></i> Interest Rate (%)
                                </label>
                                <input type="number" id="calc_interest" name="interest_rate" class="form-control" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="calc_duration" class="form-label">
                                    <i class="ri-calendar-line text-gray-400"></i> Duration (Months)
                                </label>
                                <input type="number" id="calc_duration" name="duration" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="calc_start_date" class="form-label">
                                    <i class="ri-calendar-2-line text-gray-400"></i> Start Date
                                </label>
                                <input type="date" id="calc_start_date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" name="calculate" id="calculate-btn" class="btn btn-primary">
                                <i class="ri-calculator-line"></i> Calculate
                            </button>
                        </div>
                    </form>

                    <?php if ($loan_breakdown): ?>
                        <div class="bg-gray-50 p-6 rounded-lg mb-8">
                            <h4 class="text-lg font-semibold mb-4">Loan Breakdown</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div><strong>Loan Amount:</strong> LKR <?php echo $loan_breakdown['amount']; ?></div>
                                <div><strong>Monthly Payment:</strong> LKR <?php echo $loan_breakdown['monthly_payment']; ?></div>
                                <div><strong>Total Interest:</strong> LKR <?php echo $loan_breakdown['interest']; ?></div>
                                <div><strong>Total Payable:</strong> LKR <?php echo $loan_breakdown['total_payable']; ?></div>
                                <div><strong>End Date:</strong> <?php echo $loan_breakdown['end_date']; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Loan Application Form -->
                    <form method="POST" id="application-form">
                        <h3 class="text-xl font-semibold mb-4 text-[var(--primary)]">Loan Application</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="member_id" class="form-label">
                                    <i class="ri-user-line text-gray-400"></i> Member
                                </label>
                                <select id="member_id" name="member_id" class="form-control" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo $m['id']; ?>">
                                            <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="amount" class="form-label">
                                    <i class="ri-money-dollar-circle-line text-gray-400"></i> Amount (LKR)
                                </label>
                                <input type="number" id="amount" name="amount" class="form-control" step="0.01" value="<?php echo isset($loan_breakdown['amount']) ? $loan_breakdown['amount'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="interest_rate" class="form-label">
                                    <i class="ri-percent-line text-gray-400"></i> Interest Rate (%)
                                </label>
                                <input type="number" id="interest_rate" name="interest_rate" class="form-control" step="0.01" value="<?php echo isset($loan_breakdown) ? $_POST['interest_rate'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="duration" class="form-label">
                                    <i class="ri-calendar-line text-gray-400"></i> Duration (Months)
                                </label>
                                <input type="number" id="duration" name="duration" class="form-control" value="<?php echo isset($loan_breakdown) ? $_POST['duration'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="monthly_payment" class="form-label">
                                    <i class="ri-wallet-line text-gray-400"></i> Monthly Payment (LKR)
                                </label>
                                <input type="number" id="monthly_payment" name="monthly_payment" class="form-control" step="0.01" value="<?php echo isset($loan_breakdown['monthly_payment']) ? $loan_breakdown['monthly_payment'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="total_payable" class="form-label">
                                    <i class="ri-money-dollar-circle-line text-gray-400"></i> Total Payable (LKR)
                                </label>
                                <input type="number" id="total_payable" name="total_payable" class="form-control" step="0.01" value="<?php echo isset($loan_breakdown['total_payable']) ? $loan_breakdown['total_payable'] : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="start_date" class="form-label">
                                    <i class="ri-calendar-2-line text-gray-400"></i> Start Date
                                </label>
                                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo isset($loan_breakdown) ? $_POST['start_date'] : date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date" class="form-label">
                                    <i class="ri-calendar-2-line text-gray-400"></i> End Date
                                </label>
                                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo isset($loan_breakdown['end_date']) ? $loan_breakdown['end_date'] : ''; ?>" required>
                            </div>
                            <div class="form-group col-span-2">
                                <label for="remarks" class="form-label">
                                    <i class="ri-chat-3-line text-gray-400"></i> Remarks
                                </label>
                                <textarea id="remarks" name="remarks" class="form-control" rows="4" placeholder="Add any additional notes..."></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end gap-4">
                            <button type="button" id="cancel-btn" class="btn btn-danger">
                                <i class="ri-close-line"></i> Cancel
                            </button>
                            <button type="submit" name="add" id="submit-btn" class="btn btn-primary">
                                <i class="ri-add-line"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- View Loans Section -->
            <?php if ($action === 'view'): ?>
                <div class="card">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-3xl font-bold text-[var(--accent)]">Manage Loans</h1>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="ri-add-line"></i> Add Loan
                        </a>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="GET" class="mb-6">
                        <input type="hidden" name="action" value="view">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-group">
                                <label for="filter_member_id" class="form-label">
                                    <i class="ri-user-line text-gray-400"></i> Filter by Member
                                </label>
                                <div class="searchable-select">
                                    <input type="text" id="member-search" class="form-control" placeholder="Search members...">
                                    <select id="filter_member_id" name="member_id" class="form-control hidden" onchange="this.form.submit()">
                                        <option value="">All Members</option>
                                        <?php foreach ($members as $m): ?>
                                            <option value="<?php echo $m['id']; ?>" <?php echo $selected_member_id == $m['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="member-dropdown" class="dropdown-menu hidden absolute w-full z-10"></div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="from_date" class="form-label">
                                    <i class="ri-calendar-2-line text-gray-400"></i> From Date
                                </label>
                                <input type="date" id="from_date" name="from_date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="to_date" class="form-label">
                                    <i class="ri-calendar-2-line text-gray-400"></i> To Date
                                </label>
                                <input type="date" id="to_date" name="to_date" class="form-control">
                            </div>
                        </div>
                    </form>

                    <div class="flex justify-end mb-4">
                        <button id="download-csv" class="btn btn-primary">
                            <i class="ri-download-line"></i> Download CSV
                        </button>
                    </div>

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
                                <th>Confirmed By</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($loans as $l):
                                $m = $member->getMemberById($l['member_id']);
                                $remaining = $l['total_payable'] - $l['total_paid'];
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td><?php echo htmlspecialchars($m['member_id'] ?? 'Unknown'); ?></td>
                                    <td>LKR <?php echo number_format($l['amount'], 2); ?></td>
                                    <td>LKR <?php echo number_format($l['monthly_payment'], 2); ?></td>
                                    <td>LKR <?php echo number_format($l['total_paid'], 2); ?></td>
                                    <td>LKR <?php echo number_format($remaining, 2); ?></td>
                                    <td>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php
                                        echo $l['status'] === 'Settled' ? 'bg-green-100 text-green-800' :
                                            ($l['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' :
                                                'bg-blue-100 text-blue-800');
                                        ?>">
                                            <?php echo $l['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($l['confirmed_by'] ?? 'N/A'); ?></td>
                                    <td class="space-x-2">
                                        <button class="btn-icon view-details text-gray-500 hover:text-[var(--accent)]" data-loan='<?php echo json_encode($l); ?>' data-member='<?php echo json_encode($m); ?>' aria-label="View loan details">
                                            <i class="ri-eye-line"></i>
                                        </button>
                                        <?php if ($l['status'] === 'Applied'): ?>
                                            <form method="POST" class="inline" id="delete-form-<?php echo $l['id']; ?>">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="button" class="btn btn-danger delete-loan-btn" data-loan-id="<?php echo $l['id']; ?>" aria-label="Delete loan">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="inline" id="approve-form-<?php echo $l['id']; ?>">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="button" class="btn btn-success approve-loan-btn" data-loan-id="<?php echo $l['id']; ?>" aria-label="Approve loan">
                                                    <i class="ri-check-line"></i> Approve
                                                </button>
                                            </form>
                                        <?php elseif ($l['status'] === 'Pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="submit" name="settle" class="btn btn-success" onclick="return confirm('Approve settlement of this loan?')" <?php echo $remaining > 0 ? 'disabled' : ''; ?> aria-label="Settle loan">
                                                    <i class="ri-check-double-line"></i> Settle
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($loans)): ?>
                                <tr><td colspan="8" class="text-center py-6 text-gray-500">No loans found.</td></tr>
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
<div id="loan-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center modal" role="dialog" aria-labelledby="modal-title">
    <div class="bg-white rounded-xl p-8 max-w-md w-full modal-content">
        <div class="flex justify-between items-center mb-6">
            <h2 id="modal-title" class="text-2xl font-semibold text-[var(--accent)]">Loan Details</h2>
            <button id="close-modal" class="text-gray-500 hover:text-gray-700" aria-label="Close modal">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div id="loan-details" class="space-y-4 text-gray-700"></div>
    </div>
</div>

<!-- Popup Overlay -->
<div id="popup-overlay" class="popup-overlay"></div>

<!-- Success Popup -->
<div id="success-popup" class="popup popup-success" role="alertdialog" aria-labelledby="success-title">
    <i class="ri-checkbox-circle-fill popup-icon"></i>
    <h2 id="success-title">Success</h2>
    <p id="success-message"></p>
</div>

<!-- Error Popup -->
<div id="error-popup" class="popup popup-error" role="alertdialog" aria-labelledby="error-title">
    <i class="ri-error-warning-fill popup-icon"></i>
    <h2 id="error-title">Error</h2>
    <p id="error-message"></p>
</div>

<!-- Confirm Delete Popup -->
<div id="confirm-delete-popup" class="popup popup-confirm" role="dialog" aria-labelledby="confirm-title">
    <i class="ri-error-warning-fill popup-icon"></i>
    <h2 id="confirm-title">Confirm Deletion</h2>
    <p>Are you sure you want to delete this loan? This action cannot be undone.</p>
    <div class="flex justify-end gap-4">
        <button id="cancel-delete-btn" class="btn btn-secondary">Cancel</button>
        <button id="confirm-delete-btn" class="btn btn-danger">Delete</button>
    </div>
</div>

<!-- Confirm Approve Popup -->
<div id="confirm-approve-popup" class="popup popup-confirm" role="dialog" aria-labelledby="confirm-approve-title">
    <i class="ri-error-warning-fill popup-icon"></i>
    <h2 id="confirm-approve-title">Confirm Approval</h2>
    <p>Are you sure you want to approve this loan to Pending status?</p>
    <div class="flex justify-end gap-4">
        <button id="cancel-approve-btn" class="btn btn-secondary">Cancel</button>
        <button id="confirm-approve-btn" class="btn btn-success">Approve</button>
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
            const member = JSON.parse(button.getAttribute('data-member'));
            loanDetails.innerHTML = `
                <div class="grid grid-cols-1 gap-3">
                    <p><strong class="text-[var(--secondary)]">Member ID:</strong> ${member.member_id || 'N/A'}</p>
                    <p><strong class="text-[var(--secondary)]">Member Name:</strong> ${member.full_name || 'N/A'}</p>
                    <p><strong class="text-[var(--secondary)]">Amount:</strong> LKR ${Number(loan.amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                    <p><strong class="text-[var(--secondary)]">Interest Rate:</strong> ${loan.interest_rate}%</p>
                    <p><strong class="text-[var(--secondary)]">Duration:</strong> ${loan.duration} months</p>
                    <p><strong class="text-[var(--secondary)]">Monthly Payment:</strong> LKR ${Number(loan.monthly_payment).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                    <p><strong class="text-[var(--secondary)]">Total Payable:</strong> LKR ${Number(loan.total_payable).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                    <p><strong class="text-[var(--secondary)]">Total Paid:</strong> LKR ${Number(loan.total_paid).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                    <p><strong class="text-[var(--secondary)]">Start Date:</strong> ${loan.start_date}</p>
                    <p><strong class="text-[var(--secondary)]">End Date:</strong> ${loan.end_date}</p>
                    <p><strong class="text-[var(--secondary)]">Status:</strong> ${loan.status}</p>
                    <p><strong class="text-[var(--secondary)]">Confirmed By:</strong> ${loan.confirmed_by || 'N/A'}</p>
                    <p><strong class="text-[var(--secondary)]">Remarks:</strong> ${loan.remarks || 'N/A'}</p>
                </div>
            `;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
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

        // Custom searchable dropdown
        const searchInput = document.getElementById('member-search');
        const select = document.getElementById('filter_member_id');
        const dropdown = document.getElementById('member-dropdown');
        const options = Array.from(select.options);

        const updateDropdown = (searchTerm = '') => {
            dropdown.innerHTML = '';
            const filteredOptions = options.filter(option =>
                option.value === '' ||
                option.text.toLowerCase().includes(searchTerm.toLowerCase())
            );

            filteredOptions.forEach(option => {
                const item = document.createElement('div');
                item.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer';
                item.textContent = option.text;
                item.dataset.value = option.value;
                item.addEventListener('click', () => {
                    select.value = option.value;
                    searchInput.value = option.text;
                    dropdown.classList.add('hidden');
                    select.form.submit();
                });
                dropdown.appendChild(item);
            });

            dropdown.classList.toggle('hidden', filteredOptions.length === 0);
        };

        searchInput.addEventListener('input', () => updateDropdown(searchInput.value));
        searchInput.addEventListener('focus', () => updateDropdown(searchInput.value));
        searchInput.addEventListener('click', () => updateDropdown(searchInput.value));
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const inputs = form.querySelectorAll('input[required], select[required]');
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value) {
                        valid = false;
                        input.classList.add('border-red-500');
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                if (!valid) {
                    e.preventDefault();
                    showPopup(errorPopup, 'Please fill in all required fields.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                }
            });
        });

        // Auto-populate application form from calculator
        <?php if ($loan_breakdown): ?>
        document.getElementById('amount').value = '<?php echo $loan_breakdown['amount']; ?>';
        document.getElementById('interest_rate').value = '<?php echo $_POST['interest_rate']; ?>';
        document.getElementById('duration').value = '<?php echo $_POST['duration']; ?>';
        document.getElementById('monthly_payment').value = '<?php echo $loan_breakdown['monthly_payment']; ?>';
        document.getElementById('total_payable').value = '<?php echo $loan_breakdown['total_payable']; ?>';
        document.getElementById('start_date').value = '<?php echo $_POST['start_date']; ?>';
        document.getElementById('end_date').value = '<?php echo $loan_breakdown['end_date']; ?>';
        <?php endif; ?>

        // Popup functionality
        const popupOverlay = document.getElementById('popup-overlay');
        const successPopup = document.getElementById('success-popup');
        const errorPopup = document.getElementById('error-popup');
        const confirmDeletePopup = document.getElementById('confirm-delete-popup');
        const confirmApprovePopup = document.getElementById('confirm-approve-popup');
        const submitBtn = document.getElementById('submit-btn');
        const cancelBtn = document.getElementById('cancel-btn');
        const calculatorForm = document.getElementById('calculator-form');
        const applicationForm = document.getElementById('application-form');

        function showPopup(popup, message = '') {
            if (message) {
                const messageEl = popup.querySelector('p');
                if (messageEl) messageEl.innerHTML = message;
            }
            popupOverlay.classList.add('show');
            popup.classList.add('show');
            popup.focus();
        }

        function hidePopup(popup) {
            popupOverlay.classList.remove('show');
            popup.classList.remove('show');
        }

        function startCountdown(elementId, redirectUrl) {
            let timeLeft = 3;
            const countdown = document.getElementById(elementId);
            if (countdown) {
                countdown.textContent = timeLeft;
                const interval = setInterval(() => {
                    timeLeft--;
                    countdown.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        window.location.href = redirectUrl;
                    }
                }, 1000);
            }
        }

        // Submit application
        if (submitBtn) {
            submitBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const inputs = applicationForm.querySelectorAll('input[required], select[required]');
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value) {
                        valid = false;
                        input.classList.add('border-red-500');
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                if (!valid) {
                    showPopup(errorPopup, 'Please fill in all required fields.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                    return;
                }
                const formData = new FormData(applicationForm);
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(response => response.text()).then(html => {
                    if (html.includes('Error adding loan')) {
                        showPopup(errorPopup, 'Error adding loan. Member may have an unsettled loan.');
                        setTimeout(() => hidePopup(errorPopup), 3000);
                    } else {
                        showPopup(successPopup, 'Loan application submitted successfully! Redirecting in <span id="success-countdown"></span> seconds...');
                        startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
                    }
                }).catch(() => {
                    showPopup(errorPopup, 'Error connecting to server.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                });
            });
        }

        // Cancel button
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                showPopup(errorPopup, 'Action cancelled. Redirecting in <span id="cancel-countdown"></span> seconds...');
                startCountdown('cancel-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
            });
        }

        // Delete loan functionality
        const deleteButtons = document.querySelectorAll('.delete-loan-btn');
        let activeDeleteForm = null;

        deleteButtons.forEach(button => {
            button.addEventListener('click', () => {
                activeDeleteForm = button.closest('form');
                showPopup(confirmDeletePopup);
            });
        });

        document.getElementById('cancel-delete-btn')?.addEventListener('click', () => {
            hidePopup(confirmDeletePopup);
            activeDeleteForm = null;
        });

        document.getElementById('confirm-delete-btn')?.addEventListener('click', () => {
            if (activeDeleteForm) {
                const formData = new FormData(activeDeleteForm);
                formData.append('delete', '1');
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.ok) {
                        showPopup(successPopup, 'Loan deleted successfully! Redirecting in <span id="success-countdown"></span> seconds...');
                        startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
                    } else {
                        showPopup(errorPopup, 'Failed to delete loan.');
                        setTimeout(() => hidePopup(errorPopup), 3000);
                    }
                }).catch(() => {
                    showPopup(errorPopup, 'Error connecting to server.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                });
                hidePopup(confirmDeletePopup);
            }
        });

        // Approve loan functionality
        const approveButtons = document.querySelectorAll('.approve-loan-btn');
        let activeApproveForm = null;

        approveButtons.forEach(button => {
            button.addEventListener('click', () => {
                activeApproveForm = button.closest('form');
                showPopup(confirmApprovePopup);
            });
        });

        document.getElementById('cancel-approve-btn')?.addEventListener('click', () => {
            hidePopup(confirmApprovePopup);
            activeApproveForm = null;
        });

        document.getElementById('confirm-approve-btn')?.addEventListener('click', () => {
            if (activeApproveForm) {
                const formData = new FormData(activeApproveForm);
                formData.append('approve', '1');
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.ok) {
                        showPopup(successPopup, 'Loan approved successfully! Redirecting in <span id="success-countdown"></span> seconds...');
                        startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
                    } else {
                        showPopup(errorPopup, 'Failed to approve loan.');
                        setTimeout(() => hidePopup(errorPopup), 3000);
                    }
                }).catch(() => {
                    showPopup(errorPopup, 'Error connecting to server.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                });
                hidePopup(confirmApprovePopup);
            }
        });

        // Keyboard navigation for popups
        [successPopup, errorPopup, confirmDeletePopup, confirmApprovePopup].forEach(popup => {
            popup.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    hidePopup(popup);
                    if (popup === confirmDeletePopup) activeDeleteForm = null;
                    if (popup === confirmApprovePopup) activeApproveForm = null;
                }
            });
        });

        if (calculatorForm) {
            calculatorForm.addEventListener('submit', (e) => {
                if (e.submitter.name === 'calculate') {
                    e.preventDefault();
                    const formData = new FormData(calculatorForm);
                    fetch('', {
                        method: 'POST',
                        body: formData
                    }).then(response => response.text()).then(() => {
                        showPopup(successPopup, 'Loan calculation completed successfully!');
                        setTimeout(() => {
                            hidePopup(successPopup);
                            window.location.reload();
                        }, 1500);
                    });
                }
            });
        }

        // CSV Download
        const downloadCsvBtn = document.getElementById('download-csv');
        if (downloadCsvBtn) {
            downloadCsvBtn.addEventListener('click', () => {
                const fromDate = document.getElementById('from_date').value;
                const toDate = document.getElementById('to_date').value;
                if (!fromDate || !toDate) {
                    showPopup(errorPopup, 'Please select both From and To dates.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.style.display = 'none';
                const fromInput = document.createElement('input');
                fromInput.type = 'hidden';
                fromInput.name = 'from_date';
                fromInput.value = fromDate;
                const toInput = document.createElement('input');
                toInput.type = 'hidden';
                toInput.name = 'to_date';
                toInput.value = toDate;
                const downloadInput = document.createElement('input');
                downloadInput.type = 'hidden';
                downloadInput.name = 'download_csv';
                downloadInput.value = '1';
                form.appendChild(fromInput);
                form.appendChild(toInput);
                form.appendChild(downloadInput);
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Show popups for PHP messages
        <?php if ($success && !isset($_POST['calculate']) && !isset($_POST['delete']) && !isset($_POST['approve']) && !isset($_POST['settle'])): ?>
        showPopup(successPopup, '<?php echo htmlspecialchars($success); ?> Redirecting in <span id="success-countdown"></span> seconds...');
        startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
        <?php endif; ?>

        <?php if ($error && (isset($_POST['delete']) || isset($_POST['add']))): ?>
        showPopup(errorPopup, '<?php echo htmlspecialchars($error); ?>');
        setTimeout(() => hidePopup(errorPopup), 3000);
        <?php endif; ?>
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

    // Custom searchable dropdown
    const searchInput = document.getElementById('member-search');
    const select = document.getElementById('filter_member_id');
    const dropdown = document.getElementById('member-dropdown');
    const options = Array.from(select.options);

    const updateDropdown = (searchTerm = '') => {
        dropdown.innerHTML = '';
        const filteredOptions = options.filter(option => 
            option.value === '' || 
            option.text.toLowerCase().includes(searchTerm.toLowerCase())
        );

        filteredOptions.forEach(option => {
            const item = document.createElement('div');
            item.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer';
            item.textContent = option.text;
            item.dataset.value = option.value;
            item.addEventListener('click', () => {
                select.value = option.value;
                searchInput.value = option.text;
                dropdown.classList.add('hidden');
                select.form.submit();
            });
            dropdown.appendChild(item);
        });

        dropdown.classList.toggle('hidden', filteredOptions.length === 0);
    };

    searchInput.addEventListener('input', () => updateDropdown(searchInput.value));
    searchInput.addEventListener('focus', () => updateDropdown(searchInput.value));
    searchInput.addEventListener('click', () => updateDropdown(searchInput.value));
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const inputs = form.querySelectorAll('input[required], select[required]');
            let valid = true;
            inputs.forEach(input => {
                if (!input.value) {
                    valid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            if (!valid) {
                e.preventDefault();
                showPopup(errorPopup, 'Please fill in all required fields.');
                setTimeout(() => hidePopup(errorPopup), 3000);
            }
        });
    });

    // Auto-populate application form from calculator
    <?php if ($loan_breakdown): ?>
        document.getElementById('amount').value = '<?php echo $loan_breakdown['amount']; ?>';
        document.getElementById('interest_rate').value = '<?php echo $_POST['interest_rate']; ?>';
        document.getElementById('duration').value = '<?php echo $_POST['duration']; ?>';
        document.getElementById('monthly_payment').value = '<?php echo $loan_breakdown['monthly_payment']; ?>';
        document.getElementById('total_payable').value = '<?php echo $loan_breakdown['total_payable']; ?>';
        document.getElementById('start_date').value = '<?php echo $_POST['start_date']; ?>';
        document.getElementById('end_date').value = '<?php echo $loan_breakdown['end_date']; ?>';
    <?php endif; ?>

    // Popup functionality
    const popupOverlay = document.getElementById('popup-overlay');
    const successPopup = document.getElementById('success-popup');
    const errorPopup = document.getElementById('error-popup');
    const confirmDeletePopup = document.getElementById('confirm-delete-popup');
    const confirmApprovePopup = document.getElementById('confirm-approve-popup');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const calculatorForm = document.getElementById('calculator-form');
    const applicationForm = document.getElementById('application-form');

    function showPopup(popup, message = '') {
        if (message) {
            const messageEl = popup.querySelector('p');
            if (messageEl) messageEl.innerHTML = message;
        }
        popupOverlay.classList.add('show');
        popup.classList.add('show');
        popup.focus();
    }

    function hidePopup(popup) {
        popupOverlay.classList.remove('show');
        popup.classList.remove('show');
    }

    function startCountdown(elementId, redirectUrl) {
        let timeLeft = 3;
        const countdown = document.getElementById(elementId);
        if (countdown) {
            countdown.textContent = timeLeft;
            const interval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    window.location.href = redirectUrl;
                }
            }, 1000);
        }
    }

    // Submit application
    if (submitBtn) {
        submitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const inputs = applicationForm.querySelectorAll('input[required], select[required]');
            let valid = true;
            inputs.forEach(input => {
                if (!input.value) {
                    valid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            if (!valid) {
                showPopup(errorPopup, 'Please fill in all required fields.');
                setTimeout(() => hidePopup(errorPopup), 3000);
                return;
            }
            const formData = new FormData(applicationForm);
            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(html => {
                if (html.includes('Error adding loan')) {
                    showPopup(errorPopup, 'Error adding loan. Member may have an unsettled loan.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                } else {
                    showPopup(successPopup, 'Loan application submitted successfully! Redirecting in <span id="success-countdown"></span> seconds...');
                    startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
                }
            }).catch(() => {
                showPopup(errorPopup, 'Error connecting to server.');
                setTimeout(() => hidePopup(errorPopup), 3000);
            });
        });
    }

    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            showPopup(errorPopup, 'Action cancelled. Redirecting in <span id="cancel-countdown"></span> seconds...');
            startCountdown('cancel-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
        });
    }

    // Delete loan functionality
    const deleteButtons = document.querySelectorAll('.delete-loan-btn');
    let activeDeleteForm = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeDeleteForm = button.closest('form');
            showPopup(confirmDeletePopup);
        });
    });

    document.getElementById('cancel-delete-btn')?.addEventListener('click', () => {
        hidePopup(confirmDeletePopup);
        activeDeleteForm = null;
    });

    document.getElementById('confirm-delete-btn')?.addEventListener('click', () => {
        if (activeDeleteForm) {
            const formData = new FormData(activeDeleteForm);
            formData.append('delete', '1');
            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    showPopup(successPopup, 'Loan deleted successfully! Redirecting in <span id="success-countdown"></span> seconds...');
                    startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
                } else {
                    showPopup(errorPopup, 'Failed to delete loan.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                }
            }).catch(() => {
                showPopup(errorPopup, 'Error connecting to server.');
                setTimeout(() => hidePopup(errorPopup), 3000);
            });
            hidePopup(confirmDeletePopup);
        }
    });

    // Approve loan functionality
    const approveButtons = document.querySelectorAll('.approve-loan-btn');
    let activeApproveForm = null;

    approveButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeApproveForm = button.closest('form');
            showPopup(confirmApprovePopup);
        });
    });

    document.getElementById('cancel-approve-btn')?.addEventListener('click', () => {
        hidePopup(confirmApprovePopup);
        activeApproveForm = null;
    });

    document.getElementById('confirm-approve-btn')?.addEventListener('click', () => {
        if (activeApproveForm) {
            const formData = new FormData(activeApproveForm);
            formData.append('approve', '1');
            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    showPopup(successPopup, 'Loan approved successfully! Redirecting in <span id="success-countdown"></span> seconds...');
                    startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
                } else {
                    showPopup(errorPopup, 'Failed to approve loan.');
                    setTimeout(() => hidePopup(errorPopup), 3000);
                }
            }).catch(() => {
                showPopup(errorPopup, 'Error connecting to server.');
                setTimeout(() => hidePopup(errorPopup), 3000);
            });
            hidePopup(confirmApprovePopup);
        }
    });

    // Keyboard navigation for popups
    [successPopup, errorPopup, confirmDeletePopup, confirmApprovePopup].forEach(popup => {
        popup.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hidePopup(popup);
                if (popup === confirmDeletePopup) activeDeleteForm = null;
                if (popup === confirmApprovePopup) activeApproveForm = null;
            }
        });
    });

    if (calculatorForm) {
        calculatorForm.addEventListener('submit', (e) => {
            if (e.submitter.name === 'calculate') {
                e.preventDefault();
                const formData = new FormData(calculatorForm);
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(response => response.text()).then(() => {
                    showPopup(successPopup, 'Loan calculation completed successfully!');
                    setTimeout(() => {
                        hidePopup(successPopup);
                        window.location.reload();
                    }, 1500);
                });
            }
        });
    }

    // CSV Download
    const downloadCsvBtn = document.getElementById('download-csv');
    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', () => {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            if (!fromDate || !toDate) {
                showPopup(errorPopup, 'Please select both From and To dates.');
                setTimeout(() => hidePopup(errorPopup), 3000);
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.style.display = 'none';
            const fromInput = document.createElement('input');
            fromInput.type = 'hidden';
            fromInput.name = 'from_date';
            fromInput.value = fromDate;
            const toInput = document.createElement('input');
            toInput.type = 'hidden';
            toInput.name = 'to_date';
            toInput.value = toDate;
            const downloadInput = document.createElement('input');
            downloadInput.type = 'hidden';
            downloadInput.name = 'download_csv';
            downloadInput.value = '1';
            form.appendChild(fromInput);
            form.appendChild(toInput);
            form.appendChild(downloadInput);
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Show popups for PHP messages
    <?php if ($success && !isset($_POST['calculate']) && !isset($_POST['delete']) && !isset($_POST['approve']) && !isset($_POST['settle'])): ?>
        showPopup(successPopup, '<?php echo htmlspecialchars($success); ?> Redirecting in <span id="success-countdown"></span> seconds...');
        startCountdown('success-countdown', 'loans.php?action=view<?php echo $selected_member_id ? "&member_id=$selected_member_id" : ""; ?>');
    <?php endif; ?>

    <?php if ($error && (isset($_POST['delete']) || isset($_POST['add']))): ?>
        showPopup(errorPopup, '<?php echo htmlspecialchars($error); ?>');
        setTimeout(() => hidePopup(errorPopup), 3000);
    <?php endif; ?>
});
</script>
</body>
</html>