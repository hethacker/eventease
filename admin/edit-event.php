<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();
$eventId = intval($_GET['id'] ?? 0);

if ($eventId <= 0) {
    setFlash('danger', 'Invalid event ID.');
    header('Location: events.php');
    exit;
}

// Fetch existing event
$stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('danger', 'Event not found.');
    header('Location: events.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? 'General');
    $description = trim($_POST['description'] ?? '');
    $rules       = trim($_POST['rules'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $event_time  = trim($_POST['event_time'] ?? '');
    $venue       = trim($_POST['venue'] ?? '');
    $organizer   = trim($_POST['organizer'] ?? 'Campus Event Committee');
    $capacity      = intval($_POST['capacity'] ?? 0);
    $event_type    = trim($_POST['event_type'] ?? ($event['event_type'] ?? 'both'));
    $min_team_size = max(1, intval($_POST['min_team_size'] ?? ($event['min_team_size'] ?? 1)));
    $max_team_size = max($min_team_size, intval($_POST['max_team_size'] ?? ($event['max_team_size'] ?? 4)));
    $status        = trim($_POST['status'] ?? 'active');
    $imageName     = $event['image'];

    if (empty($title) || empty($description) || empty($event_date) || empty($event_time) || empty($venue) || $capacity <= 0) {
        $error = 'Please fill out all required fields.';
    } else {
        // Image Upload if provided
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
            $updateStmt = $db->prepare("UPDATE events SET title = ?, category = ?, event_type = ?, min_team_size = ?, max_team_size = ?, description = ?, rules = ?, event_date = ?, event_time = ?, venue = ?, organizer = ?, capacity = ?, image = ?, status = ? WHERE id = ?");
            
            if ($updateStmt->execute([$title, $category, $event_type, $min_team_size, $max_team_size, $description, $rules, $event_date, $event_time, $venue, $organizer, $capacity, $imageName, $status, $eventId])) {
                setFlash('success', 'Event updated successfully.');
                header('Location: events.php');
                exit;
            } else {
                $error = 'Failed to update event in database.';
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
                    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Campus Event</h4>
                    <a href="events.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i> Back</a>
                </div>
                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="edit-event.php?id=<?php echo $eventId; ?>" method="POST" enctype="multipart/form-data">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="title" class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <?php 
                                    $cats = ['Technology', 'Robotics', 'Cultural', 'Workshop', 'Sports', 'Seminar', 'General'];
                                    foreach ($cats as $c):
                                    ?>
                                        <option value="<?php echo $c; ?>" <?php echo ($event['category'] === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="rules" class="form-label fw-semibold">Rules & Guidelines</label>
                            <textarea class="form-control" id="rules" name="rules" rows="3"><?php echo htmlspecialchars($event['rules']); ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="event_date" class="form-label fw-semibold">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event['event_date']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="event_time" class="form-label fw-semibold">Event Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="event_time" name="event_time" value="<?php echo htmlspecialchars($event['event_time']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="capacity" class="form-label fw-semibold">Capacity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="<?php echo htmlspecialchars($event['capacity']); ?>" required>
                            </div>
                        </div>

                        <!-- Team Configuration Row -->
                        <div class="row g-3 mb-3 p-3 bg-light rounded-3 border">
                            <div class="col-md-4">
                                <label for="event_type" class="form-label fw-semibold">Participation Format</label>
                                <select class="form-select" id="event_type" name="event_type">
                                    <option value="both" <?php echo (($event['event_type'] ?? '') === 'both') ? 'selected' : ''; ?>>Solo & Team Allowed</option>
                                    <option value="team" <?php echo (($event['event_type'] ?? '') === 'team') ? 'selected' : ''; ?>>Team Only (Hackathons, Group)</option>
                                    <option value="individual" <?php echo (($event['event_type'] ?? '') === 'individual') ? 'selected' : ''; ?>>Individual (Solo Only)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="min_team_size" class="form-label fw-semibold">Min Team Size</label>
                                <input type="number" class="form-control" id="min_team_size" name="min_team_size" min="1" max="20" value="<?php echo htmlspecialchars($event['min_team_size'] ?? '1'); ?>">
                                <small class="text-muted">Includes team leader</small>
                            </div>
                            <div class="col-md-4">
                                <label for="max_team_size" class="form-label fw-semibold">Max Team Size</label>
                                <input type="number" class="form-control" id="max_team_size" name="max_team_size" min="1" max="20" value="<?php echo htmlspecialchars($event['max_team_size'] ?? '4'); ?>">
                                <small class="text-muted">Max members allowed</small>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label for="venue" class="form-label fw-semibold">Venue <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="venue" name="venue" value="<?php echo htmlspecialchars($event['venue']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="organizer" class="form-label fw-semibold">Organizer</label>
                                <input type="text" class="form-control" id="organizer" name="organizer" value="<?php echo htmlspecialchars($event['organizer']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label fw-semibold">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo ($event['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="cancelled" <?php echo ($event['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="image" class="form-label fw-semibold">Change Banner Image</label>
                            <input type="file" class="form-control mb-2" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="small text-muted">Current image: <code><?php echo htmlspecialchars($event['image']); ?></code> | Supported formats: JPG, PNG, WEBP (Max 5 MB)</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="events.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-gradient-primary shadow"><i class="bi bi-check-lg me-1"></i> Update Event</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
