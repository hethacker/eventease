<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Fetch upcoming active events (limit 6 for home page)
$stmt = $db->query("SELECT * FROM events WHERE status = 'active' ORDER BY event_date ASC LIMIT 6");
$upcomingEvents = $stmt->fetchAll();

// Fetch Platform Stats
$totalEventsCount = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$totalRegistrationsCount = $db->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$totalCheckinsCount = $db->query("SELECT COUNT(*) FROM registrations WHERE checkin_status = 'checked_in'")->fetchColumn();
?>

<!-- Hero Section -->
<section class="hero-section text-white position-relative">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge bg-primary bg-opacity-25 text-light border border-primary border-opacity-50 px-3 py-2 rounded-pill mb-3">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Hackathon Project PS-03
                </span>
                <h1 class="hero-title mb-3">
                    Discover, Register & Fast Check-In for <span class="text-gradient">Campus Events</span>
                </h1>
                <p class="hero-subtitle mb-4 text-light opacity-75">
                    EventEase is the all-in-one digital college event management portal. Browse exciting technical, cultural, and sports events, register instantly, get your unique QR pass, and breeze through event check-ins.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="events.php" class="btn btn-gradient-primary btn-lg shadow">
                        <i class="bi bi-compass me-2"></i> Explore Events
                    </a>
                    <?php if (!isLoggedIn()): ?>
                        <a href="register.php" class="btn btn-outline-custom btn-lg">
                            <i class="bi bi-person-plus me-2"></i> Register Student
                        </a>
                    <?php else: ?>
                        <a href="my-events.php" class="btn btn-outline-custom btn-lg">
                            <i class="bi bi-qr-code me-2"></i> My Event Passes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 text-center">
                <!-- Visual Card Mockup -->
                <div class="ticket-card text-start shadow-lg border-0 bg-dark text-white p-4 rounded-4 position-relative">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-3">
                        <div>
                            <span class="badge bg-success mb-1">REGISTERED</span>
                            <h6 class="fw-bold mb-0 text-light">TechNova 2026 - Hackathon</h6>
                        </div>
                        <i class="bi bi-qr-code-scan fs-1 text-info"></i>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white p-2 rounded text-center">
                            <i class="bi bi-qr-code text-dark fs-1"></i>
                            <div class="small fw-bold text-dark mt-1" style="font-size: 0.65rem;">EE-2026-PASS</div>
                        </div>
                        <div class="small">
                            <p class="mb-1"><i class="bi bi-calendar3 me-1 text-primary"></i> Oct 15, 2026</p>
                            <p class="mb-1"><i class="bi bi-geo-alt me-1 text-danger"></i> Main Auditorium</p>
                            <p class="mb-0"><i class="bi bi-person-check me-1 text-success"></i> Instant Gate Check-In</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Counter Bar -->
<section class="py-4 bg-white border-bottom shadow-sm">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon icon-purple"><i class="bi bi-calendar-event"></i></div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?php echo $totalEventsCount; ?>+</h3>
                        <span class="text-muted small">Active College Events</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 border-start-md">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon icon-cyan"><i class="bi bi-people-fill"></i></div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?php echo $totalRegistrationsCount; ?>+</h3>
                        <span class="text-muted small">Student Registrations</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 border-start-md">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <div class="stat-icon icon-emerald"><i class="bi bi-qr-code-scan"></i></div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?php echo $totalCheckinsCount; ?></h3>
                        <span class="text-muted small">Validated QR Check-Ins</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Events Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="section-tag">WHAT'S HAPPENING</span>
                <h2 class="section-title">Upcoming College Events</h2>
            </div>
            <a href="events.php" class="btn btn-outline-primary rounded-pill px-4 mt-3 mt-md-0 fw-semibold">
                View All Events <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php if (empty($upcomingEvents)): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No active events found at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcomingEvents as $event): 
                    $remaining = $event['capacity'] - $event['registered_count'];
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
                                    <i class="bi bi-person-fill text-primary"></i> <?php echo max(0, $remaining); ?> seats left
                                </span>
                            </div>
                            <div class="event-card-body">
                                <h5 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <div class="event-meta-item">
                                    <i class="bi bi-calendar3"></i>
                                    <span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span>
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

                                <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                                    <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                                        View Details
                                    </a>
                                    <?php if ($remaining > 0): ?>
                                        <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-gradient-primary">
                                            Register Now
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-3 py-2 rounded-pill">House Full</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5 bg-white border-top border-bottom">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="section-tag">EFFORTLESS PROCESS</span>
            <h2 class="section-title">How EventEase Works</h2>
            <p class="text-muted">A seamless experience for both students and campus administrators</p>
        </div>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h5 class="fw-bold mb-2">Explore Events</h5>
                    <p class="text-muted small mb-0">Browse through departmental, technical, and cultural events happening across campus.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h5 class="fw-bold mb-2">Instant Register</h5>
                    <p class="text-muted small mb-0">Login with your student account and register in a single click with real-time seat tracking.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h5 class="fw-bold mb-2">Get Unique QR</h5>
                    <p class="text-muted small mb-0">Receive a unique digital QR pass saved in your dashboard for easy offline/online access.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h5 class="fw-bold mb-2">Scan & Attend</h5>
                    <p class="text-muted small mb-0">Show your QR code at the venue gate for instant webcam verification by event organizers.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call To Action -->
<section class="py-5 bg-dark text-white text-center position-relative">
    <div class="container py-4">
        <h2 class="fw-bold mb-3">Ready to Experience Digital Campus Events?</h2>
        <p class="text-secondary mb-4 max-w-600 mx-auto">Create your free student profile or login to admin portal to manage events.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="register.php" class="btn btn-gradient-primary btn-lg shadow">Register as Student</a>
            <a href="admin/login.php" class="btn btn-outline-light btn-lg">Admin Login</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
