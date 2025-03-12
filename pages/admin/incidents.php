<?php
define('APP_START', true);
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../classes/Member.php';
require_once '../../classes/Incident.php';
require_once '../../classes/Database.php';

$member = new Member();
$incident = new Incident();
$error = $success = '';

$members = $member->getAllMembers();
$incidents = $incident->getAllIncidents();

$action = isset($_GET['action']) ? $_GET['action'] : 'view';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $incident_id = $incident->generateIncidentId();
        $member_id = $_POST['member_id'];
        $incident_type = $_POST['incident_type'];
        $incident_datetime = $_POST['incident_datetime'];
        $remarks = $_POST['remarks'] ?: null;

        if ($incident->addIncident($incident_id, $member_id, $incident_type, $incident_datetime, null, null, $remarks)) {
            $success = "Incident '$incident_id' recorded successfully! Redirecting in <span id='countdown'>2</span> seconds...";
            header("Refresh: 2; url=dashboard.php");
        } else {
            $error = "Error recording incident.";
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("DELETE FROM incidents WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Incident deleted successfully!";
            $incidents = $incident->getAllIncidents();
        } else {
            $error = "Error deleting incident: " . $conn->error;
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $incident_type = $_POST['incident_type'];
        $incident_datetime = $_POST['incident_datetime'];
        $remarks = $_POST['remarks'];

        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("UPDATE incidents SET incident_type = ?, incident_datetime = ?, remarks = ? WHERE id = ?");
        $stmt->bind_param("sssi", $incident_type, $incident_datetime, $remarks, $id);
        if ($stmt->execute()) {
            $success = "Incident updated successfully!";
            $incidents = $incident->getAllIncidents();
        } else {
            $error = "Error updating incident: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action == 'add' ? 'Add Incident' : 'Manage Incidents'; ?> - Maranadhara Samithi</title>
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
            line-height: 1.6;
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
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-orange);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: var(--orange-dark);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-icon {
            background: none;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-icon:hover {
            background: var(--orange-light);
            color: var(--primary-orange);
        }

        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            transition: border-color 0.2s ease;
            background: #fff;
        }

        .input-field:focus {
            border-color: var(--primary-orange);
            outline: none;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            background: var(--card-bg);
        }

        .table th, .table td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table thead th {
            background: var(--card-bg);
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: rgba(249, 115, 22, 0.05);
        }

        .animate-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-section {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            background: #fafafa;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow);
            position: relative;
            animation: modalIn 0.3s ease-out;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.25rem;
            cursor: pointer;
        }

        .modal-close:hover {
            color: var(--primary-orange);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 16px;
            }
            .sidebar.expanded ~ .main-content {
                margin-left: calc(var(--sidebar-expanded) + 16px);
            }
            .card {
                padding: 16px;
            }
            .table th, .table td {
                padding: 10px;
            }
            .modal-content {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen pt-20">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content p-6 flex-1">
        <div class="max-w-5xl mx-auto">
            <?php if ($action == 'add'): ?>
                <h1 class="text-3xl font-semibold text-gray-900 mb-6 animate-in">Add Incident</h1>
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

                <form method="POST" class="card space-y-6 animate-in">
                    <div class="form-section">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="member_id" class="block text-sm font-medium mb-2">Member <span class="text-red-500">*</span></label>
                                <select id="member_id" name="member_id" class="input-field" required>
                                    <option value="" disabled selected>Select Member</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="incident_type" class="block text-sm font-medium mb-2">Incident Type <span class="text-red-500">*</span></label>
                                <select id="incident_type" name="incident_type" class="input-field" required>
                                    <option value="" disabled selected>Select Type</option>
                                    <option value="Death of Mother">Death of Mother</option>
                                    <option value="Death of Father">Death of Father</option>
                                    <option value="Death of Wife's Mother">Death of Wife's Mother</option>
                                    <option value="Death of Wife's Father">Death of Wife's Father</option>
                                    <option value="Death of Husband's Mother">Death of Husband's Mother</option>
                                    <option value="Death of Husband's Father">Death of Husband's Father</option>
                                    <option value="Death of Child">Death of Child</option>
                                    <option value="Death - Other">Death - Other</option>
                                    <option value="Accident">Accident</option>
                                    <option value="Fraud">Fraud</option>
                                    <option value="Payment Issue">Payment Issue</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="incident_datetime" class="block text-sm font-medium mb-2">Incident Date & Time <span class="text-red-500">*</span></label>
                                <input type="datetime-local" id="incident_datetime" name="incident_datetime" class="input-field" required max="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                            <div class="md:col-span-2">
                                <label for="remarks" class="block text-sm font-medium mb-2">Remarks (Optional)</label>
                                <textarea id="remarks" name="remarks" class="input-field" rows="4" placeholder="Additional details (e.g., circumstances, location)..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4">
                        <a href="incidents.php" class="btn-danger"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" name="add" class="btn-primary"><i class="fas fa-plus"></i> Add Incident</button>
                    </div>
                </form>
            <?php else: ?>
                <h1 class="text-3xl font-semibold text-gray-900 mb-6 animate-in">Manage Incidents</h1>
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

                <div class="card animate-in">
                    <div class="table-container">
                        <table class="w-full table">
                            <thead>
                            <tr>
                                <th class="text-left">Incident ID</th>
                                <th class="text-left">Member</th>
                                <th class="text-left">Type</th>
                                <th class="text-left">Date & Time</th>
                                <th class="text-left">Remarks</th>
                                <th class="text-left">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($incidents as $i): ?>
                                <?php $m = $member->getMemberById($i['member_id']); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                    <td><?php echo $m ? htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']) : 'Unknown Member'; ?></td>
                                    <td><?php echo htmlspecialchars($i['incident_type']); ?></td>
                                    <td><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                                    <td><?php echo htmlspecialchars($i['remarks'] ?? 'N/A'); ?></td>
                                    <td class="flex space-x-2">
                                        <button class="btn-icon edit-btn" data-id="<?php echo $i['id']; ?>" title="Edit Incident"><i class="fas fa-edit"></i></button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                                            <button type="submit" name="delete" class="btn-icon text-red-600" title="Delete Incident" onclick="return confirm('Are you sure you want to delete this incident?');"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($incidents)): ?>
                                <tr><td colspan="6" class="py-4 text-center text-gray-500">No incidents recorded yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div id="edit-modal" class="modal">
                    <div class="modal-content">
                        <button class="modal-close">&times;</button>
                        <h2 class="text-xl font-medium text-gray-900 mb-4">Edit Incident</h2>
                        <form method="POST" id="edit-form">
                            <input type="hidden" name="id" id="edit-id">
                            <div class="space-y-4">
                                <div>
                                    <label for="edit-incident-type" class="block text-sm font-medium mb-2">Incident Type</label>
                                    <select id="edit-incident-type" name="incident_type" class="input-field" required>
                                        <option value="Death of Mother">Death of Mother</option>
                                        <option value="Death of Father">Death of Father</option>
                                        <option value="Death of Wife's Mother">Death of Wife's Mother</option>
                                        <option value="Death of Wife's Father">Death of Wife's Father</option>
                                        <option value="Death of Husband's Mother">Death of Husband's Mother</option>
                                        <option value="Death of Husband's Father">Death of Husband's Father</option>
                                        <option value="Death of Child">Death of Child</option>
                                        <option value="Death - Other">Death - Other</option>
                                        <option value="Accident">Accident</option>
                                        <option value="Fraud">Fraud</option>
                                        <option value="Payment Issue">Payment Issue</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="edit-incident-datetime" class="block text-sm font-medium mb-2">Date & Time</label>
                                    <input type="datetime-local" id="edit-incident-datetime" name="incident_datetime" class="input-field" required>
                                </div>
                                <div>
                                    <label for="edit-remarks" class="block text-sm font-medium mb-2">Remarks</label>
                                    <textarea id="edit-remarks" name="remarks" class="input-field" rows="3" placeholder="Additional details..."></textarea>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-4 mt-6">
                                <button type="button" class="btn-danger modal-close"><i class="fas fa-times"></i> Cancel</button>
                                <button type="submit" name="update" class="btn-primary"><i class="fas fa-save"></i> Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <p class="text-center mt-6 animate-in"><a href="dashboard.php" class="text-[var(--primary-orange)] hover:underline">Back to Dashboard</a></p>
        </div>
    </main>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const modal = document.getElementById('edit-modal');
        const editButtons = document.querySelectorAll('.edit-btn');
        const closeButtons = document.querySelectorAll('.modal-close');

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

        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const row = button.closest('tr');
                const type = row.cells[2].textContent;
                const datetime = row.cells[3].textContent.replace(' ', 'T');
                const remarks = row.cells[4].textContent === 'N/A' ? '' : row.cells[4].textContent;

                document.getElementById('edit-id').value = id;
                document.getElementById('edit-incident-type').value = type;
                document.getElementById('edit-incident-datetime').value = datetime;
                document.getElementById('edit-remarks').value = remarks;

                modal.style.display = 'flex';
            });
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
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