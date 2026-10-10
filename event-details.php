<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$eventId = intval($_GET['id'] ?? 0);

if ($eventId <= 0) {
    setFlash('danger', 'Invalid event selected.');
    header('Location: events.php');
    exit;
}

// Fetch event details
$stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND status = 'active'");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('danger', 'Event not found or has been cancelled.');
    header('Location: events.php');
    exit;
}

$remainingSeats = $event['capacity'] - $event['registered_count'];
$percentFilled = min(100, round(($event['registered_count'] / $event['capacity']) * 100));

// Check if user is already registered for this event
$isRegistered = false;
$registrationDetails = null;

if (isLoggedIn()) {
    $regStmt = $db->prepare("SELECT * FROM registrations WHERE user_id = ? AND event_id = ?");
    $regStmt->execute([$_SESSION['user_id'], $eventId]);
    $registrationDetails = $regStmt->fetch();
    if ($registrationDetails) {
        $isRegistered = true;
    }
}
?>

<?php
$imageFile = basename($event['image'] ?? 'default-event.jpg');
$imageFullPath = __DIR__ . '/assets/uploads/' . $imageFile;
if (!$imageFile || !file_exists($imageFullPath)) {
    $imageFile = 'default-event.jpg';
}
$imagePath = 'assets/uploads/' . rawurlencode($imageFile);
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="events.php">Events</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($event['title']); ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Left Column: Image & Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="position-relative">
                    <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="w-100 object-fit-cover" style="max-height: 380px;" onerror="this.src='assets/uploads/default-event.jpg';" />
                    <span class="position-absolute top-0 start-0 m-3 badge bg-primary px-3 py-2 rounded-pill fs-6 shadow">
                        <?php echo htmlspecialchars($event['category']); ?>
                    </span>
                    <span class="position-absolute top-0 end-0 m-3 badge bg-dark bg-opacity-75 text-white px-3 py-2 rounded-pill fs-6 shadow">
                        <?php 
                        $evType = $event['event_type'] ?? 'both';
                        if ($evType === 'team') {
                            echo '<i class="bi bi-people-fill text-warning me-1"></i> Team Event (' . intval($event['min_team_size'] ?? 2) . '-' . intval($event['max_team_size'] ?? 4) . ' Members)';
                        } elseif ($evType === 'both') {
                            echo '<i class="bi bi-person-check-fill text-info me-1"></i> Solo / Team Event';
                        } else {
                            echo '<i class="bi bi-person text-light me-1"></i> Solo Event';
                        }
                        ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($event['title']); ?></h2>

                    <div class="row g-3 py-3 border-top border-bottom my-3">
                        <div class="col-sm-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-calendar3 fs-4 text-primary"></i>
                                <div>
                                    <small class="text-muted d-block">Date</small>
                                    <strong><?php echo date('F j, Y', strtotime($event['event_date'])); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-clock fs-4 text-info"></i>
                                <div>
                                    <small class="text-muted d-block">Time</small>
                                    <strong><?php echo date('g:i A', strtotime($event['event_time'])); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-geo-alt-fill fs-4 text-danger"></i>
                                <div>
                                    <small class="text-muted d-block">Venue</small>
                                    <strong><?php echo htmlspecialchars($event['venue']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold mt-4 mb-2">Event Overview</h5>
                    <p class="text-secondary leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </p>

                    <?php if (!empty($event['rules'])): ?>
                        <h5 class="fw-bold mt-4 mb-2">Event Rules & Guidelines</h5>
                        <div class="p-3 bg-light rounded-3 border">
                            <ul class="list-unstyled mb-0">
                                <?php 
                                $rules = explode("\n", $event['rules']);
                                foreach ($rules as $rule):
                                    if (trim($rule) !== ''):
                                ?>
                                    <li class="mb-2 d-flex align-items-start gap-2">
                                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                                        <span><?php echo htmlspecialchars(trim($rule)); ?></span>
                                    </li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top d-flex align-items-center gap-2 text-muted small">
                        <i class="bi bi-building me-1"></i> Organized by: <strong class="text-dark"><?php echo htmlspecialchars($event['organizer']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Registration Box -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 100px;">
                <div class="card-header bg-dark text-white p-4 rounded-top-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-ticket-perforated me-2 text-primary"></i> Registration Status</h5>
                </div>
                <div class="card-body p-4">

                    <!-- Seats Progress Widget -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1 small fw-semibold">
                            <span>Seats Availability</span>
                            <span class="text-primary"><?php echo max(0, $remainingSeats); ?> remaining</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-gradient-primary" role="progressbar" style="width: <?php echo $percentFilled; ?>%;" aria-valuenow="<?php echo $percentFilled; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                            <span><?php echo $event['registered_count']; ?> Registered</span>
                            <span>Total Capacity: <?php echo $event['capacity']; ?></span>
                        </div>
                    </div>

                    <hr>

                    <!-- Action Button Logic -->
                    <?php if ($isRegistered): ?>
                        <div class="alert alert-success border-success text-center mb-3">
                            <i class="bi bi-check-circle-fill fs-2 d-block mb-1 text-success"></i>
                            <strong>Registration Confirmed!</strong>
                            <?php if (($registrationDetails['registration_type'] ?? '') === 'team'): ?>
                                <div class="mt-2">
                                    <span class="badge bg-primary px-3 py-1 rounded-pill">
                                        <i class="bi bi-people-fill me-1"></i> Team: <?php echo htmlspecialchars($registrationDetails['team_name'] ?? ''); ?>
                                    </span>
                                    <p class="small text-muted mt-1 mb-0"><?php echo intval($registrationDetails['team_size'] ?? 1); ?> Team Members Registered</p>
                                </div>
                            <?php endif; ?>
                            <p class="small mb-0 mt-2">Reg Code: <code><?php echo htmlspecialchars($registrationDetails['registration_code']); ?></code></p>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="my-events.php" class="btn btn-success btn-lg shadow">
                                <i class="bi bi-qr-code me-1"></i> View QR Code Pass
                            </a>
                        </div>
                    <?php elseif ($remainingSeats <= 0): ?>
                        <div class="alert alert-danger text-center mb-3">
                            <i class="bi bi-x-circle-fill fs-2 d-block mb-1 text-danger"></i>
                            <strong>House Full!</strong>
                            <p class="small mb-0 mt-1">Registration capacity has been reached for this event.</p>
                        </div>
                    <?php else: ?>
                        <?php if (isLoggedIn()): 
                            $eventType   = $event['event_type'] ?? 'both';
                            $minTeamSize = max(1, intval($event['min_team_size'] ?? 2));
                            $maxTeamSize = max($minTeamSize, intval($event['max_team_size'] ?? 4));
                        ?>
                            <!-- Registration Mode Tabs if Both Allowed -->
                            <?php if ($eventType === 'both'): ?>
                                <div class="btn-group w-100 mb-3 p-1 bg-light rounded-pill border" role="group">
                                    <button type="button" class="btn btn-sm rounded-pill active fw-semibold" id="modeBtnSolo" onclick="switchRegMode('solo')">
                                        <i class="bi bi-person me-1"></i> Individual
                                    </button>
                                    <button type="button" class="btn btn-sm rounded-pill text-muted fw-semibold" id="modeBtnTeam" onclick="switchRegMode('team')">
                                        <i class="bi bi-people-fill me-1"></i> Team Pass
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- 1. Solo Registration Form -->
                            <div id="soloRegForm" style="<?php echo ($eventType === 'team') ? 'display: none;' : 'display: block;'; ?>">
                                <form action="register-event.php" method="POST">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <input type="hidden" name="registration_type" value="individual">

                                    <div class="p-3 bg-light rounded-3 mb-3 small border">
                                        <div class="mb-1 text-muted fw-semibold">Registering as Individual:</div>
                                        <div class="fw-bold text-dark"><i class="bi bi-person-fill text-primary me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                        <div class="text-muted"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-gradient-primary btn-lg shadow">
                                            <i class="bi bi-check2-square me-2"></i> Register Solo
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- 2. Team Registration Form -->
                            <div id="teamRegForm" style="<?php echo ($eventType === 'team') ? 'display: block;' : 'display: none;'; ?>">
                                <form action="register-event.php" method="POST" id="teamRegistrationForm" onsubmit="return validateTeamForm();">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <input type="hidden" name="registration_type" value="team">

                                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
                                        <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
                                        <div>
                                            <strong>Team Size:</strong> <?php echo $minTeamSize; ?> to <?php echo $maxTeamSize; ?> Members (including Leader).
                                        </div>
                                    </div>

                                    <!-- Team Name -->
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            Team Name <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-flag-fill text-primary"></i></span>
                                            <input type="text" name="team_name" id="teamNameInput" class="form-control" placeholder="e.g. CodeCrusaders, AlphaSquad">
                                        </div>
                                    </div>

                                    <!-- Team Leader Card -->
                                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 mb-3 border border-primary border-opacity-25 small">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-primary"><i class="bi bi-star-fill text-warning me-1"></i> Team Leader (You)</strong>
                                            <span class="badge bg-primary">Leader</span>
                                        </div>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                        <div class="text-muted"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
                                    </div>

                                    <!-- Team Members Container -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label small fw-bold text-dark mb-0">Team Members</label>
                                        <span class="badge bg-secondary-subtle text-secondary small" id="teamCountBadge">
                                            1 / <?php echo $maxTeamSize; ?> Members
                                        </span>
                                    </div>

                                    <div id="membersContainer">
                                        <!-- Member rows dynamically inserted here -->
                                    </div>

                                    <div class="d-grid mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="addMemberBtn" onclick="addMemberRow()">
                                            <i class="bi bi-person-plus-fill me-1"></i> + Add Team Member
                                        </button>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-gradient-primary btn-lg shadow">
                                            <i class="bi bi-people-fill me-2"></i> Register Full Team
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <script>
                                const MIN_TEAM_SIZE = <?php echo $minTeamSize; ?>;
                                const MAX_TEAM_SIZE = <?php echo $maxTeamSize; ?>;
                                let currentMembers = 0; // Additional members (excluding leader)

                                function switchRegMode(mode) {
                                    const soloForm = document.getElementById('soloRegForm');
                                    const teamForm = document.getElementById('teamRegForm');
                                    const btnSolo  = document.getElementById('modeBtnSolo');
                                    const btnTeam  = document.getElementById('modeBtnTeam');

                                    if (mode === 'team') {
                                        soloForm.style.display = 'none';
                                        teamForm.style.display = 'block';
                                        if (btnSolo) {
                                            btnSolo.classList.remove('active', 'btn-primary');
                                            btnSolo.classList.add('text-muted');
                                        }
                                        if (btnTeam) {
                                            btnTeam.classList.add('active', 'btn-primary');
                                            btnTeam.classList.remove('text-muted');
                                        }

                                        // Auto-add initial members if min required > 1
                                        if (currentMembers === 0 && MIN_TEAM_SIZE > 1) {
                                            for (let i = 1; i < MIN_TEAM_SIZE; i++) {
                                                addMemberRow();
                                            }
                                        }
                                    } else {
                                        teamForm.style.display = 'none';
                                        soloForm.style.display = 'block';
                                        if (btnTeam) {
                                            btnTeam.classList.remove('active', 'btn-primary');
                                            btnTeam.classList.add('text-muted');
                                        }
                                        if (btnSolo) {
                                            btnSolo.classList.add('active', 'btn-primary');
                                            btnSolo.classList.remove('text-muted');
                                        }
                                    }
                                }

                                function updateMemberCounter() {
                                    const total = 1 + currentMembers;
                                    const badge = document.getElementById('teamCountBadge');
                                    if (badge) {
                                        badge.textContent = `${total} / ${MAX_TEAM_SIZE} Members`;
                                    }

                                    const addBtn = document.getElementById('addMemberBtn');
                                    if (addBtn) {
                                        if (total >= MAX_TEAM_SIZE) {
                                            addBtn.disabled = true;
                                            addBtn.classList.add('opacity-50');
                                        } else {
                                            addBtn.disabled = false;
                                            addBtn.classList.remove('opacity-50');
                                        }
                                    }
                                }

                                function addMemberRow() {
                                    const total = 1 + currentMembers;
                                    if (total >= MAX_TEAM_SIZE) {
                                        alert(`Maximum team size is ${MAX_TEAM_SIZE} members.`);
                                        return;
                                    }

                                    currentMembers++;
                                    const memberIndex = currentMembers;
                                    const container = document.getElementById('membersContainer');

                                    const row = document.createElement('div');
                                    row.className = 'card border rounded-3 p-2 mb-2 bg-white shadow-sm member-row';
                                    row.id = `memberRow_${memberIndex}`;
                                    row.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="fw-bold text-dark"><i class="bi bi-person me-1"></i> Member #${memberIndex + 1}</small>
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-0 px-1" onclick="removeMemberRow(${memberIndex})" title="Remove member">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-7">
                                                <input type="text" name="member_name[]" class="form-control form-control-sm" placeholder="Full Name *" required>
                                            </div>
                                            <div class="col-5">
                                                <input type="text" name="member_college_id[]" class="form-control form-control-sm" placeholder="Roll/ID *" required>
                                            </div>
                                            <div class="col-12">
                                                <input type="email" name="member_email[]" class="form-control form-control-sm" placeholder="Email / Phone (Optional)">
                                            </div>
                                        </div>
                                    `;
                                    container.appendChild(row);
                                    updateMemberCounter();
                                }

                                function removeMemberRow(id) {
                                    const row = document.getElementById(`memberRow_${id}`);
                                    if (row) {
                                        row.remove();
                                        currentMembers--;
                                        updateMemberCounter();
                                    }
                                }

                                function validateTeamForm() {
                                    const teamNameInput = document.getElementById('teamNameInput');
                                    if (!teamNameInput || !teamNameInput.value.trim()) {
                                        alert('Please enter your Team Name.');
                                        return false;
                                    }
                                    const total = 1 + currentMembers;
                                    if (total < MIN_TEAM_SIZE) {
                                        alert(`This event requires a minimum of ${MIN_TEAM_SIZE} members (including Team Leader). Please add more members.`);
                                        return false;
                                    }
                                    return true;
                                }

                                <?php if ($eventType === 'team'): ?>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        if (MIN_TEAM_SIZE > 1) {
                                            for (let i = 1; i < MIN_TEAM_SIZE; i++) {
                                                addMemberRow();
                                            }
                                        }
                                    });
                                <?php endif; ?>
                            </script>

                        <?php else: ?>
                            <div class="alert alert-warning text-center mb-3">
                                <i class="bi bi-shield-lock fs-3 d-block mb-1"></i>
                                <span>Please login to register for this event.</span>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="login.php" class="btn btn-gradient-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i> Login to Register
                                </a>
                                <a href="register.php" class="btn btn-outline-dark">
                                    Create Account
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top text-center text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i> Guaranteed QR Pass upon registration
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
