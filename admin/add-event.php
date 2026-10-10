<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? 'General');
    $description = trim($_POST['description'] ?? '');
    $rules       = trim($_POST['rules'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $event_time  = trim($_POST['event_time'] ?? '');
    $venue       = trim($_POST['venue'] ?? '');
    $organizer     = trim($_POST['organizer'] ?? 'Campus Event Committee');
    $capacity      = intval($_POST['capacity'] ?? 0);
    $event_type    = trim($_POST['event_type'] ?? 'both');
    $min_team_size = max(1, intval($_POST['min_team_size'] ?? 1));
    $max_team_size = max($min_team_size, intval($_POST['max_team_size'] ?? 4));
    $imageName     = 'default-event.jpg';

    if (empty($title) || empty($description) || empty($event_date) || empty($event_time) || empty($venue) || $capacity <= 0) {
        $error = 'Please fill out all required fields with valid values.';
    } else {
        // Process Image Upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Image upload failed. Please try again.';
            } else {
                $fileTmpPath = $_FILES['image']['tmp_name'];
                $fileSize = (int) $_FILES['image']['size'];

                $maxFileSize = 5 * 1024 * 1024;

                $allowedMimeTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if ($fileSize > $maxFileSize) {
                    $error = 'Image size must be 5 MB or less.';
                } elseif (@getimagesize($fileTmpPath) === false) {
                    $error = 'Invalid image file. Please upload a real JPG, PNG, or WEBP image.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($fileTmpPath);

                    if (!isset($allowedMimeTypes[$mimeType])) {
                        $error = 'Invalid image format. Only JPG, PNG and WEBP images are allowed.';
                    } else {
                        $extension = $allowedMimeTypes[$mimeType];
                        $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;

                        $uploadFileDir = __DIR__ . '/../assets/uploads/';

                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0755, true);
                        }

                        $destination = $uploadFileDir . $newFileName;

                        if (move_uploaded_file($fileTmpPath, $destination)) {
                            $imageName = $newFileName;
                        } else {
                            $error = 'Unable to save the uploaded image.';
                        }
                    }
                }
            }
        }

        if (empty($error)) {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO events (title, category, event_type, min_team_size, max_team_size, description, rules, event_date, event_time, venue, organizer, capacity, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            
            if ($stmt->execute([$title, $category, $event_type, $min_team_size, $max_team_size, $description, $rules, $event_date, $event_time, $venue, $organizer, $capacity, $imageName])) {
                setFlash('success', 'New event created successfully.');
                header('Location: events.php');
                exit;
            } else {
                $error = 'Failed to create event in database.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-dark text-white p-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold mb-0"><i class="bi bi-calendar-plus text-primary me-2"></i> Add New Campus Event</h4>
                    <a href="events.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i> Back</a>
                </div>
                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="add-event.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="title" class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="e.g. AI & Robotics Symposium 2026" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="Technology">Technology</option>
                                    <option value="Robotics">Robotics</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Workshop">Workshop</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Seminar">Seminar</option>
                                    <option value="General">General</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" placeholder="Provide a comprehensive summary of the event..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="rules" class="form-label fw-semibold">Rules & Guidelines (One per line)</label>
                            <textarea class="form-control" id="rules" name="rules" rows="3" placeholder="1. Laptops mandatory&#10;2. Carry college ID card"><?php echo htmlspecialchars($_POST['rules'] ?? ''); ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="event_date" class="form-label fw-semibold">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo htmlspecialchars($_POST['event_date'] ?? date('Y-m-d')); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="event_time" class="form-label fw-semibold">Event Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="event_time" name="event_time" value="<?php echo htmlspecialchars($_POST['event_time'] ?? '10:00'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="capacity" class="form-label fw-semibold">Capacity (Seats) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" placeholder="100" value="<?php echo htmlspecialchars($_POST['capacity'] ?? '100'); ?>" required>
                            </div>
                        </div>

                        <!-- Team Configuration Row -->
                        <div class="row g-3 mb-3 p-3 bg-light rounded-3 border">
                            <div class="col-md-4">
                                <label for="event_type" class="form-label fw-semibold">Participation Format</label>
                                <select class="form-select" id="event_type" name="event_type">
                                    <option value="both" selected>Solo & Team Allowed</option>
                                    <option value="team">Team Only (Hackathons, Group)</option>
                                    <option value="individual">Individual (Solo Only)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="min_team_size" class="form-label fw-semibold">Min Team Size</label>
                                <input type="number" class="form-control" id="min_team_size" name="min_team_size" min="1" max="20" value="<?php echo htmlspecialchars($_POST['min_team_size'] ?? '1'); ?>">
                                <small class="text-muted">Includes team leader</small>
                            </div>
                            <div class="col-md-4">
                                <label for="max_team_size" class="form-label fw-semibold">Max Team Size</label>
                                <input type="number" class="form-control" id="max_team_size" name="max_team_size" min="1" max="20" value="<?php echo htmlspecialchars($_POST['max_team_size'] ?? '4'); ?>">
                                <small class="text-muted">Max members allowed</small>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="venue" class="form-label fw-semibold">Venue <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="venue" name="venue" placeholder="e.g. Main Auditorium Hall 2" value="<?php echo htmlspecialchars($_POST['venue'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="organizer" class="form-label fw-semibold">Organizer Body</label>
                                <input type="text" class="form-control" id="organizer" name="organizer" placeholder="e.g. CS Student Council" value="<?php echo htmlspecialchars($_POST['organizer'] ?? 'Campus Event Committee'); ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="image" class="form-label fw-semibold">Banner Image Upload (Optional)</label>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <small class="text-muted">Supported formats: JPG, PNG, WEBP. Maximum size: 5 MB.</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="events.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-gradient-primary shadow"><i class="bi bi-check-lg me-1"></i> Save Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
