<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();

// Handle Manual Check-In action
if (isset($_GET['action']) && $_GET['action'] === 'manual_checkin' && isset($_GET['id'])) {
    $regId = intval($_GET['id']);
    if ($regId > 0) {
        $checkStmt = $db->prepare("UPDATE registrations SET checkin_status = 'checked_in', checkin_time = NOW() WHERE id = ?");
        $checkStmt->execute([$regId]);
        setFlash('success', 'Student manually checked in successfully.');
    }
    header('Location: registrations.php' . (isset($_GET['event_id']) ? '?event_id=' . intval($_GET['event_id']) : ''));
    exit;
}

// Filters
$eventIdFilter = intval($_GET['event_id'] ?? 0);
$statusFilter  = trim($_GET['status'] ?? '');
$typeFilter    = trim($_GET['type'] ?? '');
$searchFilter  = trim($_GET['search'] ?? '');

// Fetch list of events for filter dropdown
$eventsList = $db->query("SELECT id, title FROM events ORDER BY title ASC")->fetchAll();

// Build query
$query = "
    SELECT r.*, u.name as student_name, u.email as student_email, u.phone, u.college_id, u.department, u.year, e.title as event_title, e.venue
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN events e ON r.event_id = e.id
    WHERE 1=1
";
$params = [];

if ($eventIdFilter > 0) {
    $query .= " AND r.event_id = ?";
    $params[] = $eventIdFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND r.checkin_status = ?";
    $params[] = $statusFilter;
}

if (!empty($typeFilter)) {
    $query .= " AND r.registration_type = ?";
    $params[] = $typeFilter;
}

