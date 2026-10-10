<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: events.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $college_id = trim($_POST['college_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year       = trim($_POST['year'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($college_id) || empty($department) || empty($year) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $db = getDB();

        // Check if email already registered
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            // Hash password securely
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users
            $insertStmt = $db->prepare("INSERT INTO users (name, email, phone, college_id, department, year, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
            
            if ($insertStmt->execute([$name, $email, $phone, $college_id, $department, $year, $hashedPassword])) {
                $newUserId = $db->lastInsertId();

                // Set session
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'student';

                setFlash('success', 'Registration successful! Welcome to EventEase.');
                header('Location: events.php');
                exit;
            } else {
                $error = 'Failed to register account. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-dark text-white p-4 rounded-top-4 text-center">
                    <i class="bi bi-person-plus-fill text-primary fs-1 mb-2"></i>
                    <h3 class="fw-bold mb-1">Student Registration</h3>
                    <p class="text-secondary small mb-0">Create your account to discover & register for campus events</p>
                </div>
                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="register.php" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="name" name="name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter student email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter 10-digit mobile number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="college_id" class="form-label fw-semibold">College ID / Roll No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="college_id" name="college_id" placeholder="Enter college ID" value="<?php echo htmlspecialchars($_POST['college_id'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="department" class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Dept</option>
                                    <option value="Computer Science & Engineering" <?php echo (($_POST['department'] ?? '') === 'Computer Science & Engineering') ? 'selected' : ''; ?>>Computer Science & Engineering</option>
                                    <option value="Information Technology" <?php echo (($_POST['department'] ?? '') === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                    <option value="Electronics & Communication" <?php echo (($_POST['department'] ?? '') === 'Electronics & Communication') ? 'selected' : ''; ?>>Electronics & Communication</option>
                                    <option value="Mechanical Engineering" <?php echo (($_POST['department'] ?? '') === 'Mechanical Engineering') ? 'selected' : ''; ?>>Mechanical Engineering</option>
                                    <option value="Civil Engineering" <?php echo (($_POST['department'] ?? '') === 'Civil Engineering') ? 'selected' : ''; ?>>Civil Engineering</option>
                                    <option value="Management Studies (MBA/BBA)" <?php echo (($_POST['department'] ?? '') === 'Management Studies (MBA/BBA)') ? 'selected' : ''; ?>>Management Studies</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="year" class="form-label fw-semibold">Year of Study <span class="text-danger">*</span></label>
                                <select class="form-select" id="year" name="year" required>
                                    <option value="">Select Year</option>
                                    <option value="1st Year" <?php echo (($_POST['year'] ?? '') === '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (($_POST['year'] ?? '') === '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (($_POST['year'] ?? '') === '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (($_POST['year'] ?? '') === '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="Post Graduate" <?php echo (($_POST['year'] ?? '') === 'Post Graduate') ? 'selected' : ''; ?>>Post Graduate</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-gradient-primary btn-lg shadow">
                                <i class="bi bi-person-check-fill me-2"></i> Create Student Account
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted">Already registered?</span>
                        <a href="login.php" class="fw-bold text-primary ms-1 text-decoration-none">Sign In Here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
