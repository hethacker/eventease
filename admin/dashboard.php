<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();

// 1. Fetch Metrics
$totalStudents = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalEvents   = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$totalRegs     = $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$totalCheckins = $db->query("SELECT COUNT(*) FROM registrations WHERE checkin_status = 'checked_in'")->fetchColumn();

$checkinRate = ($totalRegs > 0) ? round(($totalCheckins / $totalRegs) * 100, 1) : 0;

// Certificate & Results Stats
$totalPCerts = $db->query("SELECT COUNT(*) FROM certificates WHERE cert_type = 'participation' AND status = 'issued'")->fetchColumn();
$totalACerts = $db->query("SELECT COUNT(*) FROM certificates WHERE cert_type = 'achievement' AND status = 'issued'")->fetchColumn();
$eventsAwaitingRanking = $db->query("
    SELECT COUNT(DISTINCT e.id)
    FROM events e
    WHERE e.event_date < CURDATE()
    AND e.id NOT IN (SELECT DISTINCT event_id FROM event_rankings WHERE published = 1)
    AND EXISTS (SELECT 1 FROM registrations r WHERE r.event_id = e.id AND r.checkin_status = 'checked_in')
")->fetchColumn();
$pendingCertEvents = $db->query("
    SELECT COUNT(DISTINCT e.id)
    FROM events e
    LEFT JOIN certificate_settings cs ON cs.event_id = e.id
    WHERE e.event_date < CURDATE()
    AND (cs.certs_issued IS NULL OR cs.certs_issued = 0)
    AND EXISTS (SELECT 1 FROM registrations r WHERE r.event_id = e.id AND r.checkin_status = 'checked_in')
")->fetchColumn();
$waFailedCount = $db->query("SELECT COUNT(*) FROM whatsapp_notifications WHERE status = 'failed'")->fetchColumn();

// 2. Recent Event Registrations (Limit 6)
$recentRegsStmt = $db->query("
    SELECT r.*, u.name as student_name, u.college_id, u.department, e.title as event_title
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN events e ON r.event_id = e.id
    ORDER BY r.registered_at DESC
    LIMIT 6
");
$recentRegistrations = $recentRegsStmt->fetchAll();

// 3. Recently Registered Students (Limit 5)
$recentStudentsStmt = $db->query("
    SELECT u.id, u.name, u.email, u.phone, u.college_id, u.department, u.year, u.created_at,
           COUNT(r.id) as events_count
    FROM users u
    LEFT JOIN registrations r ON u.id = r.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.name, u.email, u.phone, u.college_id, u.department, u.year, u.created_at
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT 5
");
$recentStudents = $recentStudentsStmt->fetchAll();

// 4. Upcoming Events Summary (Limit 5)
$eventsStmt = $db->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 5");
$upcomingEvents = $eventsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 font-semibold"><i class="bi bi-shield-check me-1"></i> Admin Console</span>
                <h2 class="fw-bold mb-1">EventEase Command Center</h2>
                <p class="text-secondary small mb-0">Overview of campus event statistics, registered students, and gate check-ins.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="checkin.php" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark shadow">
                    <i class="bi bi-qr-code-scan me-1"></i> Launch QR Scanner
                </a>
                <a href="add-event.php" class="btn btn-gradient-primary btn-sm rounded-pill shadow">
                    <i class="bi bi-plus-lg me-1"></i> Create Event
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Metrics Grid -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <a href="students.php" class="text-decoration-none">
                <div class="stat-card border-start border-primary border-4 h-100 transition-all hover-shadow">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon icon-purple"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $totalStudents; ?></h3>
                                <span class="text-muted small">Registered Students</span>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-md-6">
            <a href="events.php" class="text-decoration-none">
                <div class="stat-card border-start border-info border-4 h-100 transition-all hover-shadow">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon icon-cyan"><i class="bi bi-calendar-event"></i></div>
                            <div>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $totalEvents; ?></h3>
                                <span class="text-muted small">Total Events</span>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-md-6">
            <a href="registrations.php" class="text-decoration-none">
                <div class="stat-card border-start border-warning border-4 h-100 transition-all hover-shadow">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon icon-amber"><i class="bi bi-ticket-perforated-fill"></i></div>
                            <div>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $totalRegs; ?></h3>
                                <span class="text-muted small">Event Passes Issued</span>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-md-6">
            <a href="attendance.php" class="text-decoration-none">
                <div class="stat-card border-start border-success border-4 h-100 transition-all hover-shadow">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon icon-emerald"><i class="bi bi-qr-code-scan"></i></div>
                            <div>
                                <h3 class="fw-bold mb-0 text-dark"><?php echo $totalCheckins; ?> <small class="fs-6 text-muted">(<?php echo $checkinRate; ?>%)</small></h3>
                                <span class="text-muted small">Gate Check-Ins Verified</span>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Certificate & Results Management Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-trophy-fill me-2 text-warning"></i>Certificate & Results Management</h5>
                <p class="text-muted small mb-0">Manage event rankings, issue certificates, and track WhatsApp notifications.</p>
            </div>
            <a href="winners.php" class="btn btn-sm btn-warning rounded-pill text-dark fw-bold">
                <i class="bi bi-trophy me-1"></i> Manage Winners
            </a>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-6">
                    <a href="cert-records.php?type=participation" class="text-decoration-none">
                        <div class="card border-0 bg-success bg-opacity-10 rounded-3 p-3 text-center h-100">
                            <i class="bi bi-award-fill text-success fs-3 mb-2"></i>
                            <div class="fw-bold fs-4 text-success"><?php echo $totalPCerts; ?></div>
                            <small class="text-muted">Participation Certs Issued</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="cert-records.php?type=achievement" class="text-decoration-none">
                        <div class="card border-0 bg-warning bg-opacity-10 rounded-3 p-3 text-center h-100">
                            <i class="bi bi-trophy-fill text-warning fs-3 mb-2"></i>
                            <div class="fw-bold fs-4 text-warning"><?php echo $totalACerts; ?></div>
                            <small class="text-muted">Achievement Certs Issued</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="winners.php" class="text-decoration-none">
                        <div class="card border-0 bg-danger bg-opacity-10 rounded-3 p-3 text-center h-100">
                            <i class="bi bi-hourglass-split text-danger fs-3 mb-2"></i>
                            <div class="fw-bold fs-4 text-danger"><?php echo $eventsAwaitingRanking; ?></div>
                            <small class="text-muted">Events Awaiting Rankings</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="wa-logs.php?status=failed" class="text-decoration-none">
                        <div class="card border-0 bg-secondary bg-opacity-10 rounded-3 p-3 text-center h-100">
                            <i class="bi bi-whatsapp text-success fs-3 mb-2"></i>
                            <div class="fw-bold fs-4"><?php echo $waFailedCount; ?></div>
                            <small class="text-muted">Failed WA Notifications</small>
                        </div>
                    </a>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="winners.php" class="btn btn-sm btn-warning rounded-pill text-dark"><i class="bi bi-trophy me-1"></i> Assign Winners</a>
                <a href="cert-records.php" class="btn btn-sm btn-success rounded-pill"><i class="bi bi-award me-1"></i> View All Certificates</a>
                <a href="wa-logs.php" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-whatsapp me-1"></i> WA Notification Logs</a>
                <a href="wa-logs.php?status=failed" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-arrow-clockwise me-1"></i> Retry Failed Notifications</a>
            </div>
        </div>
    </div>

    <!-- Section 1: Recently Registered Students (Real Student Accounts) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1">
                    <i class="bi bi-person-check-fill me-2 text-success"></i> Recently Registered Students
                </h5>
                <p class="text-muted small mb-0">Live accounts created by students through the portal</p>
            </div>
            <a href="students.php" class="btn btn-sm btn-outline-primary rounded-pill">
                View All Students (<?php echo $totalStudents; ?>) <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Student Name</th>
                            <th>College ID / Roll No</th>
                            <th>Department & Year</th>
                            <th>Contact Email</th>
                            <th>Passes Count</th>
                            <th>Joined Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentStudents)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No student accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentStudents as $st): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                                <?php 
                                                    $parts = explode(' ', trim($st['name']));
                                                    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                                                    echo htmlspecialchars($initials ?: 'ST');
                                                ?>
                                            </div>
                                            <div>
                                                <strong class="d-block text-dark"><?php echo htmlspecialchars($st['name']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark px-2 py-1"><?php echo htmlspecialchars($st['college_id']); ?></span>
                                    </td>
                                    <td>
                                        <span class="d-block small text-dark fw-semibold"><?php echo htmlspecialchars($st['department']); ?></span>
                                        <small class="text-muted"><?php echo htmlspecialchars($st['year']); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($st['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($st['events_count'] > 0): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">
                                                <?php echo $st['events_count']; ?> Registered
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">0 Events</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo date('M j, Y, g:i A', strtotime($st['created_at'])); ?></small>
                                    </td>
                                    <td class="text-end">
                                        <a href="registrations.php?search=<?php echo urlencode($st['college_id']); ?>" class="btn btn-sm btn-outline-secondary" title="View Event Passes">
                                            <i class="bi bi-ticket-perforated"></i> Passes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Recent Event Registrations & Upcoming Events -->
    <div class="row g-4">
        <!-- Left Col: Recent Registrations -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Event Passes Issued</h5>
                        <p class="text-muted small mb-0">Students registered for specific campus events</p>
                    </div>
                    <a href="registrations.php" class="btn btn-sm btn-outline-primary rounded-pill">View All Passes</a>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Student</th>
                                    <th>Event</th>
                                    <th>Reg Code</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRegistrations)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No registrations found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentRegistrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <strong class="d-block text-dark"><?php echo htmlspecialchars($reg['student_name']); ?></strong>
                                                <small class="text-muted"><?php echo htmlspecialchars($reg['college_id']); ?> &bull; <?php echo htmlspecialchars($reg['department']); ?></small>
                                            </td>
                                            <td><span class="small text-truncate d-inline-block fw-semibold" style="max-width: 150px;"><?php echo htmlspecialchars($reg['event_title']); ?></span></td>
                                            <td><code><?php echo htmlspecialchars($reg['registration_code']); ?></code></td>
                                            <td>
                                                <?php if ($reg['checkin_status'] === 'checked_in'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">Checked In</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Col: Upcoming Events Quick Summary -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-calendar-check me-2 text-info"></i> Manage Events</h5>
                        <p class="text-muted small mb-0">Upcoming schedule and capacity</p>
                    </div>
                    <a href="events.php" class="btn btn-sm btn-outline-dark rounded-pill">Manage All</a>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingEvents as $ev): 
                            $pct = ($ev['capacity'] > 0) ? min(100, round(($ev['registered_count'] / $ev['capacity']) * 100)) : 0;
                        ?>
                            <div class="list-group-item border-0 px-0 py-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold mb-0 text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars($ev['title']); ?></h6>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ev['category']); ?></span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('M j, Y', strtotime($ev['event_date'])); ?> &bull; <i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($ev['venue']); ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span class="small fw-bold text-muted"><?php echo $ev['registered_count']; ?>/<?php echo $ev['capacity']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
