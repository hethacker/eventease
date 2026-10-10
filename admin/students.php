<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();

// Filters
$searchFilter     = trim($_GET['search'] ?? '');
$departmentFilter = trim($_GET['department'] ?? '');
$yearFilter       = trim($_GET['year'] ?? '');

// Base query
$query = "
    SELECT u.id, u.name, u.email, u.phone, u.college_id, u.department, u.year, u.created_at,
           COUNT(r.id) AS total_events_registered,
           SUM(CASE WHEN r.checkin_status = 'checked_in' THEN 1 ELSE 0 END) AS total_checked_in
    FROM users u
    LEFT JOIN registrations r ON u.id = r.user_id
    WHERE u.role = 'student'
";

$params = [];

if (!empty($searchFilter)) {
    $query .= " AND (u.name LIKE ? OR u.college_id LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$searchFilter}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($departmentFilter)) {
    $query .= " AND u.department = ?";
    $params[] = $departmentFilter;
}

if (!empty($yearFilter)) {
    $query .= " AND u.year = ?";
    $params[] = $yearFilter;
}

$query .= " GROUP BY u.id, u.name, u.email, u.phone, u.college_id, u.department, u.year, u.created_at";
$query .= " ORDER BY u.created_at DESC, u.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch distinct departments for filter dropdown
$deptStmt = $db->query("SELECT DISTINCT department FROM users WHERE role = 'student' AND department != '' ORDER BY department ASC");
$departmentsList = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch distinct years for filter dropdown
$yearStmt = $db->query("SELECT DISTINCT year FROM users WHERE role = 'student' AND year != '' ORDER BY year ASC");
$yearsList = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

$totalStudentsCount = count($students);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 font-semibold">
                    <i class="bi bi-shield-check me-1"></i> Admin Panel
                </span>
                <h2 class="fw-bold mb-1">Registered Students Directory</h2>
                <p class="text-secondary small mb-0">View all real student accounts registered on the EventEase platform.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="registrations.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-ticket-perforated me-1"></i> View Event Passes
                </a>
                <a href="checkin.php" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark shadow">
                    <i class="bi bi-qr-code-scan me-1"></i> QR Scanner
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Filters Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="students.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Search Student</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, roll no, email..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Filter by Department</label>
                    <select name="department" class="form-select" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departmentsList as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($departmentFilter === $dept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Filter by Year</label>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        <?php foreach ($yearsList as $yr): ?>
                            <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yearFilter === $yr) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($yr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-gradient-primary w-100">Filter</button>
                    <?php if (!empty($searchFilter) || !empty($departmentFilter) || !empty($yearFilter)): ?>
                        <a href="students.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Directory Table Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-people-fill text-primary me-2"></i> Registered Students: <?php echo $totalStudentsCount; ?>
                </h5>
                <span class="text-muted small">Showing live student records</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>College ID / Roll No</th>
                            <th>Department & Year</th>
                            <th>Contact Details</th>
                            <th>Events Registered</th>
                            <th>Registered Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                    No registered students found matching the selected criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $serial = 1; foreach ($students as $stu): ?>
                                <tr>
                                    <td class="text-muted small fw-semibold"><?php echo $serial++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                <?php 
                                                    $parts = explode(' ', trim($stu['name']));
                                                    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                                                    echo htmlspecialchars($initials ?: 'ST');
                                                ?>
                                            </div>
                                            <div>
                                                <strong class="d-block text-dark fs-6"><?php echo htmlspecialchars($stu['name']); ?></strong>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success" style="font-size: 0.7rem;">Active Student</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark px-2 py-1 fs-7"><?php echo htmlspecialchars($stu['college_id']); ?></span>
                                    </td>
                                    <td>
                                        <strong class="d-block text-dark small"><?php echo htmlspecialchars($stu['department']); ?></strong>
                                        <small class="text-muted"><i class="bi bi-mortarboard me-1"></i><?php echo htmlspecialchars($stu['year']); ?></small>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div class="text-dark"><i class="bi bi-envelope me-1 text-primary"></i><?php echo htmlspecialchars($stu['email']); ?></div>
                                            <div class="text-muted"><i class="bi bi-telephone me-1 text-secondary"></i><?php echo htmlspecialchars($stu['phone']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($stu['total_events_registered'] > 0): ?>
                                            <a href="registrations.php?search=<?php echo urlencode($stu['college_id']); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-0">
                                                <i class="bi bi-ticket-perforated me-1"></i> <?php echo $stu['total_events_registered']; ?> Event(s)
                                            </a>
                                            <?php if ($stu['total_checked_in'] > 0): ?>
                                                <small class="d-block text-success mt-1" style="font-size: 0.72rem;">
                                                    <i class="bi bi-check-circle-fill me-1"></i> <?php echo $stu['total_checked_in']; ?> Checked in
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">0 Events</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block"><?php echo date('M j, Y', strtotime($stu['created_at'])); ?></small>
                                        <small class="text-muted" style="font-size: 0.72rem;"><?php echo date('g:i A', strtotime($stu['created_at'])); ?></small>
                                    </td>
                                    <td class="text-end">
                                        <a href="registrations.php?search=<?php echo urlencode($stu['name']); ?>" class="btn btn-sm btn-outline-secondary" title="View Event Registrations">
                                            <i class="bi bi-eye"></i> View Passes
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
