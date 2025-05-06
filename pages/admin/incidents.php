<?php
define('APP_START', true);
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../classes/Incident.php';
require_once '../../classes/Member.php';
require_once '../../classes/Database.php';

$incident = new Incident();
$member = new Member();

$error = $success = '';

$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_type = isset($_GET['search_type']) ? trim($_GET['search_type']) : 'all';
$incidents = $incident->getAllIncidents();
$total_incidents = count($incidents);

if ($search) {
    $incidents = array_filter($incidents, function ($i) use ($search, $search_type) {
        $search = strtolower($search);
        if ($search_type === 'all') {
            return stripos(strtolower($i['incident_id']), $search) !== false ||
                stripos(strtolower($i['incident_type']), $search) !== false ||
                stripos(strtolower($i['member_name'] ?? ''), $search) !== false;
        } elseif ($search_type === 'incident_id') {
            return stripos(strtolower($i['incident_id']), $search) !== false;
        } elseif ($search_type === 'incident_type') {
            return stripos(strtolower($i['incident_type']), $search) !== false;
        } elseif ($search_type === 'member_name') {
            return stripos(strtolower($i['member_name'] ?? ''), $search) !== false;
        }
        return false;
    });
}

$incidents_paginated = array_slice($incidents, $offset, $items_per_page);
$total_pages = ceil($total_incidents / $items_per_page);

