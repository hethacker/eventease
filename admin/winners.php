<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/certificate-service.php';
require_once __DIR__ . '/../includes/whatsapp-service.php';

requireAdmin();

$db      = getDB();
$certSvc = new CertificateService();
$waSvc   = new WhatsAppService();

$action  = $_GET['action'] ?? '';
$eventId = intval($_GET['event_id'] ?? 0);
$flash   = '';
$flashType = 'success';

// ─────────────────────────────────────────────────────────────────────────────
// Handle POST actions
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // Save event certificate settings
    if ($postAction === 'save_settings') {
        $eid = intval($_POST['event_id']);
        $certSvc->upsertEventSettings($eid, [
            'college_name'         => trim($_POST['college_name'] ?? 'EventEase College'),
            'organizer_name'       => trim($_POST['organizer_name'] ?? ''),
            'signatory_name'       => trim($_POST['signatory_name'] ?? ''),
            'signatory_title'      => trim($_POST['signatory_title'] ?? 'Event Coordinator'),
            'enable_participation' => isset($_POST['enable_participation']) ? 1 : 0,
            'enable_ranking'       => isset($_POST['enable_ranking']) ? 1 : 0,
        ]);
        $flash = 'Certificate settings saved successfully.';
        header("Location: winners.php?event_id=$eid&action=manage&flash=" . urlencode($flash));
        exit;
    }

    // Assign / update ranks
    if ($postAction === 'save_rankings') {
        $eid       = intval($_POST['event_id']);
        $confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === '1';

        if (!$confirmed) {
            header("Location: winners.php?event_id=$eid&action=manage&err=confirm_required");
            exit;
        }

        $rankUsers = [
            1 => intval($_POST['rank1_user'] ?? 0),
            2 => intval($_POST['rank2_user'] ?? 0),
            3 => intval($_POST['rank3_user'] ?? 0),
        ];

        // Validate uniqueness
        $assigned = [];
        $errors   = [];
        foreach ($rankUsers as $rank => $uid) {
            if ($uid === 0) continue;
            if (in_array($uid, $assigned)) {
                $errors[] = "Same student cannot receive multiple ranks.";
                break;
            }
            $assigned[] = $uid;
        }

        if (empty($errors)) {
            $db->beginTransaction();
            try {
                foreach ($rankUsers as $rank => $uid) {
                    // Remove existing rank for this slot
                    $db->prepare("DELETE FROM event_rankings WHERE event_id = ? AND `rank` = ?")->execute([$eid, $rank]);

                    if ($uid > 0) {
                        // Get registration
                        $regStmt = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ? AND checkin_status = 'checked_in' LIMIT 1");
                        $regStmt->execute([$uid, $eid]);
                        $reg = $regStmt->fetch();

                        if (!$reg) {
                            $errors[] = "Rank $rank student has no valid check-in for this event.";
                            continue;
                        }

                        // Remove any existing rank for this user in this event
                        $db->prepare("DELETE FROM event_rankings WHERE event_id = ? AND user_id = ?")->execute([$eid, $uid]);

                        $db->prepare("
                            INSERT INTO event_rankings (event_id, user_id, registration_id, `rank`, assigned_by, published)
                            VALUES (?, ?, ?, ?, ?, 0)
                        ")->execute([$eid, $uid, $reg['id'], $rank, $_SESSION['user_id']]);
                    }
                }
                $db->commit();
                $flash = 'Rankings saved as draft. Use "Publish Results" to make them official.';
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        if ($errors) {
            $flash = implode(' | ', $errors);
            $flashType = 'danger';
        }
        header("Location: winners.php?event_id=$eid&action=manage&flash=" . urlencode($flash) . "&ftype=$flashType");
        exit;
    }

    // Publish rankings
    if ($postAction === 'publish_rankings') {
        $eid = intval($_POST['event_id']);
        $db->prepare("UPDATE event_rankings SET published = 1 WHERE event_id = ?")->execute([$eid]);
        $certSvc->upsertEventSettings($eid, ['results_published' => 1]);

        // Issue achievement certs and notify
        $rankStmt = $db->prepare("SELECT er.*, u.id as uid FROM event_rankings er JOIN users u ON er.user_id = u.id WHERE er.event_id = ? AND er.published = 1");
        $rankStmt->execute([$eid]);
        $rankedStudents = $rankStmt->fetchAll();

        $settings = $certSvc->getEventSettings($eid);
        foreach ($rankedStudents as $rs) {
            $certRecord = $certSvc->issueAchievementCert($rs['uid'], $eid, $rs['id'], $rs['rank'], $settings);
            if ($certRecord) {
                $waSvc->notifyAchievementCert($rs['uid'], $eid, $certRecord['id'], $rs['rank']);
            }
        }

        $flash = 'Rankings published! Achievement certificates issued and WhatsApp notifications dispatched.';
        header("Location: winners.php?event_id=$eid&action=manage&flash=" . urlencode($flash));
        exit;
    }

    // Bulk issue participation certs
    if ($postAction === 'issue_participation_certs') {
        $eid      = intval($_POST['event_id']);
        $settings = [
            'college_name'    => trim($_POST['college_name'] ?? 'EventEase College'),
            'organizer_name'  => trim($_POST['organizer_name'] ?? ''),
            'signatory_name'  => trim($_POST['signatory_name'] ?? ''),
            'signatory_title' => trim($_POST['signatory_title'] ?? 'Event Coordinator'),
        ];
        $count = $certSvc->bulkIssueParticipationCerts($eid, $settings);

        // Send WhatsApp notifications
        $attendeesStmt = $db->prepare("SELECT r.user_id, r.event_id FROM registrations r WHERE r.event_id = ? AND r.checkin_status = 'checked_in'");
        $attendeesStmt->execute([$eid]);
        $attendees = $attendeesStmt->fetchAll();

        foreach ($attendees as $att) {
            $certRecord = $certSvc->getCertificate($att['user_id'], $att['event_id'], 'participation');
            if ($certRecord) {
                $waSvc->notifyParticipationCert($att['user_id'], $att['event_id'], $certRecord['id']);
            }
        }

        $flash = "$count participation certificate(s) issued. WhatsApp notifications dispatched.";
        header("Location: winners.php?event_id=$eid&action=manage&flash=" . urlencode($flash));
        exit;
    }
}

// Flash from redirect
if (!$flash && isset($_GET['flash'])) {
    $flash     = $_GET['flash'];
    $flashType = $_GET['ftype'] ?? 'success';
}
$errMsg = $_GET['err'] ?? '';

// Fetch all events for the selector
$allEvents = $db->query("SELECT id, title, event_date, status, category FROM events ORDER BY event_date DESC")->fetchAll();

// If event selected, load its data
$selectedEvent   = null;
$participants    = [];
$currentRankings = [];
$eventSettings   = [];

if ($eventId > 0) {
    $evStmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $evStmt->execute([$eventId]);
    $selectedEvent = $evStmt->fetch();

    if ($selectedEvent) {
        $eventSettings = $certSvc->getEventSettings($eventId);

        // Eligible participants (checked-in students)
        $search     = trim($_GET['search'] ?? '');
        $searchSql  = '';
        $searchBinds = [$eventId];

        if ($search) {
            $searchSql    = "AND (u.name LIKE ? OR u.college_id LIKE ? OR r.registration_code LIKE ? OR u.department LIKE ?)";
            $searchBinds  = array_merge($searchBinds, ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }

        $participantsStmt = $db->prepare("
            SELECT r.id as reg_id, r.registration_code, r.checkin_status, r.checkin_time,
                   u.id as user_id, u.name, u.email, u.college_id, u.department, u.year
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            WHERE r.event_id = ? AND r.checkin_status = 'checked_in'
            $searchSql
            ORDER BY u.name ASC
        ");
        $participantsStmt->execute($searchBinds);
        $participants = $participantsStmt->fetchAll();

        // Current rankings
        $rankingsStmt = $db->prepare("
            SELECT er.*, u.name as student_name, u.college_id
            FROM event_rankings er
            JOIN users u ON er.user_id = u.id
            WHERE er.event_id = ?
            ORDER BY er.rank ASC
        ");
        $rankingsStmt->execute([$eventId]);
        $currentRankings = $rankingsStmt->fetchAll();

        // Index rankings by rank
        $rankBySlot = [];
        foreach ($currentRankings as $cr) {
            $rankBySlot[$cr['rank']] = $cr;
        }
    }
}

// Summary stats
$totalPCerts = $db->query("SELECT COUNT(*) FROM certificates WHERE cert_type = 'participation'")->fetchColumn();
$totalACerts = $db->query("SELECT COUNT(*) FROM certificates WHERE cert_type = 'achievement'")->fetchColumn();
$eventsAwaitingRanking = $db->query("
    SELECT COUNT(DISTINCT e.id)
    FROM events e
    WHERE e.event_date < CURDATE()
    AND e.id NOT IN (SELECT DISTINCT event_id FROM event_rankings WHERE published = 1)
")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 fw-semibold">
                    <i class="bi bi-trophy me-1"></i> Results & Certificates
                </span>
                <h2 class="fw-bold mb-1">Winners & Certificate Management</h2>
                <p class="text-secondary small mb-0">Assign event rankings, publish results, and issue digital certificates to participants.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                <a href="wa-logs.php" class="btn btn-outline-success btn-sm rounded-pill"><i class="bi bi-whatsapp me-1"></i> WA Logs</a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show mb-4" role="alert">
            <?php echo htmlspecialchars($flash); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errMsg === 'confirm_required'): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            Please check the confirmation box before saving rankings.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-emerald"><i class="bi bi-award-fill"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $totalPCerts; ?></h3>
                    <span class="text-muted small">Participation Certs Issued</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:linear-gradient(135deg,#B8860B,#FFD700);border-radius:12px;width:48px;height:48px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $totalACerts; ?></h3>
                    <span class="text-muted small">Achievement Certs Issued</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-amber"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $eventsAwaitingRanking; ?></h3>
                    <span class="text-muted small">Events Awaiting Rankings</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <a href="cert-records.php" class="text-decoration-none">
                <div class="stat-card d-flex align-items-center gap-3 h-100">
                    <div class="stat-icon icon-cyan"><i class="bi bi-file-earmark-text"></i></div>
                    <div>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $totalPCerts + $totalACerts; ?></h3>
                        <span class="text-muted small">View All Records →</span>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Event Selector -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-1"><i class="bi bi-calendar-event me-2 text-primary"></i>Select Event</h5>
            <p class="text-muted small mb-0">Choose an event to manage winners and certificates.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <form method="GET" action="winners.php" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="manage">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Select Event</label>
                    <select name="event_id" class="form-select" required onchange="this.form.submit()">
                        <option value="">-- Choose an event --</option>
                        <?php foreach ($allEvents as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo ($ev['id'] == $eventId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['title']); ?>
                                (<?php echo date('M j, Y', strtotime($ev['event_date'])); ?>)
                                <?php echo strtotime($ev['event_date']) < strtotime('today') ? '– COMPLETED' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary rounded-pill w-100">
                        <i class="bi bi-arrow-right me-1"></i> Manage This Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedEvent): ?>
    <!-- Event Management Panel -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-dark text-white pt-4 px-4 pb-3 rounded-top-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($selectedEvent['title']); ?></h5>
                    <span class="text-light opacity-75 small">
                        <i class="bi bi-calendar3 me-1"></i><?php echo date('F j, Y', strtotime($selectedEvent['event_date'])); ?>
                        &bull; <?php echo htmlspecialchars($selectedEvent['category']); ?>
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($eventSettings['results_published']): ?>
                        <span class="badge bg-success px-3 py-2">✓ Rankings Published</span>
                    <?php else: ?>
                        <span class="badge bg-secondary px-3 py-2">Draft Rankings</span>
                    <?php endif; ?>
                    <?php if ($eventSettings['certs_issued']): ?>
                        <span class="badge bg-primary px-3 py-2">✓ Certs Issued</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark px-3 py-2">Certs Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-body px-4 py-4">
            <ul class="nav nav-tabs mb-4" id="eventMgmtTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-rankings">
                        <i class="bi bi-trophy me-2"></i>Assign Rankings
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-certs">
                        <i class="bi bi-award me-2"></i>Issue Certificates
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-participants">
                        <i class="bi bi-people me-2"></i>Participants (<?php echo count($participants); ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-settings">
                        <i class="bi bi-gear me-2"></i>Settings
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ── RANKINGS TAB ── -->
                <div class="tab-pane fade show active" id="tab-rankings">
                    <?php if (!$eventSettings['enable_ranking']): ?>
                        <div class="alert alert-info">Rankings are disabled for this event (e.g. workshop/seminar). Enable in Settings tab.</div>
                    <?php else: ?>

                    <?php if (count($participants) === 0): ?>
                        <div class="alert alert-warning">No checked-in participants yet. Rankings can only be assigned to students who checked in.</div>
                    <?php else: ?>

                    <form method="POST" action="winners.php" id="rankingsForm">
                        <input type="hidden" name="action" value="save_rankings">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

                        <div class="row g-4 mb-4">
                            <?php
                            $rankConfig = [
                                1 => ['label' => '🥇 First Place',  'color' => 'warning', 'icon' => 'trophy-fill'],
                                2 => ['label' => '🥈 Second Place', 'color' => 'secondary', 'icon' => 'award-fill'],
                                3 => ['label' => '🥉 Third Place',  'color' => 'danger', 'icon' => 'award'],
                            ];
                            foreach ($rankConfig as $rankNum => $rc):
                                $currentWinner = $rankBySlot[$rankNum] ?? null;
                            ?>
                            <div class="col-md-4">
                                <div class="card border-<?php echo $rc['color']; ?> border-2 rounded-4 h-100">
                                    <div class="card-header bg-<?php echo $rc['color']; ?> <?php echo $rc['color'] === 'warning' ? 'text-dark' : 'text-white'; ?> py-2 px-3 fw-bold d-flex align-items-center gap-2">
                                        <i class="bi bi-<?php echo $rc['icon']; ?>"></i>
                                        <?php echo $rc['label']; ?>
                                    </div>
                                    <div class="card-body p-3">
                                        <?php if ($currentWinner): ?>
                                            <div class="alert alert-<?php echo $rc['color']; ?> py-2 px-3 small mb-2">
                                                <strong>Current:</strong> <?php echo htmlspecialchars($currentWinner['student_name']); ?>
                                                (<?php echo htmlspecialchars($currentWinner['college_id']); ?>)
                                                <?php echo $currentWinner['published'] ? '<span class="badge bg-success ms-1">Published</span>' : '<span class="badge bg-secondary ms-1">Draft</span>'; ?>
                                            </div>
                                        <?php endif; ?>
                                        <label class="form-label fw-semibold small">Assign Student</label>
                                        <select name="rank<?php echo $rankNum; ?>_user" class="form-select form-select-sm rank-select" data-rank="<?php echo $rankNum; ?>">
                                            <option value="0">-- None / Not Applicable --</option>
                                            <?php foreach ($participants as $p): ?>
                                                <option value="<?php echo $p['user_id']; ?>"
                                                    <?php echo ($currentWinner && $currentWinner['user_id'] == $p['user_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($p['name']); ?>
                                                    (<?php echo htmlspecialchars($p['college_id']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="alert alert-warning d-flex align-items-start gap-3">
                            <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
                            <div>
                                <strong>Important:</strong> Rankings are saved as draft until you click "Publish Results".
                                Publish action is irreversible without admin correction. Please verify selections carefully.
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirmRankings" name="confirmed" value="1">
                            <label class="form-check-label fw-semibold" for="confirmRankings">
                                I confirm these rankings are correct and wish to save them.
                            </label>
                        </div>

                        <div class="d-flex gap-3 flex-wrap">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-floppy me-2"></i> Save Draft Rankings
                            </button>

                            <?php if (!empty($currentRankings) && !$eventSettings['results_published']): ?>
                            <button type="button" class="btn btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#publishModal">
                                <i class="bi bi-broadcast me-2"></i> Publish Results & Issue Achievement Certs
                            </button>
                            <?php elseif ($eventSettings['results_published']): ?>
                            <span class="badge bg-success align-self-center px-3 py-2">
                                <i class="bi bi-check-circle-fill me-1"></i> Results Already Published
                            </span>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ── CERTIFICATES TAB ── -->
                <div class="tab-pane fade" id="tab-certs">
                    <?php
                    $eventOver = strtotime($selectedEvent['event_date']) < strtotime('today');
                    $checkedInCount = count($participants);
                    $existingPartCertCount = $db->prepare("SELECT COUNT(*) FROM certificates WHERE event_id = ? AND cert_type = 'participation'")->execute([$eventId]) ? 0 : 0;
                    $pcStmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE event_id = ? AND cert_type = 'participation'");
                    $pcStmt->execute([$eventId]);
                    $existingPartCertCount = $pcStmt->fetchColumn();
                    ?>

                    <?php if (!$eventOver): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-calendar-x me-2"></i>
                            This event has not ended yet (event date: <?php echo date('F j, Y', strtotime($selectedEvent['event_date'])); ?>).
                            Certificates can only be issued after the event ends.
                        </div>
                    <?php else: ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-0 bg-light rounded-3 text-center p-3">
                                <div class="fw-bold fs-3 text-primary"><?php echo $checkedInCount; ?></div>
                                <small class="text-muted">Eligible Attendees</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 bg-light rounded-3 text-center p-3">
                                <div class="fw-bold fs-3 text-success"><?php echo $existingPartCertCount; ?></div>
                                <small class="text-muted">Participation Certs Already Issued</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 bg-light rounded-3 text-center p-3">
                                <div class="fw-bold fs-3 text-warning"><?php echo $totalACerts; ?></div>
                                <small class="text-muted">Achievement Certs Issued</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 bg-light rounded-3 text-center p-3">
                                <div class="fw-bold fs-3"><?php echo $checkedInCount - $existingPartCertCount; ?></div>
                                <small class="text-muted">Pending Issuance</small>
                            </div>
                        </div>
                    </div>

                    <?php $settings = $certSvc->getEventSettings($eventId); ?>

                    <form method="POST" action="winners.php">
                        <input type="hidden" name="action" value="issue_participation_certs">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">College / Institution Name</label>
                                <input type="text" name="college_name" class="form-control" value="<?php echo htmlspecialchars($settings['college_name'] ?? 'EventEase College'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Organizer Name</label>
                                <input type="text" name="organizer_name" class="form-control" value="<?php echo htmlspecialchars($settings['organizer_name'] ?? ''); ?>" placeholder="e.g. Department of Computer Science">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Signatory Name</label>
                                <input type="text" name="signatory_name" class="form-control" value="<?php echo htmlspecialchars($settings['signatory_name'] ?? ''); ?>" placeholder="e.g. Prof. Ramesh Sharma">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Signatory Title</label>
                                <input type="text" name="signatory_title" class="form-control" value="<?php echo htmlspecialchars($settings['signatory_title'] ?? 'Event Coordinator'); ?>">
                            </div>
                        </div>

                        <?php if ($checkedInCount === 0): ?>
                            <div class="alert alert-warning">No checked-in attendees found. Students must check in to receive certificates.</div>
                        <?php elseif ($eventSettings['certs_issued']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Participation certificates have already been issued for this event.
                                Clicking below will issue certificates only for any new attendees not yet covered.
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-success rounded-pill px-5"
                            onclick="return confirm('Issue participation certificates to all <?php echo $checkedInCount; ?> checked-in attendees? This will also send WhatsApp notifications.');">
                            <i class="bi bi-award me-2"></i>
                            Issue Participation Certificates (<?php echo $checkedInCount - $existingPartCertCount; ?> pending)
                        </button>
                    </form>

                    <?php endif; ?>
                </div>

                <!-- ── PARTICIPANTS TAB ── -->
                <div class="tab-pane fade" id="tab-participants">
                    <form method="GET" action="winners.php" class="mb-3">
                        <input type="hidden" name="action" value="manage">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, college ID, reg code, department..."
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <?php if (!empty($_GET['search'])): ?>
                                <a href="winners.php?action=manage&event_id=<?php echo $eventId; ?>" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if (empty($participants)): ?>
                        <div class="alert alert-info">No checked-in participants found for this event.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark small text-uppercase">
                                <tr>
                                    <th>Student</th>
                                    <th>College ID</th>
                                    <th>Department / Year</th>
                                    <th>Check-In Time</th>
                                    <th>Rank</th>
                                    <th>Certs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $p):
                                    $pRank = null;
                                    foreach ($currentRankings as $cr) {
                                        if ($cr['user_id'] == $p['user_id']) { $pRank = $cr; break; }
                                    }
                                    $partCert = $certSvc->getCertificate($p['user_id'], $eventId, 'participation');
                                    $achCert  = $certSvc->getCertificate($p['user_id'], $eventId, 'achievement');
                                    $rankEmojis = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                        <div class="small text-muted"><?php echo htmlspecialchars($p['registration_code']); ?></div>
                                    </td>
                                    <td><span class="badge bg-dark"><?php echo htmlspecialchars($p['college_id']); ?></span></td>
                                    <td>
                                        <div class="small fw-semibold"><?php echo htmlspecialchars($p['department']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($p['year']); ?></small>
                                    </td>
                                    <td><small class="text-muted"><?php echo $p['checkin_time'] ? date('M j, g:i A', strtotime($p['checkin_time'])) : '–'; ?></small></td>
                                    <td>
                                        <?php if ($pRank): ?>
                                            <span class="badge bg-<?php echo ['','warning','secondary','danger'][$pRank['rank']] ?? 'secondary'; ?>">
                                                <?php echo $rankEmojis[$pRank['rank']] ?? ''; ?> Rank <?php echo $pRank['rank']; ?>
                                                <?php echo $pRank['published'] ? '(Published)' : '(Draft)'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($achCert): ?>
                                            <a href="../certificate.php?id=<?php echo urlencode($achCert['certificate_id']); ?>" target="_blank" class="badge bg-warning text-dark text-decoration-none">
                                                🏆 Achievement
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($partCert): ?>
                                            <a href="../certificate.php?id=<?php echo urlencode($partCert['certificate_id']); ?>" target="_blank" class="badge bg-success text-decoration-none">
                                                📜 Participation
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$partCert && !$achCert): ?>
                                            <span class="text-muted small">No certs yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── SETTINGS TAB ── -->
                <div class="tab-pane fade" id="tab-settings">
                    <form method="POST" action="winners.php">
                        <input type="hidden" name="action" value="save_settings">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">College / Institution Name *</label>
                                <input type="text" name="college_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($eventSettings['college_name'] ?? 'EventEase College'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Organizer Name</label>
                                <input type="text" name="organizer_name" class="form-control"
                                       value="<?php echo htmlspecialchars($eventSettings['organizer_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Authorized Signatory Name</label>
                                <input type="text" name="signatory_name" class="form-control"
                                       value="<?php echo htmlspecialchars($eventSettings['signatory_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Signatory Title / Designation</label>
                                <input type="text" name="signatory_title" class="form-control"
                                       value="<?php echo htmlspecialchars($eventSettings['signatory_title'] ?? 'Event Coordinator'); ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="enable_participation"
                                           id="enableParticipation" value="1"
                                           <?php echo ($eventSettings['enable_participation'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="enableParticipation">
                                        Enable Participation Certificates for this event
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_ranking"
                                           id="enableRanking" value="1"
                                           <?php echo ($eventSettings['enable_ranking'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="enableRanking">
                                        Enable Rankings / Achievement Certificates
                                        <small class="text-muted fw-normal">(disable for workshops, seminars)</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-5">
                                <i class="bi bi-floppy me-2"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>

            </div><!-- /.tab-content -->
        </div><!-- /.card-body -->
    </div><!-- /.card -->
    <?php endif; ?>

</div><!-- /.container -->

<!-- Publish Confirmation Modal -->
<div class="modal fade" id="publishModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-success text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-broadcast me-2"></i>Confirm: Publish Rankings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>This action will:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Mark all saved rankings as <strong>Published</strong></li>
                        <li>Issue <strong>Achievement Certificates</strong> to ranked students</li>
                        <li>Send <strong>WhatsApp notifications</strong> to ranked students (if configured)</li>
                    </ul>
                </div>
                <p class="text-muted small">Rankings can still be corrected and certificates regenerated after publishing by saving new draft rankings and publishing again.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="winners.php" class="d-inline">
                    <input type="hidden" name="action" value="publish_rankings">
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                    <button type="submit" class="btn btn-success rounded-pill px-4">
                        <i class="bi bi-check-circle me-2"></i> Yes, Publish Rankings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Prevent same student from being assigned multiple ranks
document.addEventListener('DOMContentLoaded', function() {
    const rankSelects = document.querySelectorAll('.rank-select');
    rankSelects.forEach(function(sel) {
        sel.addEventListener('change', function() {
            const selectedVal = this.value;
            if (selectedVal === '0') return;
            rankSelects.forEach(function(other) {
                if (other !== sel && other.value === selectedVal) {
                    alert('This student is already assigned a rank. Please choose a different student.');
                    sel.value = '0';
                }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
