<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/certificate-service.php';

requireLogin();

$user = getLoggedInUser();
$db   = getDB();
$certSvc = new CertificateService();

// Fetch student registrations JOIN events
$stmt = $db->prepare("
    SELECT r.*, e.title, e.category, e.event_date, e.event_time, e.venue, e.organizer, e.image, e.status as event_status
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    WHERE r.user_id = ?
    ORDER BY e.event_date ASC
");
$stmt->execute([$user['id']]);
$registrations = $stmt->fetchAll();

// Stats
$totalRegs      = count($registrations);
$checkedInCount = 0;
$certCount      = 0;
foreach ($registrations as $r) {
    if ($r['checkin_status'] === 'checked_in') $checkedInCount++;
}
$pendingCount = $totalRegs - $checkedInCount;

// Get all certificates for this student
$myCerts = $certSvc->getStudentCertificates($user['id']);
$certCount = count($myCerts);

// Auto-issue any eligible participation certificates not yet issued
foreach ($registrations as $reg) {
    if ($reg['checkin_status'] === 'checked_in' && strtotime($reg['event_date']) < strtotime('today')) {
        $certSvc->getOrCreateParticipationCert($user['id'], $reg['event_id']);
        $certSvc->getOrCreateAchievementCert($user['id'], $reg['event_id']);
    }
}

// Refresh certs after possible auto-issue
$myCerts   = $certSvc->getStudentCertificates($user['id']);
$certCount = count($myCerts);

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-primary px-3 py-1 rounded-pill mb-2">Student Dashboard</span>
                <h2 class="fw-bold mb-1">My Event Passes &amp; Certificates</h2>
                <p class="text-secondary small mb-0">
                    Welcome back, <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                    (<?php echo htmlspecialchars($user['college_id']); ?> – <?php echo htmlspecialchars($user['department']); ?>)
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end flex-wrap">
                <a href="profile.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-person me-1"></i> Edit Profile</a>
                <a href="events.php" class="btn btn-gradient-primary btn-sm rounded-pill"><i class="bi bi-plus-circle me-1"></i> Register New Event</a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Overview Stats Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-purple"><i class="bi bi-ticket-perforated"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $totalRegs; ?></h3>
                    <span class="text-muted small">Total Registered Events</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-emerald"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $checkedInCount; ?></h3>
                    <span class="text-muted small">Successfully Checked-In</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $pendingCount; ?></h3>
                    <span class="text-muted small">Pending Gate Check-Ins</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f093fb,#f5576c);border-radius:12px;width:48px;height:48px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.3rem;">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $certCount; ?></h3>
                    <span class="text-muted small">Certificates Earned</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4 border-bottom" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold px-4" id="passes-tab" data-bs-toggle="tab" data-bs-target="#passes-panel" type="button" role="tab">
                <i class="bi bi-qr-code me-2"></i>My Event Passes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold px-4" id="certs-tab" data-bs-toggle="tab" data-bs-target="#certs-panel" type="button" role="tab">
                <i class="bi bi-award me-2"></i>My Certificates
                <?php if ($certCount > 0): ?>
                    <span class="badge bg-success rounded-pill ms-1"><?php echo $certCount; ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="dashboardTabContent">

        <!-- ── TAB 1: EVENT PASSES ── -->
        <div class="tab-pane fade show active" id="passes-panel" role="tabpanel">
            <?php if (empty($registrations)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-ticket-detailed fs-1 text-muted"></i>
                        <h4 class="fw-bold mt-3">No event registrations found</h4>
                        <p class="text-muted">You haven't registered for any campus events yet.</p>
                        <a href="events.php" class="btn btn-gradient-primary rounded-pill px-4 mt-2">
                            <i class="bi bi-compass me-1"></i> Browse Upcoming Events
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($registrations as $reg):
                        $isCheckedIn    = ($reg['checkin_status'] === 'checked_in');
                        $modalId        = "qrModal_" . $reg['id'];
                        $ticketContentId = "ticketContent_" . $reg['id'];
                        $qrContainerId  = "qrCodeContainer_" . $reg['id'];
                        $certStatus     = $certSvc->getCertificateStatusForRegistration($user['id'], $reg['event_id']);
                        $eventOver      = strtotime($reg['event_date']) < strtotime('today');
                    ?>
                        <div class="col-lg-6">
                            <div class="ticket-card">
                                <div class="ticket-header d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-light text-dark fw-bold mb-2"><?php echo htmlspecialchars($reg['category']); ?></span>
                                        <h5 class="fw-bold mb-1 text-white"><?php echo htmlspecialchars($reg['title']); ?></h5>
                                        <p class="small mb-0 text-light opacity-75"><i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($reg['organizer']); ?></p>
                                    </div>
                                    <div>
                                        <?php if ($isCheckedIn): ?>
                                            <span class="badge bg-success border border-white px-3 py-2 rounded-pill">
                                                <i class="bi bi-check-circle-fill me-1"></i> Checked In
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark border border-white px-3 py-2 rounded-pill">
                                                <i class="bi bi-clock me-1"></i> Check-in Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="ticket-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="mb-2 small">
                                                <i class="bi bi-calendar3 text-primary me-2"></i>
                                                <strong>Date:</strong> <?php echo date('F j, Y', strtotime($reg['event_date'])); ?>
                                            </div>
                                            <div class="mb-2 small">
                                                <i class="bi bi-clock text-info me-2"></i>
                                                <strong>Time:</strong> <?php echo date('g:i A', strtotime($reg['event_time'])); ?>
                                            </div>
                                            <div class="mb-3 small">
                                                <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                                <strong>Venue:</strong> <?php echo htmlspecialchars($reg['venue']); ?>
                                                                                     <div class="small text-muted border-top pt-2">
                                                Reg Code: <code class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($reg['registration_code']); ?></code>
                                            </div>

                                            <?php if (($reg['registration_type'] ?? '') === 'team'): 
                                                $members = !empty($reg['team_members']) ? json_decode($reg['team_members'], true) : [];
                                            ?>
                                                <div class="mt-2 pt-2 border-top">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1 rounded-pill small mb-1">
                                                        <i class="bi bi-people-fill me-1"></i> Team: <?php echo htmlspecialchars($reg['team_name'] ?? ''); ?> (<?php echo intval($reg['team_size'] ?? 1); ?> Members)
                                                    </span>
                                                    <div class="small text-muted">
                                                        <strong>Leader:</strong> <?php echo htmlspecialchars($user['name']); ?>
                                                        <?php if (!empty($members)): ?>
                                                            <br><strong>Members:</strong> <?php 
                                                                $mNames = array_map(function($m) {
                                                                    return htmlspecialchars($m['name']) . ' (' . htmlspecialchars($m['college_id']) . ')';
                                                                }, $members);
                                                                echo implode(', ', $mNames);
                                                            ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($isCheckedIn && $reg['checkin_time']): ?>
                                                <div class="small text-success mt-1">
                                                    <i class="bi bi-clock-check me-1"></i> Checked in at: <?php echo date('g:i A, M j', strtotime($reg['checkin_time'])); ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Certificate Status -->
                                            <?php if ($eventOver): ?>
                                            <div class="mt-3 pt-2 border-top">
                                                <?php
                                                $badgeColors = [
                                                    'upcoming'        => 'info',
                                                    'not_attended'    => 'danger',
                                                    'achievement'     => 'warning',
                                                    'participation'   => 'success',
                                                    'results_pending' => 'warning',
                                                    'pending'         => 'secondary',
                                                ];
                                                $bc = $badgeColors[$certStatus['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $bc; ?> px-2 py-1 rounded-pill small">
                                                    <i class="bi bi-award me-1"></i> <?php echo htmlspecialchars($certStatus['label']); ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-5 text-center mt-3 mt-md-0 border-start-md">
                                            <button type="button" class="btn btn-gradient-primary w-100 shadow-sm mb-2"
                                                data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>"
                                                onclick="renderQRCode('<?php echo $qrContainerId; ?>', '<?php echo htmlspecialchars($reg['registration_code']); ?>')">
                                                <i class="bi bi-qr-code me-1"></i> View QR Code
                                            </button>

                                            <!-- Certificate Download Button -->
                                            <?php if (!empty($certStatus['cert'])): ?>
                                                <a href="certificate.php?id=<?php echo urlencode($certStatus['cert']['certificate_id']); ?>"
                                                    target="_blank"
                                                    class="btn w-100 shadow-sm mb-1
                                                        <?php echo $certStatus['status'] === 'achievement' ? 'btn-warning' : 'btn-success'; ?>">
                                                    <i class="bi bi-award-fill me-1"></i>
                                                    <?php echo $certStatus['status'] === 'achievement' ? 'Achievement Certificate' : 'Download Certificate'; ?>
                                                </a>
                                            <?php elseif ($eventOver && $isCheckedIn): ?>
                                                <button class="btn btn-outline-secondary w-100 shadow-sm mb-1" disabled>
                                                    <i class="bi bi-hourglass-split me-1"></i> Certificate Not Available Yet
                                                </button>
                                            <?php elseif ($eventOver && !$isCheckedIn): ?>
                                                <button class="btn btn-outline-danger w-100 shadow-sm mb-1" disabled>
                                                    <i class="bi bi-x-circle me-1"></i> Not Attended
                                                </button>
                                            <?php endif; ?>
                                            <span class="small text-muted d-block" style="font-size: 0.75rem;">Show at venue entrance</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- QR Modal -->
                        <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                    <div class="modal-header bg-dark text-white rounded-top-4">
                                        <h5 class="modal-title fw-bold" id="<?php echo $modalId; ?>Label">
                                            <i class="bi bi-ticket-perforated text-primary me-2"></i> Official Event QR Pass
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 text-center" id="<?php echo $ticketContentId; ?>">
                                        <div class="mb-3">
                                            <span class="badge bg-primary px-3 py-1 rounded-pill mb-1"><?php echo htmlspecialchars($reg['category']); ?></span>
                                            <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($reg['title']); ?></h4>
                                            <p class="text-muted small"><?php echo date('F j, Y', strtotime($reg['event_date'])); ?> &bull; <?php echo date('g:i A', strtotime($reg['event_time'])); ?></p>
                                        </div>
                                        <div class="qr-container mb-3">
                                            <div id="<?php echo $qrContainerId; ?>" class="d-flex justify-content-center align-items-center py-2"></div>
                                        </div>
                                        <div class="p-3 bg-light rounded-3 text-start mb-3 border">
                                            <div class="row g-2 small">
                                                <?php if (($reg['registration_type'] ?? '') === 'team'): ?>
                                                    <div class="col-12 pb-2 mb-2 border-bottom">
                                                        <span class="badge bg-primary px-2 py-1 rounded-pill me-1"><i class="bi bi-people-fill"></i> TEAM PASS</span>
                                                        <strong class="text-dark fs-6"><?php echo htmlspecialchars($reg['team_name'] ?? ''); ?></strong>
                                                        <span class="text-muted small">(<?php echo intval($reg['team_size'] ?? 1); ?> Members)</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="col-6"><strong>Student Name:</strong><br><?php echo htmlspecialchars($user['name']); ?> <?php echo (($reg['registration_type'] ?? '') === 'team') ? '<span class="badge bg-dark" style="font-size:0.65rem;">Leader</span>' : ''; ?></div>
                                                <div class="col-6"><strong>College ID:</strong><br><?php echo htmlspecialchars($user['college_id']); ?></div>
                                                <div class="col-6"><strong>Department:</strong><br><?php echo htmlspecialchars($user['department']); ?></div>
                                                <div class="col-6"><strong>Reg Code:</strong><br><code><?php echo htmlspecialchars($reg['registration_code']); ?></code></div>
                                                <div class="col-12 mt-2 pt-2 border-top"><strong>Venue:</strong> <?php echo htmlspecialchars($reg['venue']); ?></div>

                                                <?php if (($reg['registration_type'] ?? '') === 'team' && !empty($members)): ?>
                                                    <div class="col-12 mt-2 pt-2 border-top">
                                                        <strong>Team Members:</strong>
                                                        <div class="mt-1 d-flex flex-column gap-1">
                                                            <?php foreach ($members as $idx => $m): ?>
                                                                <div class="p-1 px-2 rounded bg-white border d-flex justify-content-between">
                                                                    <span><strong>#<?php echo $idx + 2; ?>:</strong> <?php echo htmlspecialchars($m['name']); ?></span>
                                                                    <span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($m['college_id']); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($isCheckedIn): ?>
                                            <div class="alert alert-success py-2 mb-0 small fw-bold">
                                                <i class="bi bi-check-circle-fill me-1"></i> Check-in Completed
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning py-2 mb-0 small">
                                                <i class="bi bi-info-circle me-1"></i> Present this QR code to the scanner at the gate.
                                            </div>
                                        <?php endif; ?>
                                    </div>    </div>
                                    <div class="modal-footer bg-light rounded-bottom-4 justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="printPassTicket('<?php echo $ticketContentId; ?>')">
                                            <i class="bi bi-printer me-1"></i> Print Pass Ticket
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── TAB 2: MY CERTIFICATES ── -->
        <div class="tab-pane fade" id="certs-panel" role="tabpanel">
            <?php if (empty($myCerts)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-award fs-1 text-muted"></i>
                        <h4 class="fw-bold mt-3">No Certificates Yet</h4>
                        <p class="text-muted mb-3">Certificates are issued after events end. You must attend and check-in to receive a participation certificate.</p>
                        <div class="row g-3 justify-content-center mt-2">
                            <?php foreach ($registrations as $reg):
                                $certStatus = $certSvc->getCertificateStatusForRegistration($user['id'], $reg['event_id']);
                                if ($certStatus['status'] === 'upcoming' || $certStatus['status'] === 'pending'): ?>
                                <div class="col-auto">
                                    <div class="badge bg-light text-dark border px-3 py-2">
                                        <i class="bi bi-calendar me-1"></i> <?php echo htmlspecialchars($reg['title']); ?> – <?php echo htmlspecialchars($certStatus['label']); ?>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($myCerts as $cert):
                        $isAchievement = $cert['cert_type'] === 'achievement';
                        $rankLabels    = [1 => '🥇 First Place', 2 => '🥈 Second Place', 3 => '🥉 Third Place'];
                    ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                            <div class="card-header py-3 px-4 <?php echo $isAchievement ? 'bg-warning' : 'bg-success'; ?> text-<?php echo $isAchievement ? 'dark' : 'white'; ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-<?php echo $isAchievement ? 'trophy-fill' : 'award-fill'; ?> fs-5"></i>
                                    <div>
                                        <strong><?php echo $isAchievement ? 'ACHIEVEMENT CERTIFICATE' : 'PARTICIPATION CERTIFICATE'; ?></strong>
                                        <?php if ($isAchievement && $cert['rank']): ?>
                                            <div class="small"><?php echo $rankLabels[$cert['rank']] ?? 'Rank ' . $cert['rank']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-4 py-3">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($cert['event_title']); ?></h6>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('F j, Y', strtotime($cert['event_date'])); ?>
                                </p>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-key me-1"></i> <?php echo htmlspecialchars($cert['certificate_id']); ?>
                                    </span>
                                    <span class="badge bg-light text-muted border">
                                        Issued: <?php echo date('M j, Y', strtotime($cert['issued_at'])); ?>
                                    </span>
                                </div>
                                <a href="certificate.php?id=<?php echo urlencode($cert['certificate_id']); ?>"
                                   target="_blank"
                                   class="btn <?php echo $isAchievement ? 'btn-warning' : 'btn-success'; ?> w-100 rounded-pill fw-bold">
                                    <i class="bi bi-download me-2"></i>
                                    <?php echo $isAchievement ? 'Download Achievement Certificate' : 'Download Participation Certificate'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Certificate status guide -->
                <div class="card border-0 bg-light rounded-4 mt-4 p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Certificate Status Guide</h6>
                    <div class="row g-2 small">
                        <div class="col-auto"><span class="badge bg-info">Event Upcoming</span> – Event hasn't happened yet</div>
                        <div class="col-auto"><span class="badge bg-secondary">Certificate Not Available Yet</span> – Awaiting admin issuance</div>
                        <div class="col-auto"><span class="badge bg-danger">Not Attended</span> – No check-in recorded</div>
                        <div class="col-auto"><span class="badge bg-success">Certificate Available</span> – Download ready</div>
                        <div class="col-auto"><span class="badge bg-warning text-dark">Achievement Certificate</span> – Winner certificate ready</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
