<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = getLoggedInUser();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone            = trim($_POST['phone'] ?? '');
    $department       = trim($_POST['department'] ?? '');
    $year             = trim($_POST['year'] ?? '');
    $waConsent        = isset($_POST['whatsapp_consent']) ? 1 : 0;
    $waNumber         = trim($_POST['whatsapp_number'] ?? '');

    if (empty($phone) || empty($department) || empty($year)) {
        $error = 'Please fill in all editable fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET phone = ?, department = ?, year = ?, whatsapp_consent = ?, whatsapp_number = ? WHERE id = ?");
        if ($stmt->execute([$phone, $department, $year, $waConsent, ($waNumber ?: null), $user['id']])) {
            setFlash('success', 'Profile updated successfully.');
            header('Location: profile.php');
            exit;
        } else {
            $error = 'Failed to update profile.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-dark text-white p-4 rounded-top-4 d-flex align-items-center gap-3">
                    <div class="stat-icon icon-purple rounded-circle"><i class="bi bi-person-fill fs-3"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($user['name']); ?></h4>
                        <span class="text-secondary small"><?php echo htmlspecialchars($user['email']); ?> &bull; <?php echo strtoupper($user['role']); ?></span>
                    </div>
                </div>
                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Full Name (Read-Only)</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Email Address (Read-Only)</label>
                                <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">College ID (Read-Only)</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['college_id']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="department" class="form-label fw-semibold">Department</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="Computer Science & Engineering" <?php echo ($user['department'] === 'Computer Science & Engineering') ? 'selected' : ''; ?>>Computer Science & Engineering</option>
                                    <option value="Information Technology" <?php echo ($user['department'] === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                    <option value="Electronics & Communication" <?php echo ($user['department'] === 'Electronics & Communication') ? 'selected' : ''; ?>>Electronics & Communication</option>
                                    <option value="Mechanical Engineering" <?php echo ($user['department'] === 'Mechanical Engineering') ? 'selected' : ''; ?>>Mechanical Engineering</option>
                                    <option value="Civil Engineering" <?php echo ($user['department'] === 'Civil Engineering') ? 'selected' : ''; ?>>Civil Engineering</option>
                                    <option value="Management Studies (MBA/BBA)" <?php echo ($user['department'] === 'Management Studies (MBA/BBA)') ? 'selected' : ''; ?>>Management Studies</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="year" class="form-label fw-semibold">Year of Study</label>
                                <select class="form-select" id="year" name="year" required>
                                    <option value="1st Year" <?php echo ($user['year'] === '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($user['year'] === '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($user['year'] === '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo ($user['year'] === '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="Post Graduate" <?php echo ($user['year'] === 'Post Graduate') ? 'selected' : ''; ?>>Post Graduate</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="my-events.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                            <button type="submit" class="btn btn-gradient-primary shadow">Save Profile Changes</button>
                        </div>
                    </form>

                    <!-- WhatsApp Consent Section -->
                    <div class="mt-4 pt-4 border-top">
                        <h6 class="fw-bold mb-3"><i class="bi bi-whatsapp text-success me-2"></i>WhatsApp Notifications</h6>
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($user['department']); ?>">
                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($user['year']); ?>">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="waConsent" name="whatsapp_consent" value="1"
                                       <?php echo !empty($user['whatsapp_consent']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="waConsent">
                                    I consent to receive WhatsApp notifications from EventEase
                                </label>
                                <div class="small text-muted mt-1">
                                    You'll receive messages for event registration, certificate availability, and rank announcements.
                                    Standard messaging rates may apply.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">WhatsApp Number <span class="text-muted small fw-normal">(optional – leave blank to use phone number above)</span></label>
                                <input type="tel" name="whatsapp_number" class="form-control" placeholder="e.g. +919876543210"
                                       value="<?php echo htmlspecialchars($user['whatsapp_number'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-4">
                                <i class="bi bi-check2 me-1"></i> Save WhatsApp Settings
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
