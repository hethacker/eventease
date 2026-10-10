<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();
$db = getDB();

// Filters
$typeFilter   = $_GET['type']   ?? '';
$eventFilter  = intval($_GET['event_id'] ?? 0);
$search       = trim($_GET['search'] ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$binds  = [];

if ($typeFilter) {
    $where[] = 'c.cert_type = ?';
    $binds[] = $typeFilter;
}
if ($eventFilter) {
    $where[] = 'c.event_id = ?';
    $binds[] = $eventFilter;
}
if ($search) {
    $where[] = '(u.name LIKE ? OR c.certificate_id LIKE ? OR u.college_id LIKE ?)';
    $binds   = array_merge($binds, ["%$search%", "%$search%", "%$search%"]);
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("
    SELECT COUNT(*) FROM certificates c
    JOIN users u ON c.user_id = u.id
    JOIN events e ON c.event_id = e.id
    WHERE $whereSql
");
$countStmt->execute($binds);
$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$certsStmt = $db->prepare("
    SELECT c.*, u.name as student_name, u.college_id, u.department,
           e.title as event_title, e.event_date
    FROM certificates c
    JOIN users u ON c.user_id = u.id
    JOIN events e ON c.event_id = e.id
    WHERE $whereSql
    ORDER BY c.issued_at DESC
    LIMIT $perPage OFFSET $offset
");
$certsStmt->execute($binds);
$certs = $certsStmt->fetchAll();

$allEvents = $db->query("SELECT id, title FROM events ORDER BY title ASC")->fetchAll();

$totalPart = $db->query("SELECT COUNT(*) FROM certificates WHERE cert_type = 'participation'")->fetchColumn();
$totalAch  = $db->query("SELECT COUNT(*) FROM certificates WHERE cert_type = 'achievement'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-primary px-3 py-1 rounded-pill mb-2 fw-semibold">
                    <i class="bi bi-file-earmark-text me-1"></i> Certificate Records
                </span>
                <h2 class="fw-bold mb-1">All Certificate Records</h2>
                <p class="text-secondary small mb-0">View and manage all issued digital certificates.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="winners.php" class="btn btn-warning btn-sm rounded-pill text-dark"><i class="bi bi-trophy me-1"></i> Manage Winners</a>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-emerald"><i class="bi bi-award-fill"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $totalPart; ?></h3>
                    <span class="text-muted small">Participation Certificates</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:linear-gradient(135deg,#B8860B,#FFD700);border-radius:12px;width:48px;height:48px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $totalAch; ?></h3>
                    <span class="text-muted small">Achievement Certificates</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-purple"><i class="bi bi-collection"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $totalPart + $totalAch; ?></h3>
                    <span class="text-muted small">Total Certificates Issued</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="cert-records.php" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, cert ID, college ID..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="participation" <?php echo $typeFilter === 'participation' ? 'selected' : ''; ?>>Participation</option>
                        <option value="achievement" <?php echo $typeFilter === 'achievement' ? 'selected' : ''; ?>>Achievement</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="event_id" class="form-select form-select-sm">
                        <option value="">All Events</option>
                        <?php foreach ($allEvents as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo ($ev['id'] == $eventFilter) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="cert-records.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Certificates Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($certs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-award fs-1"></i>
                    <p class="mt-3 fw-semibold">No certificates found.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark small text-uppercase">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Certificate ID</th>
                            <th>Issued On</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($certs as $cert):
                        $isAch = $cert['cert_type'] === 'achievement';
                        $rankEmojis = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                    ?>
                    <tr>
                        <td class="ps-4">
                            <strong><?php echo htmlspecialchars($cert['student_name']); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars($cert['college_id']); ?> &bull; <?php echo htmlspecialchars($cert['department']); ?></div>
                        </td>
                        <td>
                            <span class="small fw-semibold"><?php echo htmlspecialchars($cert['event_title']); ?></span>
                            <div class="small text-muted"><?php echo date('M j, Y', strtotime($cert['event_date'])); ?></div>
                        </td>
                        <td>
                            <?php if ($isAch): ?>
                                <span class="badge bg-warning text-dark">
                                    <?php echo $rankEmojis[$cert['rank']] ?? ''; ?> Achievement (Rank <?php echo $cert['rank']; ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">📜 Participation</span>
                            <?php endif; ?>
                        </td>
                        <td><code class="small"><?php echo htmlspecialchars($cert['certificate_id']); ?></code></td>
                        <td class="small text-muted"><?php echo date('M j, Y, g:i A', strtotime($cert['issued_at'])); ?></td>
                        <td class="text-end pe-4">
                            <a href="../certificate.php?id=<?php echo urlencode($cert['certificate_id']); ?>&preview=1"
                               target="_blank"
                               class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="bi bi-eye me-1"></i> Preview
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                <small class="text-muted">Showing <?php echo count($certs); ?> of <?php echo $totalRows; ?> certificates</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $p; ?>&type=<?php echo urlencode($typeFilter); ?>&event_id=<?php echo $eventFilter; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $p; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
