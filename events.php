<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Get Search & Filter params
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Build query
$query = "SELECT * FROM events WHERE status = 'active'";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR description LIKE ? OR venue LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
}

$query .= " ORDER BY event_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get registered event IDs for logged in user
$userRegisteredEventIds = [];
if (isLoggedIn()) {
    $regStmt = $db->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
    $regStmt->execute([$_SESSION['user_id']]);
    $userRegisteredEventIds = $regStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Categories list
$categories = ['Technology', 'Robotics', 'Cultural', 'Workshop', 'Sports'];
?>

<div class="bg-dark text-white py-4 mb-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1"><i class="bi bi-compass text-primary me-2"></i> Explore College Events</h2>
                <p class="text-secondary small mb-0">Discover upcoming workshops, hackathons, cultural fests, and sports leagues.</p>
            </div>
            <div class="col-md-5 mt-3 mt-md-0 text-md-end">
                <span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25 px-3 py-2">
                    <i class="bi bi-calendar-check me-1"></i> <?php echo count($events); ?> Events Found
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Search & Filter Controls -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="events.php" method="GET" class="row g-3">
                <div class="col-md-6 col-lg-7">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by event title, venue, or keywords..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="col-md-4 col-lg-3">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-gradient-primary w-100 fw-semibold">Filter</button>
                    <?php if (!empty($search) || !empty($category)): ?>
                        <a href="events.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Category Pills Bar -->
            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                <a href="events.php" class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo empty($category) ? 'bg-primary text-white' : 'bg-light text-dark border'; ?>">All Events</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="events.php?category=<?php echo urlencode($cat); ?>" class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo ($category === $cat) ? 'bg-primary text-white' : 'bg-light text-dark border'; ?>">
                        <?php echo $cat; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Event Cards Grid -->
    <div class="row g-4">
        <?php if (empty($events)): ?>
            <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="bi bi-search-heart fs-1 text-muted"></i>
                <h4 class="fw-bold mt-3">No matching events found</h4>
                <p class="text-muted">Try resetting your search query or selecting a different category.</p>
                <a href="events.php" class="btn btn-outline-primary rounded-pill px-4 mt-2">Reset Filters</a>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): 
                $remaining = $event['capacity'] - $event['registered_count'];
                $isRegistered = in_array($event['id'], $userRegisteredEventIds);
                $imageFile = basename($event['image'] ?? 'default-event.jpg');
                $imageFullPath = __DIR__ . '/assets/uploads/' . $imageFile;
                if (!$imageFile || !file_exists($imageFullPath)) {
                    $imageFile = 'default-event.jpg';
                }
                $imagePath = 'assets/uploads/' . rawurlencode($imageFile);
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="event-card">
                        <div class="event-card-img-wrapper">
                            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="event-card-img" onerror="this.src='assets/uploads/default-event.jpg';" />
                            <span class="event-category-badge"><?php echo htmlspecialchars($event['category']); ?></span>
                            <span class="event-seats-badge">
                                <i class="bi bi-people-fill me-1 text-primary"></i> <?php echo max(0, $remaining); ?> / <?php echo $event['capacity']; ?> seats left
                            </span>
                        </div>
                        <div class="event-card-body">
                            <h5 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h5>

                            <div class="mb-2">
                                <?php 
                                $evType = $event['event_type'] ?? 'both';
                                if ($evType === 'team'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill small">
                                        <i class="bi bi-people-fill me-1"></i> Team Event (<?php echo intval($event['min_team_size'] ?? 2); ?>-<?php echo intval($event['max_team_size'] ?? 4); ?>)
                                    </span>
                                <?php elseif ($evType === 'both'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25 rounded-pill small">
                                        <i class="bi bi-person-check-fill me-1"></i> Solo or Team
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill small">
                                        <i class="bi bi-person me-1"></i> Solo Event
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="event-meta-item">
                                <i class="bi bi-calendar3"></i>
                                <span><?php echo date('D, M j, Y', strtotime($event['event_date'])); ?></span>
                            </div>
                            <div class="event-meta-item">
                                <i class="bi bi-clock"></i>
                                <span><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                            </div>
                            <div class="event-meta-item">
                                <i class="bi bi-geo-alt-fill text-danger"></i>
                                <span class="text-truncate"><?php echo htmlspecialchars($event['venue']); ?></span>
                            </div>
                            
                            <p class="text-muted small mt-2 mb-3 line-clamp-2">
                                <?php echo htmlspecialchars(substr($event['description'], 0, 110)) . '...'; ?>
                            </p>

                            <div class="mt-auto d-flex justify-content-between align-items-center pt-3 border-top">
                                <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                                    <i class="bi bi-info-circle me-1"></i> Details
                                </a>

                                <?php if ($isRegistered): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Registered
                                    </span>
                                <?php elseif ($remaining <= 0): ?>
                                    <span class="badge bg-secondary px-3 py-2 rounded-pill">Sold Out</span>
                                <?php else: ?>
                                    <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-gradient-primary">
                                        Register Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
