<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();

$selectedEventId = intval($_GET['event_id'] ?? 0);

// Fetch all events for dropdown filter
$eventsList = $db->query("SELECT id, title, event_date, capacity FROM events ORDER BY event_date ASC")->fetchAll();

if ($selectedEventId <= 0 && !empty($eventsList)) {
    $selectedEventId = $eventsList[0]['id'];
}

// Fetch selected event details
$selectedEvent = null;
if ($selectedEventId > 0) {
    $eStmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $eStmt->execute([$selectedEventId]);
    $selectedEvent = $eStmt->fetch();
}

// Fetch registration records for selected event
$registrations = [];
$checkedInStudents = [];
$pendingStudents = [];

if ($selectedEventId > 0) {
    $rStmt = $db->prepare("
        SELECT r.*, u.name as student_name, u.email as student_email, u.phone, u.college_id, u.department, u.year
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        WHERE r.event_id = ?
        ORDER BY r.checkin_status DESC, r.checkin_time ASC, r.registered_at ASC
    ");
    $rStmt->execute([$selectedEventId]);
    $registrations = $rStmt->fetchAll();

    foreach ($registrations as $r) {
        if ($r['checkin_status'] === 'checked_in') {
            $checkedInStudents[] = $r;
        } else {
            $pendingStudents[] = $r;
        }
    }
}

$totalRegistered = count($registrations);
$totalCheckedIn  = count($checkedInStudents);
$totalPending    = count($pendingStudents);
$attendancePct   = ($totalRegistered > 0) ? round(($totalCheckedIn / $totalRegistered) * 100, 1) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 font-semibold"><i class="bi bi-bar-chart-line me-1"></i> Analytics</span>
                <h2 class="fw-bold mb-1">Event Attendance Reports</h2>
                <p class="text-secondary small mb-0">Track real-time gate check-ins and generate attendance sheets.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="students.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-people-fill me-1"></i> Registered Students
                </a>
                <button type="button" class="btn btn-outline-light btn-sm rounded-pill" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Attendance Report
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Event Selector Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4">
            <form action="attendance.php" method="GET" class="row align-items-center g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold text-muted small">Select Event for Attendance Breakdown</label>
                    <select name="event_id" class="form-select form-select-lg" onchange="this.form.submit()">
                        <?php foreach ($eventsList as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo ($selectedEventId === $ev['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['title']); ?> (<?php echo date('M j, Y', strtotime($ev['event_date'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 text-md-end pt-md-4">
                    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                        <i class="bi bi-calendar-event me-1"></i> <?php echo date('F j, Y', strtotime($selectedEvent['event_date'])); ?>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedEvent): ?>

        <!-- Attendance Stats Overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card border-start border-primary border-4">
                    <h3 class="fw-bold mb-0 text-primary"><?php echo $totalRegistered; ?></h3>
                    <span class="text-muted small">Total Registered</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border-start border-success border-4">
                    <h3 class="fw-bold mb-0 text-success"><?php echo $totalCheckedIn; ?></h3>
                    <span class="text-muted small">Checked In</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border-start border-warning border-4">
                    <h3 class="fw-bold mb-0 text-warning"><?php echo $totalPending; ?></h3>
                    <span class="text-muted small">Pending Check-Ins</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border-start border-info border-4">
                    <h3 class="fw-bold mb-0 text-info"><?php echo $attendancePct; ?>%</h3>
                    <span class="text-muted small">Attendance Turnout Rate</span>
                </div>
            </div>
        </div>

        <!-- Attendance Progress Bar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">Event Turnout Rate</h6>
                    <span class="fw-bold text-success"><?php echo $totalCheckedIn; ?> / <?php echo $totalRegistered; ?> Checked In (<?php echo $attendancePct; ?>%)</span>
                </div>
                <div class="progress" style="height: 14px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendancePct; ?>%;" aria-valuenow="<?php echo $attendancePct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <!-- Detailed Attendance Tabs -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <ul class="nav nav-tabs card-header-tabs" id="attendanceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="checkedin-tab" data-bs-toggle="tab" data-bs-target="#checkedin" type="button" role="tab">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> Checked-In Students (<?php echo $totalCheckedIn; ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                            <i class="bi bi-clock-history text-warning me-1"></i> Pending Check-Ins (<?php echo $totalPending; ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                            All Registered (<?php echo $totalRegistered; ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content" id="attendanceTabsContent">
                    
                    <!-- Tab 1: Checked-In -->
                    <div class="tab-pane fade show active" id="checkedin" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>College ID</th>
                                        <th>Department & Year</th>
                                        <th>Reg Code</th>
                                        <th>Check-In Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($checkedInStudents)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No students have checked in for this event yet.</td></tr>
                                    <?php else: ?>
                                        <?php $idx = 1; foreach ($checkedInStudents as $st): ?>
                                            <tr>
                                                <td><?php echo $idx++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($st['student_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($st['student_email']); ?></small></td>
                                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($st['college_id']); ?></span></td>
                                                <td><?php echo htmlspecialchars($st['department']); ?> (<?php echo htmlspecialchars($st['year']); ?>)</td>
                                                <td><code><?php echo htmlspecialchars($st['registration_code']); ?></code></td>
                                                <td><span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-clock-check me-1"></i> <?php echo date('g:i:s A, M j', strtotime($st['checkin_time'])); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Pending -->
                    <div class="tab-pane fade" id="pending" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>College ID</th>
                                        <th>Department</th>
                                        <th>Reg Code</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pendingStudents)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">All registered students have been checked in!</td></tr>
                                    <?php else: ?>
                                        <?php $idx = 1; foreach ($pendingStudents as $st): ?>
                                            <tr>
                                                <td><?php echo $idx++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($st['student_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($st['student_email']); ?></small></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($st['college_id']); ?></span></td>
                                                <td><?php echo htmlspecialchars($st['department']); ?></td>
                                                <td><code><?php echo htmlspecialchars($st['registration_code']); ?></code></td>
                                                <td>
                                                    <a href="registrations.php?action=manual_checkin&id=<?php echo $st['id']; ?>&event_id=<?php echo $selectedEventId; ?>" class="btn btn-sm btn-outline-success">Check In</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 3: All -->
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>College ID</th>
                                        <th>Reg Code</th>
                                        <th>Status</th>
                                        <th>Check-In Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $r): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($r['college_id']); ?></code></td>
                                            <td><code><?php echo htmlspecialchars($r['registration_code']); ?></code></td>
                                            <td>
                                                <?php if ($r['checkin_status'] === 'checked_in'): ?>
                                                    <span class="badge bg-success">Checked In</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $r['checkin_time'] ? date('g:i:s A', strtotime($r['checkin_time'])) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
