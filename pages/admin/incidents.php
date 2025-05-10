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
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (isset($_POST['delete'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                if ($incident->deleteIncident($id)) {
                    if ($isAjax) {
                        echo json_encode(['success' => true, 'message' => 'Incident deleted successfully.']);
                    } else {
                        $success = "Incident deleted successfully.";
                    }
                } else {
                    if ($isAjax) {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete incident. Please try again.']);
                    } else {
                        $error = "Failed to delete incident. Please try again.";
                    }
                }
            } catch (Exception $e) {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => 'Error deleting incident: ' . $e->getMessage()]);
                } else {
                    $error = "Error deleting incident: " . $e->getMessage();
                }
            }
        } else {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Invalid incident ID.']);
            } else {
                $error = "Invalid incident ID.";
            }
        }
        if ($isAjax) exit;
    } elseif (isset($_POST['update'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $incident_type = trim($_POST['incident_type'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $status = trim($_POST['status'] ?? '');

        if (empty($incident_type)) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Incident type is required.']);
            } else {
                $error = "Incident type is required.";
            }
        } elseif (!in_array($status, ['Open', 'In Progress', 'Resolved', 'Closed'])) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            } else {
                $error = "Invalid status.";
            }
        } else {
            try {
                $data = [
                    'incident_type' => $incident_type,
                    'remarks' => $remarks,
                    'status' => $status
                ];
                
                if ($incident->updateIncident($id, $data)) {
                    if ($isAjax) {
                        echo json_encode(['success' => true, 'message' => 'Incident updated successfully.']);
                    } else {
                        $success = "Incident updated successfully.";
                    }
                } else {
                    if ($isAjax) {
                        echo json_encode(['success' => false, 'message' => 'Failed to update incident. Please try again.']);
                    } else {
                        $error = "Failed to update incident. Please try again.";
                    }
                }
            } catch (Exception $e) {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => 'Error updating incident: ' . $e->getMessage()]);
                } else {
                    $error = "Error updating incident: " . $e->getMessage();
                }
            }
        }
        if ($isAjax) exit;
    } elseif (isset($_POST['add'])) {
        $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
        $incident_type = trim($_POST['incident_type'] ?? '');
        $incident_datetime = trim($_POST['incident_datetime'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$member_id) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Please select a valid member.']);
            } else {
                $error = "Please select a valid member.";
            }
        } elseif (empty($incident_type)) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Incident type is required.']);
            } else {
                $error = "Incident type is required.";
            }
        } elseif (empty($incident_datetime)) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Incident date and time are required.']);
            } else {
                $error = "Incident date and time are required.";
            }
        } else {
            try {
                $incident_id = $incident->generateIncidentId();
                if ($incident->addIncident($incident_id, $member_id, $incident_type, $incident_datetime, $remarks)) {
                    if ($isAjax) {
                        echo json_encode(['success' => true, 'message' => 'Incident reported successfully.']);
                    } else {
                        $success = "Incident reported successfully.";
                    }
                } else {
                    if ($isAjax) {
                        echo json_encode(['success' => false, 'message' => 'Failed to report incident. Please try again.']);
                    } else {
                        $error = "Failed to report incident. Please try again.";
                    }
                }
            } catch (Exception $e) {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => 'Error reporting incident: ' . $e->getMessage()]);
                } else {
                    $error = "Error reporting incident: " . $e->getMessage();
                }
            }
        }
        if ($isAjax) exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidents - Maranadara Society</title>
    <link rel="stylesheet" href="../../assets/css/incidents.css">
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

<!-- Popup Overlay -->
<div id="popup-overlay" class="popup-overlay"></div>

<!-- Success Popup -->
<div id="success-popup" class="popup">
    <div class="popup-content">
        <i class="ri-checkbox-circle-fill" style="font-size: 3rem; color: #48bb78; margin-bottom: 1rem;"></i>
        <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Success!</h3>
        <p class="popup-message" style="color: #4a5568; margin-bottom: 1rem;"></p>
        <p class="countdown" style="color: #718096; font-size: 0.875rem;">Redirecting in 3 seconds...</p>
    </div>
</div>

<!-- Error Popup -->
<div id="error-popup" class="popup">
    <div class="popup-content">
        <i class="ri-error-warning-fill" style="font-size: 3rem; color: #e53e3e; margin-bottom: 1rem;"></i>
        <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Error!</h3>
        <p class="popup-message" style="color: #4a5568; margin-bottom: 1rem;"></p>
        <button class="btn btn-secondary popup-close" style="margin-top: 1rem;">Close</button>
    </div>
</div>

<!-- Cancel Popup -->
<div id="cancel-popup" class="popup">
    <div class="popup-content">
        <i class="ri-close-circle-fill" style="font-size: 3rem; color: #f97316; margin-bottom: 1rem;"></i>
        <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Cancelled</h3>
        <p class="popup-message" style="color: #4a5568; margin-bottom: 1rem;">Operation has been cancelled.</p>
        <p class="countdown" style="color: #718096; font-size: 0.875rem;">Redirecting in 3 seconds...</p>
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