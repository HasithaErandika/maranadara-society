<?php
require_once '../../includes/header.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once '../../classes/Member.php';
require_once '../../classes/Incident.php';

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
        $reporter_name = $_POST['reporter_name'];
        $reporter_member_id = $_POST['reporter_member_id'] ?: null;
        $remarks = $_POST['remarks'] ?: null;

        if ($incident->addIncident($incident_id, $member_id, $incident_type, $incident_datetime, $reporter_name, $reporter_member_id, $remarks)) {
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
    <title>Manage Incidents - Maranadhara Samithi</title>
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
            background-color: var(--card-bg);
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
            <li class="sidebar-item"><a href="loans.php?action=add" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Add Loan</span></a></li>
            <li class="sidebar-item"><a href="members.php" class="flex items-center"><i class="fas fa-users"></i><span>Manage Members</span></a></li>
            <li class="sidebar-item"><a href="loans.php" class="flex items-center"><i class="fas fa-hand-holding-usd"></i><span>Manage Loans</span></a></li>
            <li class="sidebar-item active"><a href="incidents.php" class="flex items-center"><i class="fas fa-file-alt"></i><span>Manage Incidents</span></a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 main-content" id="main-content">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-4xl font-extrabold mb-8 text-orange-600">Manage Incidents</h1>

            <!-- Tabs -->
            <div class="flex space-x-4 mb-6">
                <a href="incidents.php?action=view" class="tab-btn <?php echo $action == 'view' ? 'active' : ''; ?>">View Incidents</a>
                <a href="incidents.php?action=add" class="tab-btn <?php echo $action == 'add' ? 'active' : ''; ?>">Add Incident</a>
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

            <?php if ($action == 'add'): ?>
                <form method="POST" class="card space-y-6">
                    <h2 class="text-2xl font-semibold text-orange-600">Record New Incident</h2>
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
                            <label for="incident_type" class="block text-sm font-semibold mb-2">Incident Type <span class="text-red-500">*</span></label>
                            <select id="incident_type" name="incident_type" class="input-field" required aria-label="Select Incident Type">
                                <option value="" disabled selected>Select Type</option>
                                <option value="Accident">Accident</option>
                                <option value="Death">Death</option>
                                <option value="Fraud">Fraud</option>
                                <option value="Payment Issue">Payment Issue</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="incident_datetime" class="block text-sm font-semibold mb-2">Incident Date & Time <span class="text-red-500">*</span></label>
                            <input type="datetime-local" id="incident_datetime" name="incident_datetime" class="input-field" required>
                        </div>
                        <div>
                            <label for="reporter_name" class="block text-sm font-semibold mb-2">Reporter’s Name <span class="text-red-500">*</span></label>
                            <input type="text" id="reporter_name" name="reporter_name" class="input-field" required>
                        </div>
                        <div>
                            <label for="reporter_member_id" class="block text-sm font-semibold mb-2">Reporter’s Membership ID (Optional)</label>
                            <input type="text" id="reporter_member_id" name="reporter_member_id" class="input-field" placeholder="e.g., MS-001">
                        </div>
                        <div class="md:col-span-2">
                            <label for="remarks" class="block text-sm font-semibold mb-2">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" class="input-field" rows="3" placeholder="Additional details..."></textarea>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-4">
                        <button type="submit" name="add" class="btn-admin font-semibold">Record Incident</button>
                        <a href="incidents.php" class="btn-delete font-semibold">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="card">
                    <h2 class="text-2xl font-semibold mb-6 text-orange-600">Incident List</h2>
                    <div class="table-container">
                        <table class="w-full table">
                            <thead>
                            <tr>
                                <th class="text-left">Incident ID</th>
                                <th class="text-left">Member ID</th>
                                <th class="text-left">Type</th>
                                <th class="text-left">Date & Time</th>
                                <th class="text-left">Reporter</th>
                                <th class="text-left">Remarks</th>
                                <th class="text-left">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($incidents as $i): ?>
                                <?php $m = $member->getMemberById($i['member_id']); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                    <td><?php echo htmlspecialchars($m['member_id']); ?></td>
                                    <td>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                                            <select name="incident_type" class="input-field" aria-label="Incident Type">
                                                <option value="Accident" <?php echo $i['incident_type'] == 'Accident' ? 'selected' : ''; ?>>Accident</option>
                                                <option value="Death" <?php echo $i['incident_type'] == 'Death' ? 'selected' : ''; ?>>Death</option>
                                                <option value="Fraud" <?php echo $i['incident_type'] == 'Fraud' ? 'selected' : ''; ?>>Fraud</option>
                                                <option value="Payment Issue" <?php echo $i['incident_type'] == 'Payment Issue' ? 'selected' : ''; ?>>Payment Issue</option>
                                                <option value="Other" <?php echo $i['incident_type'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                    </td>
                                    <td>
                                        <input type="datetime-local" name="incident_datetime" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $i['incident_datetime'])); ?>" class="input-field" aria-label="Incident DateTime">
                                    </td>
                                    <td><?php echo htmlspecialchars($i['reporter_name']); ?></td>
                                    <td>
                                        <textarea name="remarks" class="input-field" rows="2" aria-label="Remarks"><?php echo htmlspecialchars($i['remarks'] ?? ''); ?></textarea>
                                    </td>
                                    <td class="flex space-x-2">
                                        <button type="submit" name="update" class="btn-admin" aria-label="Save Changes"><i class="fas fa-save"></i></button>
                                        <button type="submit" name="delete" class="btn-delete" onclick="return confirm('Are you sure you want to delete this incident?');" aria-label="Delete Incident"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($incidents)): ?>
                                <tr><td colspan="7" class="py-4 text-center text-gray-500 dark:text-gray-400">No incidents recorded.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
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