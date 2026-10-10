<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/whatsapp-service.php';

requireAdmin();
$db    = getDB();
$waSvc = new WhatsAppService();

// Handle retry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry') {
    $notifId = intval($_POST['notification_id'] ?? 0);
    $result  = $waSvc->retryNotification($notifId);
    $flash   = $result['success'] ? 'Retry sent successfully.' : ('Retry failed: ' . ($result['reason'] ?? $result['info'] ?? 'Unknown error.'));
    $ftype   = $result['success'] ? 'success' : 'danger';
    header("Location: wa-logs.php?flash=" . urlencode($flash) . "&ftype=$ftype");
    exit;
}

$flash = $_GET['flash'] ?? '';
$ftype = $_GET['ftype'] ?? 'info';

// Filters
$statusFilter = $_GET['status'] ?? '';
$typeFilter   = $_GET['type']   ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where    = ['1=1'];
$binds    = [];

if ($statusFilter) {
    $where[] = 'wn.status = ?';
    $binds[] = $statusFilter;
}
if ($typeFilter) {
    $where[] = 'wn.notification_type = ?';
    $binds[] = $typeFilter;
}
if ($search) {
    $where[] = '(u.name LIKE ? OR u.phone LIKE ? OR e.title LIKE ?)';
    $binds   = array_merge($binds, ["%$search%", "%$search%", "%$search%"]);
}

$whereSql = implode(' AND ', $where);

// Count total
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM whatsapp_notifications wn
    JOIN users u  ON wn.user_id  = u.id
    LEFT JOIN events e ON wn.event_id = e.id
    WHERE $whereSql