if (!empty($searchFilter)) {
    $query .= " AND (u.name LIKE ? OR u.college_id LIKE ? OR u.email LIKE ? OR r.registration_code LIKE ? OR r.team_name LIKE ? OR r.team_members LIKE ?)";
    $term = "%{$searchFilter}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY r.registered_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 font-semibold">Admin Panel</span>
                <h2 class="fw-bold mb-1">Event Registrations Directory</h2>
                <p class="text-secondary small mb-0">Search, inspect, and manage student and team registration passes.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="students.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-people-fill me-1"></i> Registered Students
                </a>
                <a href="checkin.php" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark shadow">
                    <i class="bi bi-qr-code-scan me-1"></i> Open QR Scanner
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Filters Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="registrations.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Search Student / Team / Code</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Team, Roll No, Code..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Filter by Event</label>
                    <select name="event_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Events</option>
                        <?php foreach ($eventsList as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo ($eventIdFilter === $ev['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Registration Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="individual" <?php echo ($typeFilter === 'individual') ? 'selected' : ''; ?>>Individual Solo</option>
                        <option value="team" <?php echo ($typeFilter === 'team') ? 'selected' : ''; ?>>Team Registration</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">Check-In Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="checked_in" <?php echo ($statusFilter === 'checked_in') ? 'selected' : ''; ?>>Checked In</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-gradient-primary w-100">Filter</button>
                    <?php if ($eventIdFilter > 0 || !empty($statusFilter) || !empty($typeFilter) || !empty($searchFilter)): ?>
                        <a href="registrations.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Total Registrations Found: <?php echo count($registrations); ?></h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Student / Team Info</th>
                            <th>College ID & Dept</th>
                            <th>Event Title</th>
                            <th>Reg Code</th>
                            <th>Type & Size</th>
                            <th>Registered On</th>
                            <th>Check-In Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registrations)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No registration records found for the selected criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($registrations as $r): 
                                $isTeam = ($r['registration_type'] ?? '') === 'team';
                                $teamMembers = $isTeam && !empty($r['team_members']) ? json_decode($r['team_members'], true) : [];
                                $teamModalId = "teamModal_" . $r['id'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle <?php echo $isTeam ? 'bg-primary text-white' : 'bg-primary bg-opacity-10 text-primary'; ?> fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                                <?php if ($isTeam): ?>
                                                    <i class="bi bi-people-fill"></i>
                                                <?php else: 
                                                    $parts = explode(' ', trim($r['student_name']));
                                                    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                                                    echo htmlspecialchars($initials ?: 'ST');
                                                endif; ?>
                                            </div>
                                            <div>
                                                <?php if ($isTeam): ?>
                                                    <strong class="d-block text-primary">
                                                        <i class="bi bi-flag-fill me-1"></i> <?php echo htmlspecialchars($r['team_name'] ?? 'Team'); ?>
                                                    </strong>
                                                    <small class="text-muted">Leader: <?php echo htmlspecialchars($r['student_name']); ?></small>
                                                <?php else: ?>
                                                    <strong class="d-block text-dark"><?php echo htmlspecialchars($r['student_name']); ?></strong>
                                                    <small class="text-muted"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($r['student_email']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border mb-1"><?php echo htmlspecialchars($r['college_id']); ?></span>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($r['department']); ?> (<?php echo htmlspecialchars($r['year']); ?>)</small>
                                    </td>
                                    <td><small class="fw-semibold text-dark text-truncate d-inline-block" style="max-width: 170px;"><?php echo htmlspecialchars($r['event_title']); ?></small></td>
                                    <td><code><?php echo htmlspecialchars($r['registration_code']); ?></code></td>
                                    <td>
                                        <?php if ($isTeam): ?>
                                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill py-0 px-2 small" data-bs-toggle="modal" data-bs-target="#<?php echo $teamModalId; ?>">
                                                <i class="bi bi-people-fill me-1"></i> Team (<?php echo intval($r['team_size'] ?? 1); ?>)
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">Solo (1)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('M j, Y, g:i A', strtotime($r['registered_at'])); ?></small></td>
                                    <td>
                                        <?php if ($r['checkin_status'] === 'checked_in'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i> Checked In
                                            </span>
                                            <?php if ($r['checkin_time']): ?>
                                                <small class="d-block text-muted" style="font-size: 0.7rem;"><?php echo date('g:i A', strtotime($r['checkin_time'])); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1">
                                                <i class="bi bi-clock me-1"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($r['checkin_status'] === 'pending'): ?>
                                            <a href="registrations.php?action=manual_checkin&id=<?php echo $r['id']; ?><?php echo ($eventIdFilter > 0 ? '&event_id='.$eventIdFilter : ''); ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Manually mark this student/team as checked in?')">
                                                <i class="bi bi-check-lg me-1"></i> Check In
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-shield-check text-success fs-5"></i></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($isTeam): ?>
                                <!-- Team Roster Modal -->
                                <div class="modal fade" id="<?php echo $teamModalId; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-dark text-white rounded-top-4">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-people-fill text-primary me-2"></i> Team: <?php echo htmlspecialchars($r['team_name'] ?? ''); ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="mb-3 p-3 bg-light rounded-3 border">
                                                    <div class="text-muted small mb-1"><strong>Event:</strong> <?php echo htmlspecialchars($r['event_title']); ?></div>
                                                    <div class="text-muted small"><strong>Reg Code:</strong> <code><?php echo htmlspecialchars($r['registration_code']); ?></code></div>
                                                </div>

                                                <h6 class="fw-bold mb-2">Team Roster (<?php echo intval($r['team_size'] ?? 1); ?> Members)</h6>
                                                
                                                <!-- Leader -->
                                                <div class="p-2 mb-2 bg-primary bg-opacity-10 rounded border border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong class="d-block text-primary"><i class="bi bi-star-fill text-warning me-1"></i> <?php echo htmlspecialchars($r['student_name']); ?> (Leader)</strong>
                                                        <small class="text-muted"><?php echo htmlspecialchars($r['student_email']); ?> &bull; <?php echo htmlspecialchars($r['phone']); ?></small>
                                                    </div>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($r['college_id']); ?></span>
                                                </div>

                                                <!-- Members -->
                                                <?php if (!empty($teamMembers)): ?>
                                                    <?php foreach ($teamMembers as $idx => $m): ?>
                                                        <div class="p-2 mb-2 bg-white rounded border d-flex justify-content-between align-items-center shadow-sm">
                                                            <div>
                                                                <strong class="d-block text-dark">#<?php echo $idx + 2; ?>: <?php echo htmlspecialchars($m['name']); ?></strong>
                                                                <small class="text-muted"><?php echo htmlspecialchars($m['email'] ?: $m['phone'] ?: 'No contact specified'); ?></small>
                                                            </div>
                                                            <span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($m['college_id']); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer bg-light rounded-bottom-4">
                                                <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
