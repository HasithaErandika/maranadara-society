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
            $success = "Incident '$incident_id' recorded successfully!";
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
        .btn-delete {
            background-color: #dc2626;
        }
        .btn-delete:hover {
            background-color: #b91c1c;
        }
        .table-hover tbody tr:hover {
            background-color: #fef5e7;
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
    <div class="card p-6 rounded-xl">
        <h1 class="text-2xl font-bold mb-6 text-orange-600">Manage Incidents</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-6"><?php echo $success; ?> Redirecting...</div>
        <?php endif; ?>

        <?php if ($action == 'add'): ?>
            <form method="POST" class="space-y-6">
                <h2 class="text-xl font-semibold text-orange-600">Record New Incident</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="member_id" class="block font-medium mb-1">Member</label>
                        <select id="member_id" name="member_id" class="input-field w-full px-4 py-2 rounded-lg" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="incident_type" class="block font-medium mb-1">Incident Type</label>
                        <select id="incident_type" name="incident_type" class="input-field w-full px-4 py-2 rounded-lg" required>
                            <option value="Accident">Accident</option>
                            <option value="Death">Death</option>
                            <option value="Fraud">Fraud</option>
                            <option value="Payment Issue">Payment Issue</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="incident_datetime" class="block font-medium mb-1">Incident Date & Time</label>
                        <input type="datetime-local" id="incident_datetime" name="incident_datetime" class="input-field w-full px-4 py-2 rounded-lg" required>
                    </div>
                    <div>
                        <label for="reporter_name" class="block font-medium mb-1">Reporter’s Name</label>
                        <input type="text" id="reporter_name" name="reporter_name" class="input-field w-full px-4 py-2 rounded-lg" required>
                    </div>
                    <div>
                        <label for="reporter_member_id" class="block font-medium mb-1">Reporter’s Membership ID (Optional)</label>
                        <input type="text" id="reporter_member_id" name="reporter_member_id" class="input-field w-full px-4 py-2 rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label for="remarks" class="block font-medium mb-1">Remarks (Optional)</label>
                        <textarea id="remarks" name="remarks" class="input-field w-full px-4 py-2 rounded-lg" rows="3"></textarea>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" name="add" class="text-white px-6 py-3 rounded-lg font-semibold btn-admin">Record Incident</button>
                </div>
            </form>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full table-hover">
                    <thead>
                    <tr class="border-b dark:border-gray-600">
                        <th class="py-2 px-4 text-left">Incident ID</th>
                        <th class="py-2 px-4 text-left">Member ID</th>
                        <th class="py-2 px-4 text-left">Type</th>
                        <th class="py-2 px-4 text-left">Date & Time</th>
                        <th class="py-2 px-4 text-left">Reporter</th>
                        <th class="py-2 px-4 text-left">Remarks</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($incidents as $i): ?>
                        <?php $m = $member->getMemberById($i['member_id']); ?>
                        <tr class="border-b dark:border-gray-600">
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['incident_id']); ?></td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($m['member_id']); ?></td>
                            <td class="py-2 px-4">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                                    <select name="incident_type" class="input-field w-full px-2 py-1 rounded-lg">
                                        <option value="Accident" <?php echo $i['incident_type'] == 'Accident' ? 'selected' : ''; ?>>Accident</option>
                                        <option value="Death" <?php echo $i['incident_type'] == 'Death' ? 'selected' : ''; ?>>Death</option>
                                        <option value="Fraud" <?php echo $i['incident_type'] == 'Fraud' ? 'selected' : ''; ?>>Fraud</option>
                                        <option value="Payment Issue" <?php echo $i['incident_type'] == 'Payment Issue' ? 'selected' : ''; ?>>Payment Issue</option>
                                        <option value="Other" <?php echo $i['incident_type'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                            </td>
                            <td class="py-2 px-4">
                                <input type="datetime-local" name="incident_datetime" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $i['incident_datetime'])); ?>" class="input-field w-full px-2 py-1 rounded-lg">
                            </td>
                            <td class="py-2 px-4"><?php echo htmlspecialchars($i['reporter_name']); ?></td>
                            <td class="py-2 px-4">
                                <textarea name="remarks" class="input-field w-full px-2 py-1 rounded-lg" rows="2"><?php echo htmlspecialchars($i['remarks'] ?? ''); ?></textarea>
                            </td>
                            <td class="py-2 px-4 flex space-x-2">
                                <button type="submit" name="update" class="text-white px-2 py-1 rounded-lg btn-admin"><i class="fas fa-save"></i></button>
                                <button type="submit" name="delete" class="text-white px-2 py-1 rounded-lg btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($incidents)): ?>
                        <tr><td colspan="7" class="py-2 px-4 text-center text-gray-500 dark:text-gray-400">No incidents recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="text-center mt-4"><a href="dashboard.php" class="text-orange-600 hover:underline">Back to Dashboard</a></p>
    </div>
</div>
</body>
</html>