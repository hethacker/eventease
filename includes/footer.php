    </div> <!-- End Main Wrapper -->

    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-4 mt-5 border-top border-secondary">
        <div class="container">
            <div class="row g-4">
                <!-- Col 1: Brand & Bio -->
                <div class="col-lg-5 col-md-6">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-ticket-perforated-fill text-primary"></i>
                        <span>EventEase</span>
                    </h5>
                    <p class="text-secondary small">
                        EventEase is a smart digital college event management platform built for hackathons. Streamlining online student registration, instant QR code ticket generation, and high-speed scanner check-ins for administrators.
                    </p>
                    <div class="d-flex gap-3 text-secondary mt-3">
                        <a href="#" class="text-secondary fs-5"><i class="bi bi-github"></i></a>
                        <a href="#" class="text-secondary fs-5"><i class="bi bi-globe"></i></a>
                        <a href="#" class="text-secondary fs-5"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>

                <!-- Col 2: Quick Links -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="text-uppercase fw-bold text-light mb-3">Navigation</h6>
                    <ul class="list-unstyled text-secondary small">
                        <li class="mb-2"><a href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../index.php' : 'index.php'; ?>" class="text-decoration-none text-secondary">Home</a></li>
                        <li class="mb-2"><a href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../events.php' : 'events.php'; ?>" class="text-decoration-none text-secondary">Browse Events</a></li>
                        <li class="mb-2"><a href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../login.php' : 'login.php'; ?>" class="text-decoration-none text-secondary">Student Portal</a></li>
                        <li class="mb-2"><a href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'dashboard.php' : 'admin/dashboard.php'; ?>" class="text-decoration-none text-secondary">Admin Portal</a></li>
                    </ul>
                </div>

                <!-- Col 3: Key Features -->
                <div class="col-lg-4 col-md-12">
                    <h6 class="text-uppercase fw-bold text-light mb-3">Core Modules</h6>
                    <ul class="list-unstyled text-secondary small">
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-1"></i> Instant QR Ticket Generation</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-1"></i> Live Camera QR Scanning</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-1"></i> Duplicate Check-In Prevention</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-primary me-1"></i> Real-time Attendance Analytics</li>
                    </ul>
                </div>
            </div>

            <hr class="my-4 border-secondary opacity-25">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center text-secondary small">
                <p class="mb-2 mb-md-0">&copy; <?php echo date('Y'); ?> EventEase Platform. Built for Hackathon PS-03.</p>
                <div>
                    <span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25">PHP 8 + MySQL + Bootstrap 5</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- QRCode JS Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <!-- HTML5 QR Code Scanner Library CDN (For Admin Check-In) -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <!-- Custom Main JS -->
    <script src="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../assets/js/main.js' : 'assets/js/main.js'; ?>"></script>
</body>
</html>
