<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();

// Handle AJAX POST Verification Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_checkin') {
    header('Content-Type: application/json');
    $code = trim($_POST['registration_code'] ?? '');

    if (empty($code)) {
        echo json_encode(['success' => false, 'type' => 'invalid', 'message' => 'Please provide a valid registration code.']);
        exit;
    }

    // Lookup registration record
    $stmt = $db->prepare("
        SELECT r.*, u.name as student_name, u.email as student_email, u.phone, u.college_id, u.department, u.year, e.title as event_title, e.venue, e.event_date
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        JOIN events e ON r.event_id = e.id
        WHERE r.registration_code = ? OR r.qr_code = ?
    ");
    $stmt->execute([$code, $code]);
    $reg = $stmt->fetch();

    if (!$reg) {
        echo json_encode([
            'success' => false,
            'type' => 'invalid',
            'message' => 'INVALID QR CODE: No matching registration pass found for code [' . htmlspecialchars($code) . '].'
        ]);
        exit;
    }

    // Check if already checked in
    if ($reg['checkin_status'] === 'checked_in') {
        echo json_encode([
            'success' => false,
            'type' => 'duplicate',
            'message' => 'ALREADY CHECKED IN: This student was previously checked in on ' . date('F j, Y, g:i:s A', strtotime($reg['checkin_time'])),
            'data' => [
                'student_name' => $reg['student_name'],
                'college_id'   => $reg['college_id'],
                'department'   => $reg['department'],
                'event_title'  => $reg['event_title'],
                'checkin_time' => date('g:i:s A', strtotime($reg['checkin_time'])),
                'reg_code'     => $reg['registration_code']
            ]
        ]);
        exit;
    }

    // Update record to Checked In
    $updateStmt = $db->prepare("UPDATE registrations SET checkin_status = 'checked_in', checkin_time = NOW() WHERE id = ?");
    $updateStmt->execute([$reg['id']]);

    $checkinTimeFormatted = date('g:i:s A, F j, Y');
    $isTeam = ($reg['registration_type'] ?? '') === 'team';
    $teamMembers = $isTeam && !empty($reg['team_members']) ? json_decode($reg['team_members'], true) : [];

    echo json_encode([
        'success' => true,
        'type' => 'success',
        'message' => 'CHECK-IN SUCCESSFUL!',
        'data' => [
            'student_name' => $reg['student_name'],
            'college_id'   => $reg['college_id'],
            'department'   => $reg['department'],
            'year'         => $reg['year'],
            'event_title'  => $reg['event_title'],
            'venue'        => $reg['venue'],
            'checkin_time' => $checkinTimeFormatted,
            'reg_code'     => $reg['registration_code'],
            'is_team'      => $isTeam,
            'team_name'    => $reg['team_name'] ?? '',
            'team_size'    => intval($reg['team_size'] ?? 1),
            'team_members' => $teamMembers ?: []
        ]
    ]);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2 font-semibold"><i class="bi bi-qr-code-scan me-1"></i> Live Scanner</span>
                <h2 class="fw-bold mb-1">Gate QR Code Check-In</h2>
                <p class="text-secondary small mb-0">Scan student passes using webcam or manually enter registration codes.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="students.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-people-fill me-1"></i> Registered Students</a>
                <a href="attendance.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-bar-chart me-1"></i> Attendance Reports</a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4 justify-content-center">
        <!-- Left Col: Camera & Manual Entry -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-header bg-dark text-white p-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-camera me-2 text-primary"></i> Live Camera QR Scanner</h5>
                    <span class="badge bg-success rounded-pill" id="scannerStatusBadge">Camera Ready</span>
                </div>
                <div class="card-body p-4 text-center">

                    <!-- Scanner Container -->
                    <div id="qr-reader" class="scanner-viewport shadow-sm bg-light mb-4"></div>

                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <button type="button" id="btnStartCamera" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-play-circle me-1"></i> Start Camera
                        </button>
                        <button type="button" id="btnStopCamera" class="btn btn-outline-secondary btn-sm rounded-pill px-3" disabled>
                            <i class="bi bi-stop-circle me-1"></i> Stop Camera
                        </button>
                    </div>

                    <hr class="my-4">

                    <!-- Manual Input Box -->
                    <div class="text-start">
                        <label for="manualCode" class="form-label fw-bold">Or Enter Registration Code Manually</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white"><i class="bi bi-qr-code text-primary"></i></span>
                            <input type="text" id="manualCode" class="form-control font-monospace text-uppercase" placeholder="e.g. EE-2026-T7A91B" autocomplete="off">
                            <button type="button" id="btnSubmitManual" class="btn btn-gradient-primary px-4 fw-bold">Verify Code</button>
                        </div>
                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i> Press Enter or click Verify Code to check-in student.</small>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right Col: Real-time Check-In Result Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 h-100" id="resultCard">
                <div class="card-header bg-secondary text-white p-4 rounded-top-4" id="resultCardHeader">
                    <h5 class="fw-bold mb-0"><i class="bi bi-shield-check me-2"></i> Verification Feedback</h5>
                </div>
                <div class="card-body p-4 text-center d-flex flex-column justify-content-center align-items-center" id="resultCardBody">
                    
                    <div id="initialStateDisplay" class="py-5">
                        <i class="bi bi-qr-code-scan display-1 text-muted opacity-50 mb-3 d-block"></i>
                        <h4 class="fw-bold text-secondary">Awaiting Scanner Input</h4>
                        <p class="text-muted max-w-400">Point student QR code at the camera or enter the registration code in the manual input box.</p>
                    </div>

                    <!-- Dynamic Verification Feedback Container -->
                    <div id="verificationResultDisplay" class="w-100 text-start d-none">
                        <!-- Populated by JS -->
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let html5QrCode = null;
    let isProcessing = false;

    const manualInput = document.getElementById('manualCode');
    const submitBtn = document.getElementById('btnSubmitManual');
    const startCamBtn = document.getElementById('btnStartCamera');
    const stopCamBtn = document.getElementById('btnStopCamera');
    const scannerStatus = document.getElementById('scannerStatusBadge');

    // Submit Manual Code
    submitBtn.addEventListener('click', function () {
        const code = manualInput.value.trim();
        if (code) {
            processCheckInCode(code);
        }
    });

    manualInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const code = manualInput.value.trim();
            if (code) {
                processCheckInCode(code);
            }
        }
    });

    // Handle AJAX API Call
    function processCheckInCode(code) {
        if (isProcessing) return;
        isProcessing = true;

        const resultCardHeader = document.getElementById('resultCardHeader');
        const initialState = document.getElementById('initialStateDisplay');
        const resultDisplay = document.getElementById('verificationResultDisplay');

        initialState.classList.add('d-none');
        resultDisplay.classList.remove('d-none');
        resultDisplay.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary mb-3"></div><p class="fw-bold">Validating Pass Code...</p></div>`;

        const formData = new FormData();
        formData.append('action', 'process_checkin');
        formData.append('registration_code', code);

        fetch('checkin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            isProcessing = false;
            manualInput.value = '';

            if (data.success) {
                // SUCCESS STATE
                resultCardHeader.className = 'card-header bg-success text-white p-4 rounded-top-4';
                resultCardHeader.innerHTML = `<h5 class="fw-bold mb-0"><i class="bi bi-check-circle-fill me-2"></i> CHECK-IN SUCCESSFUL</h5>`;

                let teamHtml = '';
                if (data.data.is_team) {
                    let memberItems = '';
                    if (data.data.team_members && data.data.team_members.length > 0) {
                        memberItems = data.data.team_members.map((m, idx) => `
                            <div class="p-1 px-2 mb-1 bg-white rounded border small d-flex justify-content-between">
                                <span><strong>#${idx + 2}:</strong> ${escapeHtml(m.name)}</span>
                                <span class="badge bg-secondary-subtle text-secondary">${escapeHtml(m.college_id)}</span>
                            </div>
                        `).join('');
                    }
                    teamHtml = `
                        <div class="p-2 mb-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-primary"><i class="bi bi-people-fill me-1"></i> Team: ${escapeHtml(data.data.team_name)}</strong>
                                <span class="badge bg-primary">${data.data.team_size} Members</span>
                            </div>
                            <div class="small text-muted mb-2">Leader: <strong>${escapeHtml(data.data.student_name)}</strong> (${escapeHtml(data.data.college_id)})</div>
                            ${memberItems ? `<div class="mt-1">${memberItems}</div>` : ''}
                        </div>
                    `;
                }

                resultDisplay.innerHTML = `
                    <div class="alert alert-success border-success mb-3 p-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-person-check-fill display-4 text-success"></i>
                            <div>
                                <h3 class="fw-bold mb-0 text-success">${escapeHtml(data.data.student_name)}</h3>
                                <span class="badge bg-success px-3 py-1 rounded-pill mt-1">Checked In Successfully</span>
                            </div>
                        </div>
                    </div>
                    ${teamHtml}
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="row g-2">
                            <div class="col-6"><strong>College ID:</strong><br><span class="badge bg-dark">${escapeHtml(data.data.college_id)}</span></div>
                            <div class="col-6"><strong>Year & Dept:</strong><br>${escapeHtml(data.data.year)} &bull; ${escapeHtml(data.data.department)}</div>
                            <div class="col-12 mt-2 pt-2 border-top"><strong>Event:</strong><br><span class="fw-bold text-primary fs-5">${escapeHtml(data.data.event_title)}</span></div>
                            <div class="col-6 mt-2"><strong>Venue:</strong><br>${escapeHtml(data.data.venue)}</div>
                            <div class="col-6 mt-2"><strong>Check-In Time:</strong><br><span class="text-success fw-bold">${escapeHtml(data.data.checkin_time)}</span></div>
                            <div class="col-12 mt-2 pt-2 border-top small text-muted">Reg Code: <code>${escapeHtml(data.data.reg_code)}</code></div>
                        </div>
                    </div>
                `;
            } else if (data.type === 'duplicate') {
                // DUPLICATE CHECKIN ERROR
                resultCardHeader.className = 'card-header bg-warning text-dark p-4 rounded-top-4';
                resultCardHeader.innerHTML = `<h5 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> ALREADY CHECKED IN</h5>`;

                resultDisplay.innerHTML = `
                    <div class="alert alert-warning border-warning mb-3 p-3 text-dark">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-circle-fill display-4 text-warning"></i>
                            <div>
                                <h4 class="fw-bold mb-0 text-dark">Already Checked In</h4>
                                <p class="mb-0 small">${escapeHtml(data.message)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 border">
                        <p class="mb-1"><strong>Student:</strong> ${escapeHtml(data.data.student_name)} (${escapeHtml(data.data.college_id)})</p>
                        <p class="mb-1"><strong>Event:</strong> ${escapeHtml(data.data.event_title)}</p>
                        <p class="mb-0"><strong>Prev Check-in Time:</strong> ${escapeHtml(data.data.checkin_time)}</p>
                    </div>
                `;
            } else {
                // INVALID CODE ERROR
                resultCardHeader.className = 'card-header bg-danger text-white p-4 rounded-top-4';
                resultCardHeader.innerHTML = `<h5 class="fw-bold mb-0"><i class="bi bi-x-circle-fill me-2"></i> INVALID QR CODE</h5>`;

                resultDisplay.innerHTML = `
                    <div class="alert alert-danger border-danger mb-3 p-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-x-octagon-fill display-4 text-danger"></i>
                            <div>
                                <h4 class="fw-bold mb-0 text-danger">Invalid Pass Code</h4>
                                <p class="mb-0 small">${escapeHtml(data.message)}</p>
                            </div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(err => {
            isProcessing = false;
            console.error(err);
            resultDisplay.innerHTML = `<div class="alert alert-danger">Network or server error processing check-in.</div>`;
        });
    }

    // HTML5 QR Code Webcam Scanner Integration
    if (typeof Html5Qrcode !== 'undefined') {
        html5QrCode = new Html5Qrcode("qr-reader");

        startCamBtn.addEventListener('click', function () {
            startCamBtn.disabled = true;
            scannerStatus.textContent = 'Camera Starting...';
            scannerStatus.className = 'badge bg-warning text-dark rounded-pill';

            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    const cameraId = devices[0].id;
                    html5QrCode.start(
                        cameraId,
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        (decodedText, decodedResult) => {
                            // On Scan Success
                            processCheckInCode(decodedText);
                        },
                        (errorMessage) => {
                            // ignore scan failures
                        }
                    ).then(() => {
                        stopCamBtn.disabled = false;
                        scannerStatus.textContent = 'Camera Active';
                        scannerStatus.className = 'badge bg-success rounded-pill';
                    }).catch(err => {
                        startCamBtn.disabled = false;
                        scannerStatus.textContent = 'Camera Error';
                        scannerStatus.className = 'badge bg-danger rounded-pill';
                        alert('Could not start camera: ' + err);
                    });
                } else {
                    startCamBtn.disabled = false;
                    alert('No camera devices found.');
                }
            }).catch(err => {
                startCamBtn.disabled = false;
                alert('Camera permission denied or not available.');
            });
        });

        stopCamBtn.addEventListener('click', function () {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    startCamBtn.disabled = false;
                    stopCamBtn.disabled = true;
                    scannerStatus.textContent = 'Camera Stopped';
                    scannerStatus.className = 'badge bg-secondary rounded-pill';
                });
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
