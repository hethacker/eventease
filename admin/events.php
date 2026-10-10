<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';

requireAdmin();

$db = getDB();

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = intval($_GET['id']);
    if ($deleteId > 0) {
        $delStmt = $db->prepare("DELETE FROM events WHERE id = ?");
        if ($delStmt->execute([$deleteId])) {
            setFlash('success', 'Event deleted successfully.');
        } else {
            setFlash('danger', 'Failed to delete event.');
        }
    }
    header('Location: events.php');
    exit;
}

// Fetch all events
$events = $db->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mb-2">Admin Panel</span>
                <h2 class="fw-bold mb-1">Manage Campus Events</h2>
                <p class="text-secondary small mb-0">Create, update, or remove event listings across campus.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                <a href="students.php" class="btn btn-outline-light rounded-pill">
                    <i class="bi bi-people-fill me-1"></i> Registered Students
                </a>
                <a href="add-event.php" class="btn btn-gradient-primary rounded-pill shadow">
                    <i class="bi bi-plus-circle me-1"></i> Add New Event
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Banner</th>
                            <th>Event Details</th>
                            <th>Category</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No events created yet. Click "Add New Event" to get started.</td></tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev): 
                                $remaining = $ev['capacity'] - $ev['registered_count'];
                                $pct = ($ev['capacity'] > 0) ? min(100, round(($ev['registered_count'] / $ev['capacity']) * 100)) : 0;
                                $imageFile = basename($ev['image'] ?? 'default-event.jpg');
                                $imageFullPath = __DIR__ . '/../assets/uploads/' . $imageFile;
                                if (!$imageFile || !file_exists($imageFullPath)) {
                                    $imageFile = 'default-event.jpg';
                                }
                                $imagePath = '../assets/uploads/' . rawurlencode($imageFile);
                            ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $imagePath; ?>" alt="Banner" class="rounded object-fit-cover" style="width: 60px; height: 40px;" onerror="this.src='../assets/uploads/default-event.jpg';" />
                                    </td>
                                    <td>
                                        <strong class="d-block text-dark"><?php echo htmlspecialchars($ev['title']); ?></strong>
                                        <small class="text-muted"><i class="bi bi-building"></i> <?php echo htmlspecialchars($ev['organizer']); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ev['category']); ?></span></td>
                                    <td>
                                        <small class="d-block fw-semibold"><i class="bi bi-calendar3 me-1"></i> <?php echo date('M j, Y', strtotime($ev['event_date'])); ?></small>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('g:i A', strtotime($ev['event_time'])); ?></small>
                                    </td>
                                    <td><small class="text-truncate d-inline-block" style="max-width: 140px;"><?php echo htmlspecialchars($ev['venue']); ?></small></td>
                                    <td>
                                        <div class="small fw-bold mb-1"><?php echo $ev['registered_count']; ?> / <?php echo $ev['capacity']; ?></div>
                                        <div class="progress" style="height: 5px; width: 90px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $pct; ?>%;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($ev['status'] === 'active'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="registrations.php?event_id=<?php echo $ev['id']; ?>" class="btn btn-outline-info" title="View Registered Students">
                                                <i class="bi bi-people"></i>
                                            </a>
                                            <a href="edit-event.php?id=<?php echo $ev['id']; ?>" class="btn btn-outline-primary" title="Edit Event">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="events.php?action=delete&id=<?php echo $ev['id']; ?>" class="btn btn-outline-danger" title="Delete Event" onclick="return confirm('Are you sure you want to delete this event? All registrations for this event will also be deleted.')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
