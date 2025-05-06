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

try {
    $member = new Member();
    $incident = new Incident();
} catch (Exception $e) {
    error_log("Initialization failed: " . $e->getMessage());
    $error = "System error: Unable to connect to database. Please try again later.";
}

$error = $success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$members = $member->getAllMembers();
$incidents = $incident->getAllIncidents();
$total_incidents = count($incidents);
$incidents_paginated = array_slice($incidents, $offset, $items_per_page);
$total_pages = ceil($total_incidents / $items_per_page);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    if (isset($_POST['add'])) {
        $incident_id = $incident->generateIncidentId();
        $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT); // Expecting members.id (integer)
        $incident_type = trim($_POST['incident_type'] ?? '');
        $incident_datetime = trim($_POST['incident_datetime'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '') ?: null;

        if (!$member_id || $member_id <= 0 || !$incident_type || !$incident_datetime) {
            $error = "All required fields must be filled.";
        } elseif (strlen($incident_type) > 50) {
            $error = "Incident type must be 50 characters or less.";
        } elseif (strtotime($incident_datetime) > time()) {
            $error = "Incident date cannot be in the future.";
        } else {
            try {
                // Verify member_id exists in members table (using members.id)
                $member_data = $member->getMemberById($member_id);
                if (!$member_data) {
                    $error = "Invalid member selected.";
                } else {
                    if ($incident->addIncident($incident_id, $member_id, $incident_type, $incident_datetime, $remarks)) {
                        $success = "Incident '$incident_id' recorded successfully!";
                    } else {
                        $error = "Error recording incident. Please try again.";
                    }
                }
            } catch (Exception $e) {
                $error = "Database error: " . htmlspecialchars($e->getMessage());
                if (strpos($e->getMessage(), 'foreign key constraint')) {
                    $error = "Invalid member selected.";
                }
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $conn->prepare("DELETE FROM incidents WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success = "Incident deleted successfully!";
                    $incidents = $incident->getAllIncidents();
                    $total_incidents = count($incidents);
                    $incidents_paginated = array_slice($incidents, $offset, $items_per_page);
                    $total_pages = ceil($total_incidents / $items_per_page);
                } else {
                    $error = "Incident not found or already deleted.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = "Invalid incident ID.";
        }
    } elseif (isset($_POST['update'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $incident_type = trim($_POST['incident_type'] ?? '');
        $incident_datetime = trim($_POST['incident_datetime'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '') ?: null;

        if (!$id || !$incident_type || !$incident_datetime) {
            $error = "All required fields must be filled.";
        } elseif (strlen($incident_type) > 50) {
            $error = "Incident type must be 50 characters or less.";
        } elseif (strtotime($incident_datetime) > time()) {
            $error = "Incident date cannot be in the future.";
        } else {
            try {
                $stmt = $conn->prepare("UPDATE incidents SET incident_type = ?, incident_datetime = ?, remarks = ? WHERE id = ?");
                $stmt->bind_param("sssi", $incident_type, $incident_datetime, $remarks, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success = "Incident updated successfully!";
                    $incidents = $incident->getAllIncidents();
                    $total_incidents = count($incidents);
                    $incidents_paginated = array_slice($incidents, $offset, $items_per_page);
                    $total_pages = ceil($total_incidents / $items_per_page);
                } else {
                    $error = "Incident not found or no changes made.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . htmlspecialchars($e->getMessage());
            }
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
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #F97316;
            --primary-dark: #EA580C;
            --primary-light: #FDBA74;
            --secondary: #4B5563;
            --background: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --error: #EF4444;
            --success: #22C55E;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --border: #E5E7EB;
            --sidebar-width: 64px;
            --sidebar-expanded: 256px;
            --header-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .main-content {
            margin-left: calc(var(--sidebar-width) + 2rem);
            padding: calc(var(--header-height) + 2rem) 2rem 2rem;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--header-height));
        }

        .sidebar.expanded ~ .main-content {
            margin-left: calc(var(--sidebar-expanded) + 2rem);
        }

        /* Header Section */
        .header-section {
            margin-bottom: 2rem;
            animation: fadeIn 0.6s ease-out;
        }

        .header-section h1 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .header-section p {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            max-width: 600px;
        }

        /* Controls Section */
        .controls-section {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            animation: fadeIn 0.6s ease-out;
        }

        .search-container {
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 1.2rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .search-input::placeholder {
            color: var(--text-secondary);
        }

        .date-filter {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .date-input {
            padding: 0.8rem 1.2rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: #FFFFFF;
            transition: all 0.3s ease;
            max-width: 180px;
        }

        .date-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem;
            animation: fadeIn 0.6s ease-out;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            background: var(--card-bg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th, .table td {
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            background: var(--card-bg);
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .table tbody tr:hover {
            background: rgba(249, 115, 22, 0.05);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .btn-danger {
            background: var(--error);
            color: #FFFFFF;
        }

        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-secondary {
            background: var(--secondary);
            color: #FFFFFF;
        }

        .btn-secondary:hover {
            background: #374151;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(75, 85, 99, 0.2);
        }

        .btn-download {
            background: #10B981;
            color: #FFFFFF;
        }

        .btn-download:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-icon {
            background: none;
            color: var(--text-secondary);
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 1.1rem;
            box-shadow: none;
        }

        .btn-icon:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.4s ease, height 0.4s ease;
        }

        .btn:active::after {
            width: 100px;
            height: 100px;
        }

        .input-field {
            width: 100%;
            padding: 0.9rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: #FFFFFF;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: #FFF7ED;
        }

        .input-field.error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .alert {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            animation: fadeIn 0.6s ease-out;
        }

        .alert-success {
            background: #DCFCE7;
            color: var(--success);
            border-left: 5px solid var(--success);
        }

        .alert-error {
            background: #FEE2E2;
            color: var(--error);
            border-left: 5px solid var(--error);
        }

        .pagination-btn {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .pagination-btn.disabled {
            background: #F3F4F6;
            color: #9CA3AF;
            cursor: not-allowed;
            border-color: #D1D5DB;
        }

        /* Popup Styles */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            animation: fadeIn 0.3s ease-out;
        }

        .popup-overlay.show {
            display: block;
        }

        .popup {
            display: none;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            animation: popupIn 0.3s ease-out forwards;
            z-index: 2001;
        }

        .popup.show {
            display: block;
        }

        .popup-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .popup-success .popup-icon {
            color: var(--success);
        }

        .popup-error .popup-icon {
            color: var(--error);
        }

        .popup-cancel .popup-icon {
            color: var(--secondary);
        }

        .popup-delete .popup-icon {
            color: var(--error);
        }

        .popup h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .popup p {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .popup span {
            font-weight: 600;
            color: var(--primary);
        }

        .popup-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        /* Modal Styles (for Edit) */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.4s ease-out;
       Topics and replies
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            width: 100%;
            max-width: 650px;
            box-shadow: var(--shadow);
            animation: slideIn 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .modal-close:hover {
            color: var(--primary);
            transform: scale(1.1);
        }

        .error-text {
            display: none;
            color: var(--error);
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        .error-text.show {
            display: block;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
            display: block;
        }

        .form-group .required {
            color: var(--error);
            font-weight: 600;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes popupIn {
            from { transform: translate(-50%, -50%) scale(0.8); opacity: 0; }
            to { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 1rem;
                padding: calc(var(--header-height) + 1.5rem) 1.5rem 1.5rem;
            }

            .sidebar.expanded ~ .main-content {
                margin-left: calc(var(--sidebar-expanded) + 1rem);
            }

            .header-section h1 {
                font-size: 1.75rem;
            }

            .controls-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                max-width: 100%;
            }

            .date-input {
                max-width: 100%;
            }

            .table th, .table td {
                padding: 0.75rem;
                font-size: 0.85rem;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }

            .popup {
                width: 90%;
                padding: 1.5rem;
            }

            .btn {
                padding: 0.65rem 1.2rem;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .header-section h1 {
                font-size: 1.5rem;
            }

            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .btn {
                padding: 0.55rem 1rem;
                font-size: 0.8rem;
            }

            .popup-icon {
                font-size: 2.5rem;
            }

            .popup h2 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main-content">
        <div class="container">
            <?php if ($action == 'add'): ?>
                <div class="header-section">
                    <h1>Add Incident</h1>
                    <p>Record a new incident for a member to track important events.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error animate-in">
                        <i class="ri-error-warning-fill mr-2 text-lg"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="add-form" class="card animate-in">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="member_id" class="block">Member <span class="required">*</span></label>
                                <select id="member_id" name="member_id" class="input-field" required>
                                    <option value="" disabled selected>Select Member</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo htmlspecialchars($m['id']); ?>">
                                            <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="error-text" id="member_id-error">Member is required.</span>
                            </div>
                            <div class="form-group">
                                <label for="incident_type" class="block">Incident Type <span class="required">*</span></label>
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
                                <span class="error-text" id="incident_type-error">Incident type is required.</span>
                            </div>
                            <div class="form-group">
                                <label for="incident_datetime" class="block">Incident Date & Time <span class="required">*</span></label>
                                <input type="datetime-local" id="incident_datetime" name="incident_datetime" class="input-field" required max="<?php echo date('Y-m-d\TH:i'); ?>">
                                <span class="error-text" id="incident_datetime-error">Date and time are required.</span>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label for="remarks" class="block">Remarks (Optional)</label>
                                <textarea id="remarks" name="remarks" class="input-field" rows="4" placeholder="Additional details (e.g., circumstances, location)..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4 mt-8">
                        <button type="button" id="cancel-button" class="btn btn-secondary"><i class="ri-close-line mr-2"></i> Cancel</button>
                        <button type="submit" name="add" class="btn btn-primary"><i class="ri-add-line mr-2"></i> Add Incident</button>
                    </div>
                </form>

                <!-- Popups -->
                <div class="popup-overlay" id="popup-overlay"></div>
                <?php if ($success): ?>
                    <div class="popup popup-success show" id="success-popup">
                        <i class="ri-checkbox-circle-fill popup-icon"></i>
                        <h2>Success</h2>
                        <p><?php echo htmlspecialchars($success); ?> Redirecting in <span id="success-countdown">3</span> seconds...</p>
                    </div>
                <?php endif; ?>
                <div class="popup popup-cancel" id="cancel-popup">
                    <i class="ri-close-circle-fill popup-icon"></i>
                    <h2>Cancel Entry</h2>
                    <p>Are you sure you want to cancel? You will be redirected in <span id="cancel-countdown">3</span> seconds.</p>
                </div>
            <?php else: ?>
                <div class="header-section">
                    <h1>Manage Incidents</h1>
                    <p>View, edit, or delete recorded incidents, and export data as needed.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error animate-in">
                        <i class="ri-error-warning-fill mr-2 text-lg"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="controls-section">
                    <div class="search-container">
                        <input type="text" id="search-input" class="search-input" placeholder="Search incidents...">
                    </div>
                    <div class="date-filter">
                        <input type="date" id="date-from" class="date-input" placeholder="From">
                        <input type="date" id="date-to" class="date-input" placeholder="To">
                    </div>
                    <button id="download-csv" class="btn btn-download"><i class="ri-download-line mr-2"></i> Download CSV</button>
                </div>

                <div class="card animate-in">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Incident ID</th>
                                <th>Member</th>
                                <th>Type</th>
                                <th>Date & Time</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="incident-table-body">
                            <?php foreach ($incidents_paginated as $i): ?>
                                <?php $m = $member->getMemberById($i['member_id']); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                    <td><?php echo $m ? htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']) : 'Unknown Member'; ?></td>
                                    <td><?php echo htmlspecialchars($i['incident_type']); ?></td>
                                    <td><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                                    <td><?php echo htmlspecialchars($i['remarks'] ?? 'N/A'); ?></td>
                                    <td class="flex space-x-2">
                                        <button class="btn-icon edit-btn" data-id="<?php echo $i['id']; ?>" data-type="<?php echo htmlspecialchars($i['incident_type']); ?>" data-datetime="<?php echo htmlspecialchars($i['incident_datetime']); ?>" data-remarks="<?php echo htmlspecialchars($i['remarks'] ?? ''); ?>" title="Edit Incident"><i class="ri-edit-line"></i></button>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $i['id']; ?>" title="Delete Incident"><i class="ri-delete-bin-line text-[var(--error)]"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($incidents_paginated)): ?>
                                <tr><td colspan="6" class="text-center text-[var(--text-secondary)]">No incidents recorded yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="mt-8 flex justify-between items-center">
                            <p class="text-sm text-[var(--text-secondary)]">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_incidents); ?> of <?php echo $total_incidents; ?>
                            </p>
                            <div class="flex space-x-3">
                                <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" <?php echo $page <= 1 ? 'onclick="return false;"' : ''; ?>>Previous</a>
                                <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" <?php echo $page >= $total_pages ? 'onclick="return false;"' : ''; ?>>Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Popups -->
                <div class="popup-overlay" id="popup-overlay"></div>
                <?php if ($success): ?>
                    <div class="popup popup-success show" id="success-popup">
                        <i class="ri-checkbox-circle-fill popup-icon"></i>
                        <h2>Success</h2>
                        <p><?php echo htmlspecialchars($success); ?> Redirecting in <span id="success-countdown">3</span> seconds...</p>
                    </div>
                <?php endif; ?>
                <div class="popup popup-delete" id="delete-popup">
                    <i class="ri-error-warning-fill popup-icon"></i>
                    <h2>Confirm Deletion</h2>
                    <p>Are you sure you want to delete this incident? This action cannot be undone.</p>
                    <form method="POST" id="delete-form">
                        <input type="hidden" name="id" id="delete-id">
                        <div class="popup-buttons">
                            <button type="button" class="btn btn-secondary" id="delete-cancel"><i class="ri-close-line mr-2"></i> Cancel</button>
                            <button type="submit" name="delete" class="btn btn-danger"><i class="ri-delete-bin-line mr-2"></i> Delete</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Modal -->
                <div id="edit-modal" class="modal">
                    <div class="modal-content">
                        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
                        <h2 class="text-2xl font-semibold text-[var(--primary)] mb-6">Edit Incident</h2>
                        <form method="POST" id="edit-form">
                            <input type="hidden" name="id" id="edit-id">
                            <div class="space-y-6">
                                <div class="form-group">
                                    <label for="edit-incident-type" class="block">Incident Type <span class="required">*</span></label>
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
                                    <span class="error-text" id="edit-incident-type-error">Incident type is required.</span>
                                </div>
                                <div class="form-group">
                                    <label for="edit-incident-datetime" class="block">Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local" id="edit-incident-datetime" name="incident_datetime" class="input-field" required max="<?php echo date('Y-m-d\TH:i'); ?>">
                                    <span class="error-text" id="edit-incident-datetime-error">Date and time are required.</span>
                                </div>
                                <div class="form-group">
                                    <label for="edit-remarks" class="block">Remarks (Optional)</label>
                                    <textarea id="edit-remarks" name="remarks" class="input-field" rows="4" placeholder="Additional details..."></textarea>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-4 mt-8">
                                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line mr-2"></i> Cancel</button>
                                <button type="submit" name="update" class="btn btn-primary"><i class="ri-save-line mr-2"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const editModal = document.getElementById('edit-modal');
    const editButtons = document.querySelectorAll('.edit-btn');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const closeButtons = document.querySelectorAll('.modal-close');
    const addForm = document.getElementById('add-form');
    const editForm = document.getElementById('edit-form');
    const searchInput = document.getElementById('search-input');
    const dateFrom = document.getElementById('date-from');
    const dateTo = document.getElementById('date-to');
    const tableBody = document.getElementById('incident-table-body');
    const downloadCsvButton = document.getElementById('download-csv');
    const cancelButton = document.getElementById('cancel-button');
    const popupOverlay = document.getElementById('popup-overlay');
    const cancelPopup = document.getElementById('cancel-popup');
    const successPopup = document.getElementById('success-popup');
    const deletePopup = document.getElementById('delete-popup');
    const deleteCancel = document.getElementById('delete-cancel');

    // Sidebar toggle
    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebarawley.classList.toggle('expanded');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && sidebar.classList.contains('expanded') &&
                !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('expanded');
            }
        });
    }

    // Edit modal
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            const type = button.getAttribute('data-type');
            let datetime = button.getAttribute('data-datetime');
            const remarks = button.getAttribute('data-remarks');

            try {
                const date = new Date(datetime);
                if (!isNaN(date)) {
                    datetime = date.toISOString().slice(0, 16);
                } else {
                    datetime = '';
                }
            } catch (e) {
                datetime = '';
            }

            document.getElementById('edit-id').value = id;
            document.getElementById('edit-incident-type').value = type;
            document.getElementById('edit-incident-datetime').value = datetime;
            document.getElementById('edit-remarks').value = remarks === 'N/A' ? '' : remarks;

            editModal.style.display = 'flex';
        });
    });

    // Delete popup
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            document.getElementById('delete-id').value = id;
            showPopup(deletePopup);
        });
    });

    // Cancel delete
    if (deleteCancel) {
        deleteCancel.addEventListener('click', () => {
            hidePopup(deletePopup);
        });
    }

    // Close edit modal
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            editModal.style.display = 'none';
            clearErrors();
        });
    });

    // Click outside to close edit modal
    if (editModal) {
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) {
                editModal.style.display = 'none';
                clearErrors();
            }
        });
    }

    // Form validation for add
    if (addForm) {
        addForm.addEventListener('submit', (e) => {
            let hasError = false;
            clearErrors();

            const memberId = document.getElementById('member_id');
            const incidentType = document.getElementById('incident_type');
            const incidentDatetime = document.getElementById('incident_datetime');

            if (!memberId.value) {
                showError('member_id-error', memberId);
                hasError = true;
            }
            if (!incidentType.value) {
                showError('incident_type-error', incidentType);
                hasError = true;
            }
            if (!incidentDatetime.value) {
                showError('incident_datetime-error', incidentDatetime);
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });
    }

    // Form validation for edit
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            let hasError = false;
            clearErrors();

            const incidentType = document.getElementById('edit-incident-type');
            const incidentDatetime = document.getElementById('edit-incident-datetime');

            if (!incidentType.value) {
                showError('edit-incident-type-error', incidentType);
                hasError = true;
            }
            if (!incidentDatetime.value) {
                showError('edit-incident-datetime-error', incidentDatetime);
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });
    }

    // Search and Date Filter
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const fromDate = dateFrom.value ? new Date(dateFrom.value) : null;
        const toDate = dateTo.value ? new Date(dateTo.value + 'T23:59:59') : null;
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            if (row.querySelector('td[colspan="6"]')) {
                return;
            }

            const cells = row.querySelectorAll('td');
            let matchesSearch = false;
            let matchesDate = true;

            // Search filter
            for (let i = 0; i < cells.length - 1; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    matchesSearch = true;
                    break;
                }
            }

            // Date filter (only Date & Time column)
            if (fromDate || toDate) {
                const dateCell = cells[3].textContent; // Date & Time column
                const rowDate = new Date(dateCell);
                matchesDate = (!fromDate || rowDate >= fromDate) && (!toDate || rowDate <= toDate);
            }

            row.style.display = (matchesSearch || searchTerm === '') && matchesDate ? '' : 'none';
        });

        // Handle empty table
        const noResultsRow = tableBody.querySelector('tr td[colspan="6"]');
        if (noResultsRow) {
            noResultsRow.style.display = (searchTerm === '' && !fromDate && !toDate) ? '' : 'none';
            if ((searchTerm !== '' || fromDate || toDate) && Array.from(rows).every(row => row.style.display === 'none')) {
                if (!tableBody.querySelector('.no-results')) {
                    const noResults = document.createElement('tr');
                    noResults.className = 'no-results';
                    noResults.innerHTML = '<td colspan="6" class="text-center text-[var(--text-secondary)]">No incidents match your criteria.</td>';
                    tableBody.appendChild(noResults);
                }
            } else {
                const existingNoResults = tableBody.querySelector('.no-results');
                if (existingNoResults) {
                    existingNoResults.remove();
                }
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', filterTable);
        dateTo.addEventListener('change', filterTable);
    }

    // CSV Download
    if (downloadCsvButton) {
        downloadCsvButton.addEventListener('click', () => {
            const rows = tableBody.querySelectorAll('tr');
            let csvContent = 'Incident ID,Member,Type,Date & Time,Remarks\n';

            rows.forEach(row => {
                if (row.style.display !== 'none' && !row.querySelector('td[colspan="6"]')) {
                    const cells = row.querySelectorAll('td');
                    const rowData = [
                        cells[0].textContent,
                        cells[1].textContent,
                        cells[2].textContent,
                        cells[3].textContent,
                        cells[4].textContent === 'N/A' ? '' : cells[4].textContent
                    ].map(cell => `"${cell.replace(/"/g, '""')}"`).join(',');
                    csvContent += rowData + '\n';
                }
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'incidents.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    }

    // Cancel Popup
    if (cancelButton) {
        cancelButton.addEventListener('click', (e) => {
            e.preventDefault();
            showPopup(cancelPopup);
            startCountdown('cancel-countdown', 'incidents.php');
        });
    }

    // Success Popup
    if (successPopup) {
        showPopup(successPopup);
        startCountdown('success-countdown', 'incidents.php');
    }

    function showPopup(popup) {
        popupOverlay.classList.add('show');
        popup.classList.add('show');
    }

    function hidePopup(popup) {
        popupOverlay.classList.remove('show');
        popup.classList.remove('show');
    }

    function startCountdown(elementId, redirectUrl) {
        let timeLeft = 3;
        const countdown = document.getElementById(elementId);
        if (countdown) {
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

    function showError(id, input) {
        const errorElement = document.getElementById(id);
        if (errorElement) {
            errorElement.classList.add('show');
            if (input) input.classList.add('error');
        }
    }

    function clearErrors() {
        document.querySelectorAll('.error-text').forEach(error => error.classList.remove('show'));
        document.querySelectorAll('.input-field').forEach(input => input.classList.remove('error'));
    }
});
</script>
</body>
</html>