// Fetch all members for the add incident modal
$all_members = $member->getAllMembers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                if ($incident->deleteIncident($id)) {
                    $success = "Incident deleted successfully.";
                } else {
                    $error = "Failed to delete incident. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Error deleting incident: " . $e->getMessage();
            }
        } else {
            $error = "Invalid incident ID.";
        }
    } elseif (isset($_POST['update'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $incident_type = trim($_POST['incident_type'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $status = trim($_POST['status'] ?? '');

        if (empty($incident_type)) {
            $error = "Incident type is required.";
        } elseif (!in_array($status, ['Open', 'In Progress', 'Resolved', 'Closed'])) {
            $error = "Invalid status.";
        } else {
            try {
                $data = [
                    'incident_type' => $incident_type,
                    'remarks' => $remarks,
                    'status' => $status
                ];
                
                if ($incident->updateIncident($id, $data)) {
                    $success = "Incident updated successfully.";
                } else {
                    $error = "Failed to update incident. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Error updating incident: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add'])) {
        $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
        $incident_type = trim($_POST['incident_type'] ?? '');
        $incident_datetime = trim($_POST['incident_datetime'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$member_id) {
            $error = "Please select a valid member.";
        } elseif (empty($incident_type)) {
            $error = "Incident type is required.";
        } elseif (empty($incident_datetime)) {
            $error = "Incident date and time are required.";
        } else {
            try {
                $incident_id = $incident->generateIncidentId();
                if ($incident->addIncident($incident_id, $member_id, $incident_type, $incident_datetime, $remarks)) {
                    $success = "Incident reported successfully.";
                } else {
                    $error = "Failed to report incident. Please try again.";
                }
            } catch (Exception $e) {
                $error = "Error reporting incident: " . $e->getMessage();
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
    <title>Manage Incidents - Maranadhara Samithi</title>
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fc;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 80px;
        }

        .form-section, .card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-top: 50px;
        }

        .form-section h2, .card h2 {
            font-size: 1.5rem;
            color: #f97316;
            margin-bottom: 16px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 8px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: #4a5568;
        }

        .required-mark, .required {
            color: #e53e3e;
        }

        .input-field {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: #fff;
        }

        .input-field:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .input-field:invalid:not(:placeholder-shown) {
            border-color: #e53e3e;
        }

        .input-field.valid {
            border-color: #48bb78;
        }

        .error-text {
            display: none;
            color: #e53e3e;
            font-size: 0.75rem;
            margin-top: 4px;
        }

        .error-text.show {
            display: block;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background-color: #f97316;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #ed8936;
        }

        .btn-secondary {
            background-color: #a0aec0;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #718096;
        }

        .btn-danger {
            color: #e53e3e;
            background: none;
            border: 1px solid transparent;
        }

        .btn-danger:hover {
            background-color: #fde2e2;
            border-color: #e53e3e;
            color: #c53030;
        }

        .btn-icon {
            background: none;
            color: #718096;
            padding: 8px;
            position: relative;
        }

        .btn-icon:hover {
            color: #f97316;
        }

        .btn-icon:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        .tooltip {
            visibility: hidden;
            background: #2d3748;
            color: #fff;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 90%;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .modal-overlay.show {
            display: block;
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: none;
            border: none;
            color: #718096;
            font-size: 1.25rem;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #f97316;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            background: #fff;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
        }

        .table th {
            background: #f7fafc;
            font-weight: 600;
            font-size: 0.875rem;
            color: #2d3748;
        }

        .table th.sortable:hover {
            background: #edf2f7;
            color: #f97316;
            cursor: pointer;
        }

        .table tbody tr:hover {
            background: #f7fafc;
        }

        .search-container {
            position: relative;
            max-width: 500px;
            margin-bottom: 24px;
        }

        .search-container .ri-search-line {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
            font-size: 1.25rem;
        }

        .search-container .ri-close-line {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
            cursor: pointer;
            font-size: 1.25rem;
            transition: color 0.2s;
        }

        .search-container .ri-close-line:hover {
            color: #f97316;
        }

        .search-container .input-field {
            padding: 12px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .search-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            display: none;
            margin-top: 4px;
        }

        .search-options.show {
            display: block;
        }

        .search-option {
            padding: 12px;
            font-size: 0.875rem;
            color: #2d3748;
            cursor: pointer;
            transition: background 0.2s;
        }

        .search-option:hover {
            background: #f7fafc;
        }

        .search-option.active {
            background: #f97316;
            color: #fff;
        }

        .search-loading {
            display: none;
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
        }

        .search-loading.show {
            display: block;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #f0fff4;
            color: #48bb78;
        }

        .alert-error {
            background: #fff5f5;
            color: #e53e3e;
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #2d3748;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #f97316;
            color: #fff;
            border-color: #f97316;
        }

        .pagination-btn.disabled {
            background: #edf2f7;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .flex {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .main {
            flex: 1;
            padding: 24px;
            margin-left: 240px;
        }

        .no-results {
            text-align: center;
            padding: 24px;
            color: #718096;
            font-size: 1rem;
        }

        .no-results i {
            font-size: 2rem;
            color: #f97316;
            margin-bottom: 12px;
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 16px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 16px;
            }

            .search-container {
                max-width: 100%;
            }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .animate-slide-in {
            animation: slideIn 0.5s ease-out;
        }

        /* Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #f97316;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../../includes/sidepanel.php'; ?>

    <main class="main">
        <div class="container">
            <div class="flex justify-between items-center mb-6 animate-slide-in">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 700;">Manage Incidents</h1>
                    <p style="font-size: 0.9rem; color: #718096;">Track and manage incident reports efficiently.</p>
                </div>
                <button class="btn btn-primary" id="add-incident-btn">
                    <i class="ri-add-line"></i> Report New Incident
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error animate-slide-in">
                    <i class="ri-error-warning-fill"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success animate-slide-in">
                    <i class="ri-checkbox-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <form method="GET" class="search-container animate-slide-in" id="search-form" role="search">
                <i class="ri-search-line"></i>
                <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search incidents..." class="input-field" aria-label="Search incidents">
                <i class="ri-close-line" id="clear-search" role="button" aria-label="Clear search"></i>
                <div class="search-loading" id="search-loading">
                    <div class="spinner"></div>
                </div>
                <div class="search-options" id="search-options">
                    <div class="search-option <?php echo $search_type === 'all' ? 'active' : ''; ?>" data-type="all">All Fields</div>
                    <div class="search-option <?php echo $search_type === 'incident_id' ? 'active' : ''; ?>" data-type="incident_id">Incident ID</div>
                    <div class="search-option <?php echo $search_type === 'incident_type' ? 'active' : ''; ?>" data-type="incident_type">Type</div>
                    <div class="search-option <?php echo $search_type === 'member_name' ? 'active' : ''; ?>" data-type="member_name">Member</div>
                </div>
                <input type="hidden" name="search_type" id="search-type" value="<?php echo htmlspecialchars($search_type); ?>">
            </form>

            <!-- Incidents List -->
            <div class="card animate-slide-in">
                <div class="table-container">
                    <table class="table">
                        <thead>
                        <tr>
                            <th class="sortable">ID</th>
                            <th class="sortable">Type</th>
                            <th class="sortable">Member</th>
                            <th class="sortable">Date & Time</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidents_paginated as $i): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($i['incident_id']); ?></td>
                                <td><?php echo htmlspecialchars($i['incident_type']); ?></td>
                                <td>
                                    <a href="members.php?member_id=<?php echo htmlspecialchars($i['member_id']); ?>" style="color: #f97316;">
                                        <?php echo htmlspecialchars($i['member_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($i['incident_datetime']); ?></td>
                                <td class="flex">
                                    <button class="btn-icon edit-btn" data-incident='<?php echo htmlspecialchars(json_encode($i), ENT_QUOTES); ?>' title="Edit Incident" aria-label="Edit Incident">
                                        <i class="ri-edit-line"></i>
                                        <span class="tooltip">Edit</span>
                                    </button>
                                    <button class="btn-icon delete-btn" data-id="<?php echo $i['id']; ?>" title="Delete Incident" aria-label="Delete Incident">
                                        <i class="ri-delete-bin-line" style="color: #e53e3e;"></i>
                                        <span class="tooltip">Delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($incidents_paginated)): ?>
                            <tr><td colspan="6" class="no-results">
                                <i class="ri-search-line"></i><br>
                                No incidents found. Try adjusting your search.
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <p style="font-size: 0.9rem; color: #718096;">
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_incidents); ?> of <?php echo $total_incidents; ?>
                        </p>
                        <div class="flex" style="gap: 10px;">
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&search_type=<?php echo urlencode($search_type); ?>"
                               class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                                <?php echo $page <= 1 ? 'onclick="return false;"' : ''; ?>>Previous</a>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&search_type=<?php echo urlencode($search_type); ?>"
                               class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                                <?php echo $page >= $total_pages ? 'onclick="return false;"' : ''; ?>>Next</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal Overlay -->
<div class="modal-overlay"></div>

<!-- Add Incident Modal -->
<div id="add-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2 style="font-size: 1.5rem; color: #f97316; margin-bottom: 20px;">Report New Incident</h2>
        <form method="POST" id="add-form">
            <div class="grid">
                <div class="form-group">
                    <label for="add-member_id" class="form-label">Member <span class="required-mark">*</span></label>
                    <select name="member_id" id="add-member_id" class="input-field" required aria-describedby="add-member_id-error">
                        <option value="">Select Member</option>
                        <?php foreach ($all_members as $m): ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['member_id'] . ' - ' . $m['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-text" id="add-member_id-error">Please select a member.</span>
                </div>
                <div class="form-group">
                    <label for="add-incident_type" class="form-label">Incident Type <span class="required-mark">*</span></label>
                    <select name="incident_type" id="add-incident_type" class="input-field" required aria-describedby="add-incident_type-error">
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
                    <span class="error-text" id="add-incident_type-error">Incident type is required.</span>
                </div>
                <div class="form-group">
                    <label for="add-incident_datetime" class="form-label">Date & Time <span class="required-mark">*</span></label>
                    <input type="datetime-local" name="incident_datetime" id="add-incident_datetime" class="input-field" required aria-describedby="add-incident_datetime-error">
                    <span class="error-text" id="add-incident_datetime-error">Date and time are required.</span>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="add-remarks" class="form-label">Remarks</label>
                    <textarea name="remarks" id="add-remarks" class="input-field" rows="4"></textarea>
                </div>
            </div>
            <div class="flex" style="justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                <button type="submit" name="add" class="btn btn-primary"><i class="ri-add-line"></i> Report Incident</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <h2 style="font-size: 1.5rem; color: #f97316; margin-bottom: 20px;">Edit Incident</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="id" id="edit-id">
            <div class="grid">
                <div class="form-group">
                    <label for="edit-incident_type" class="form-label">Incident Type <span class="required-mark">*</span></label>
                    <input type="text" name="incident_type" id="edit-incident_type" class="input-field" required aria-describedby="edit-incident_type-error">
                    <span class="error-text" id="edit-incident_type-error">Incident type is required.</span>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="edit-remarks" class="form-label">Remarks</label>
                    <textarea name="remarks" id="edit-remarks" class="input-field" rows="4"></textarea>
                </div>
            </div>
            <div class="flex" style="justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                <button type="submit" name="update" class="btn btn-primary"><i class="ri-save-line"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal"><i class="ri-close-line"></i></button>
        <div style="text-align: center;">
            <i class="ri-error-warning-fill" style="font-size: 3rem; color: #e53e3e; margin-bottom: 20px;"></i>
            <h3 style="font-size: 1.5rem; font-weight: 700;">Confirm Deletion</h3>
            <p style="color: #718096; margin-top: 10px;">Are you sure you want to delete this incident? This action cannot be undone.</p>
            <form method="POST" id="delete-form">
                <input type="hidden" name="id" id="delete-id">
                <div class="flex" style="justify-content: center; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary modal-close"><i class="ri-close-line"></i> Cancel</button>
                    <button type="submit" name="delete" class="btn btn-primary"><i class="ri-delete-bin-line"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const addModal = document.getElementById('add-modal');
    const editModal = document.getElementById('edit-modal');
    const deleteModal = document.getElementById('delete-modal');
    const modalOverlay = document.querySelector('.modal-overlay');
    const addButton = document.getElementById('add-incident-btn');
    const editButtons = document.querySelectorAll('.edit-btn');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const closeButtons = document.querySelectorAll('.modal-close');
    const searchInput = document.getElementById('search-input');
    const clearSearch = document.getElementById('clear-search');
    const addForm = document.getElementById('add-form');
    const editForm = document.getElementById('edit-form');
    const searchForm = document.getElementById('search-form');
    const searchOptions = document.getElementById('search-options');
    const searchTypeInput = document.getElementById('search-type');
    const searchLoading = document.getElementById('search-loading');

    // Sidebar toggle
    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('expanded');
            document.body.classList.toggle('sidebar-expanded');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && sidebar.classList.contains('expanded') &&
                !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('expanded');
                document.body.classList.remove('sidebar-expanded');
            }
        });
    }

    // Add modal
    if (addButton) {
        addButton.addEventListener('click', () => {
            addModal.classList.add('show');
            modalOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    }

    // Edit modal
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            try {
                const incident = JSON.parse(button.getAttribute('data-incident'));
                document.getElementById('edit-id').value = incident.id || '';
                document.getElementById('edit-incident_type').value = incident.incident_type || '';
                document.getElementById('edit-remarks').value = incident.remarks || '';
                editModal.classList.add('show');
                modalOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } catch (e) {
                console.error('Failed to parse incident data:', e);
                alert('Error loading incident data. Please try again.');
            }
        });
    });

    // Delete modal
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            document.getElementById('delete-id').value = id;
            deleteModal.classList.add('show');
            modalOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modals
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            addModal.classList.remove('show');
            editModal.classList.remove('show');
            deleteModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
            addForm.reset();
            editForm.reset();
            clearErrors();
        });
    });

    // Click outside to close modals
    addModal.addEventListener('click', (e) => {
        if (e.target === addModal) {
            addModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
            addForm.reset();
            clearErrors();
        }
    });

    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            editModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
            editForm.reset();
            clearErrors();
        }
    });

    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            deleteModal.classList.remove('show');
            modalOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    // Form validation for add
    if (addForm) {
        addForm.addEventListener('submit', (e) => {
            let hasError = false;
            clearErrors();

            const memberId = document.getElementById('add-member_id');
            const incidentType = document.getElementById('add-incident_type');
            const incidentDatetime = document.getElementById('add-incident_datetime');

            if (!memberId.value) {
                showError('add-member_id-error', memberId);
                hasError = true;
            }
            if (!incidentType.value.trim()) {
                showError('add-incident_type-error', incidentType);
                hasError = true;
            }
            if (!incidentDatetime.value) {
                showError('add-incident_datetime-error', incidentDatetime);
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

            const incidentType = document.getElementById('edit-incident_type');

            if (!incidentType.value.trim()) {
                showError('edit-incident_type-error', incidentType);
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });
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

    // Search functionality
    if (searchInput && clearSearch && searchForm) {
        let debounceTimer;
        const debounce = (callback, delay) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(callback, delay);
        };

        // Toggle search options
        searchInput.addEventListener('focus', () => {
            searchOptions.classList.add('show');
        });

        document.addEventListener('click', (e) => {
            if (!searchForm.contains(e.target)) {
                searchOptions.classList.remove('show');
            }
        });

        // Handle search option selection
        searchOptions.querySelectorAll('.search-option').forEach(option => {
            option.addEventListener('click', () => {
                searchOptions.querySelectorAll('.search-option').forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');
                searchTypeInput.value = option.getAttribute('data-type');
                if (searchInput.value.trim()) {
                    searchLoading.classList.add('show');
                    searchForm.submit();
                }
            });
        });

        // Debounced search
        searchInput.addEventListener('input', () => {
            debounce(() => {
                if (searchInput.value.trim()) {
                    searchLoading.classList.add('show');
                    searchForm.submit();
                }
            }, 500);
        });

        // Clear search
        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            searchTypeInput.value = 'all';
            searchOptions.querySelectorAll('.search-option').forEach(opt => opt.classList.remove('active'));
            searchOptions.querySelector('.search-option[data-type="all"]').classList.add('active');
            searchInput.focus();
            searchForm.submit();
        });

        // Prevent empty search submission
        searchForm.addEventListener('submit', (e) => {
            if (!searchInput.value.trim() && searchTypeInput.value !== 'all') {
                e.preventDefault();
                searchInput.focus();
                searchInput.classList.add('error');
                setTimeout(() => searchInput.classList.remove('error'), 2000);
            } else {
                searchLoading.classList.add('show');
            }
        });

        // Keyboard navigation for search options
        searchOptions.querySelectorAll('.search-option').forEach((option, index) => {
            option.setAttribute('tabindex', '0');
            option.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    option.click();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = searchOptions.querySelectorAll('.search-option')[index + 1];
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = searchOptions.querySelectorAll('.search-option')[index - 1];
                    if (prev) prev.focus();
                }
            });
        });
    }
});
</script>
</body>
</html>