");
$countStmt->execute($binds);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch logs
$logsStmt = $db->prepare("
    SELECT wn.*, u.name as student_name, u.phone, u.college_id,
           e.title as event_title
    FROM whatsapp_notifications wn
    JOIN users u ON wn.user_id = u.id
    LEFT JOIN events e ON wn.event_id = e.id
    WHERE $whereSql
    ORDER BY wn.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$logsStmt->execute($binds);
$logs = $logsStmt->fetchAll();

// Summary stats
$statStmt = $db->query("SELECT status, COUNT(*) as cnt FROM whatsapp_notifications GROUP BY status");
$stats = [];
foreach ($statStmt->fetchAll() as $s) { $stats[$s['status']] = $s['cnt']; }

$waConfigured = (WA_PROVIDER !== 'none');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-success px-3 py-1 rounded-pill mb-2 fw-semibold">
                    <i class="bi bi-whatsapp me-1"></i> WhatsApp Notifications
                </span>
                <h2 class="fw-bold mb-1">WhatsApp Notification Logs</h2>
                <p class="text-secondary small mb-0">Track all WhatsApp notifications sent to students.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="winners.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-trophy me-1"></i> Winners & Certs</a>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($ftype); ?> alert-dismissible fade show mb-4">
            <?php echo htmlspecialchars($flash); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Provider Status Banner -->
    <?php if (!$waConfigured): ?>
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 mt-1"></i>
        <div>
            <strong>WhatsApp integration not configured.</strong><br>
            Set <code>WA_PROVIDER</code> in <code>config/whatsapp.php</code> to 'twilio' or 'meta' and provide API credentials.
            Notifications will be logged as <span class="badge bg-warning text-dark">skipped</span> until configured.
            <a href="#setup-guide" class="btn btn-sm btn-outline-dark ms-2 mt-1">View Setup Guide</a>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill"></i>
        <strong>WhatsApp provider configured:</strong> <?php echo strtoupper(WA_PROVIDER); ?>. Notifications are active.
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <?php
        $statConfig = [
            'pending'   => ['color' => 'warning', 'icon' => 'clock',           'label' => 'Pending'],
            'sent'      => ['color' => 'primary',  'icon' => 'send-check',      'label' => 'Sent'],
            'delivered' => ['color' => 'success',  'icon' => 'check-all',       'label' => 'Delivered'],
            'failed'    => ['color' => 'danger',   'icon' => 'x-circle',        'label' => 'Failed'],
            'skipped'   => ['color' => 'secondary','icon' => 'skip-forward',    'label' => 'Skipped (No Config)'],
        ];
        foreach ($statConfig as $s => $sc): ?>
        <div class="col-md-2 col-6">
            <div class="card border-0 bg-<?php echo $sc['color']; ?> <?php echo in_array($s, ['pending','skipped']) ? 'text-dark' : 'text-white'; ?> rounded-3 text-center p-3">
                <i class="bi bi-<?php echo $sc['icon']; ?> fs-4 mb-1"></i>
                <div class="fw-bold fs-4"><?php echo $stats[$s] ?? 0; ?></div>
                <small><?php echo $sc['label']; ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="wa-logs.php" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search student name, phone, event..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach (array_keys($statConfig) as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="registration" <?php echo $typeFilter === 'registration' ? 'selected' : ''; ?>>Registration</option>
                        <option value="participation_cert" <?php echo $typeFilter === 'participation_cert' ? 'selected' : ''; ?>>Participation Cert</option>
                        <option value="achievement_cert" <?php echo $typeFilter === 'achievement_cert' ? 'selected' : ''; ?>>Achievement Cert</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="wa-logs.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-whatsapp fs-1"></i>
                    <p class="mt-3 fw-semibold">No WhatsApp notification logs found.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark small text-uppercase">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log):
                        $statusBadge = [
                            'pending'   => 'warning',
                            'sent'      => 'primary',
                            'delivered' => 'success',
                            'failed'    => 'danger',
                            'skipped'   => 'secondary',
                        ][$log['status']] ?? 'secondary';

                        $typeLabel = [
                            'registration'       => '📋 Registration',
                            'participation_cert' => '📜 Participation Cert',
                            'achievement_cert'   => '🏆 Achievement Cert',
                        ][$log['notification_type']] ?? $log['notification_type'];
                    ?>
                    <tr>
                        <td class="ps-4">
                            <strong><?php echo htmlspecialchars($log['student_name']); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars($log['college_id']); ?></div>
                        </td>
                        <td>
                            <span class="small"><?php echo htmlspecialchars($log['event_title'] ?? '–'); ?></span>
                        </td>
                        <td><span class="small"><?php echo $typeLabel; ?></span></td>
                        <td><code class="small"><?php echo htmlspecialchars($log['recipient_phone']); ?></code></td>
                        <td>
                            <span class="badge bg-<?php echo $statusBadge; ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?php echo $log['retry_count']; ?></td>
                        <td class="small text-muted"><?php echo $log['created_at'] ? date('M j, g:i A', strtotime($log['created_at'])) : '–'; ?></td>
                        <td class="text-end pe-4">
                            <?php if (in_array($log['status'], ['failed', 'pending']) && $log['retry_count'] < 5): ?>
                                <form method="POST" action="wa-logs.php" class="d-inline">
                                    <input type="hidden" name="action" value="retry">
                                    <input type="hidden" name="notification_id" value="<?php echo $log['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill"
                                            onclick="return confirm('Retry sending this WhatsApp notification?')">
                                        <i class="bi bi-arrow-clockwise"></i> Retry
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                <small class="text-muted">Showing <?php echo count($logs); ?> of <?php echo $totalRows; ?> notifications</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $p; ?>&status=<?php echo urlencode($statusFilter); ?>&type=<?php echo urlencode($typeFilter); ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- Setup Guide -->
    <div class="card border-0 shadow-sm rounded-4 mt-4" id="setup-guide">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-1"><i class="bi bi-gear me-2 text-primary"></i>WhatsApp Setup Guide</h5>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="fw-bold">Option A: Twilio Sandbox (Easiest – For Testing)</h6>
                    <ol class="small">
                        <li>Create a free account at <a href="https://www.twilio.com/try-twilio" target="_blank">twilio.com</a></li>
                        <li>Go to Messaging → Try It Out → Send a WhatsApp message</li>
                        <li>Join the sandbox by sending the sandbox code from your phone</li>
                        <li>Copy your Account SID and Auth Token from Console</li>
                        <li>In <code>config/whatsapp.php</code>, set:<br>
                            <code>WA_PROVIDER = 'twilio'</code><br>
                            <code>TWILIO_ACCOUNT_SID = 'ACxxxxxxxx'</code><br>
                            <code>TWILIO_AUTH_TOKEN = 'your_token'</code>
                        </li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold">Option B: Meta Cloud API (Production)</h6>
                    <ol class="small">
                        <li>Create a Meta Business App at <a href="https://developers.facebook.com" target="_blank">developers.facebook.com</a></li>
                        <li>Add WhatsApp product to your app</li>
                        <li>Get a Permanent Access Token and Phone Number ID</li>
                        <li>Add approved message templates for notifications</li>
                        <li>In <code>config/whatsapp.php</code>, set:<br>
                            <code>WA_PROVIDER = 'meta'</code><br>
                            <code>META_ACCESS_TOKEN = 'your_token'</code><br>
                            <code>META_PHONE_NUMBER_ID = 'your_id'</code>
                        </li>
                    </ol>
                </div>
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <strong>Student Consent:</strong> Students must enable WhatsApp notifications in their profile.
                        The <code>whatsapp_consent</code> field in the users table controls this.
                        Only students with <code>whatsapp_consent = 1</code> will receive WhatsApp messages.
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
