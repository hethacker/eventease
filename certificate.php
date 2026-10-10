<?php
/**
 * EventEase - Certificate Download / View
 * URL: /certificate.php?id=CERT-2026-XXXXXXXX
 *
 * Server-side security: only the certificate owner or admin can access.
 * Renders an HTML certificate page that the user can print/save as PDF.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/certificate-service.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$certId  = trim($_GET['id'] ?? '');
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';

if (empty($certId)) {
    die('Invalid certificate ID.');
}

$certSvc = new CertificateService();
$cert    = $certSvc->getCertificateById($certId);

if (!$cert) {
    http_response_code(404);
    die('Certificate not found or has been revoked.');
}

// Security: students can only view their own certificates; admins can view all
$currentUserId   = $_SESSION['user_id'];
$currentUserRole = $_SESSION['user_role'] ?? 'student';

if ($currentUserRole !== 'admin' && (int)$cert['user_id'] !== (int)$currentUserId) {
    http_response_code(403);
    die('Access denied. You are not authorised to view this certificate.');
}

// ── Prepare display data ──────────────────────────────────────────────────────
$isAchievement = $cert['cert_type'] === 'achievement';
$rank          = (int)($cert['rank'] ?? 0);
$rankLabels    = [1 => 'FIRST PLACE', 2 => 'SECOND PLACE', 3 => 'THIRD PLACE'];
$rankLabel     = $rankLabels[$rank] ?? '';

$rankColors = [
    1 => ['primary' => '#B8860B', 'secondary' => '#FFD700', 'bg' => 'linear-gradient(135deg, #1a0a00 0%, #3d1f00 50%, #1a0a00 100%)', 'accent' => '#FFD700'],
    2 => ['primary' => '#696969', 'secondary' => '#C0C0C0', 'bg' => 'linear-gradient(135deg, #0a0a0a 0%, #2d2d2d 50%, #0a0a0a 100%)', 'accent' => '#C0C0C0'],
    3 => ['primary' => '#8B4513', 'secondary' => '#CD7F32', 'bg' => 'linear-gradient(135deg, #0f0500 0%, #2d1500 50%, #0f0500 100%)', 'accent' => '#CD7F32'],
];

if ($isAchievement) {
    $colors  = $rankColors[$rank] ?? $rankColors[1];
    $bgStyle = $colors['bg'];
    $borderColor = $colors['accent'];
    $accentColor = $colors['primary'];
    $titleText = 'CERTIFICATE OF ACHIEVEMENT';
    $bodyText  = 'has demonstrated outstanding performance and secured';
} else {
    $bgStyle     = 'linear-gradient(135deg, #0a0a2e 0%, #1a1a4e 50%, #0a0a2e 100%)';
    $borderColor = '#4A90D9';
    $accentColor = '#2C5F8A';
    $titleText   = 'CERTIFICATE OF PARTICIPATION';
    $bodyText    = 'has successfully participated in';
}

$eventDate    = date('F j, Y', strtotime($cert['event_date']));
$issuedDate   = date('F j, Y', strtotime($cert['issued_at']));
$collegeName  = htmlspecialchars($cert['college_name'] ?? 'EventEase College');
$signatoryName  = htmlspecialchars($cert['signatory_name']  ?? 'Event Coordinator');
$signatoryTitle = htmlspecialchars($cert['signatory_title'] ?? 'Event Coordinator');
$organizerName  = htmlspecialchars($cert['organizer_name']  ?? $cert['event_organizer'] ?? 'EventEase');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isAchievement ? 'Achievement' : 'Participation'; ?> Certificate – <?php echo htmlspecialchars($cert['student_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #1a1a2e;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .controls {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-ctrl {
            padding: 10px 24px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.4); }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-back:hover { background: rgba(255,255,255,0.2); }

        /* ── CERTIFICATE ── */
        .certificate-wrapper {
            width: 100%;
            max-width: 900px;
        }

        .certificate {
            width: 100%;
            aspect-ratio: 1.414 / 1; /* A4 landscape */
            background: <?php echo $bgStyle; ?>;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 40px 60px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.7), 0 0 0 2px <?php echo $borderColor; ?>40;
            overflow: hidden;
        }

        /* Outer ornate border */
        .cert-border-outer {
            position: absolute;
            inset: 14px;
            border: 3px solid <?php echo $borderColor; ?>;
            pointer-events: none;
        }
        .cert-border-inner {
            position: absolute;
            inset: 20px;
            border: 1px solid <?php echo $borderColor; ?>60;
            pointer-events: none;
        }

        /* Corner ornaments */
        .corner {
            position: absolute;
            width: 50px;
            height: 50px;
            border-color: <?php echo $borderColor; ?>;
            border-style: solid;
        }
        .corner-tl { top: 10px; left: 10px; border-width: 3px 0 0 3px; }
        .corner-tr { top: 10px; right: 10px; border-width: 3px 3px 0 0; }
        .corner-bl { bottom: 10px; left: 10px; border-width: 0 0 3px 3px; }
        .corner-br { bottom: 10px; right: 10px; border-width: 0 3px 3px 0; }

        /* Background watermark */
        .cert-watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            opacity: 0.04;
            font-family: 'Cinzel', serif;
            font-size: 80px;
            font-weight: 900;
            color: white;
            letter-spacing: 8px;
            text-align: center;
            line-height: 1.1;
        }

        /* Top section */
        .cert-top {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .cert-logo {
            font-size: 28px;
            color: <?php echo $borderColor; ?>;
            margin-bottom: 4px;
        }

        .cert-college {
            font-family: 'Cinzel', serif;
            font-size: 13px;
            color: <?php echo $borderColor; ?>cc;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .cert-divider {
            width: 120px;
            height: 1px;
            background: linear-gradient(90deg, transparent, <?php echo $borderColor; ?>, transparent);
            margin: 8px auto;
        }

        .cert-title {
            font-family: 'Cinzel', serif;
            font-size: clamp(16px, 3vw, 24px);
            font-weight: 900;
            color: <?php echo $borderColor; ?>;
            letter-spacing: 6px;
            text-transform: uppercase;
            text-shadow: 0 0 30px <?php echo $borderColor; ?>80;
        }

        /* Middle section */
        .cert-middle {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 20px 0;
        }

        .cert-presented {
            font-family: 'EB Garamond', serif;
            font-style: italic;
            font-size: clamp(13px, 2vw, 17px);
            color: rgba(255,255,255,0.65);
            letter-spacing: 1px;
        }

        .cert-name {
            font-family: 'Cinzel', serif;
            font-size: clamp(22px, 5vw, 42px);
            font-weight: 700;
            color: white;
            letter-spacing: 2px;
            text-shadow: 0 2px 20px rgba(255,255,255,0.3);
            position: relative;
        }

        .cert-name::after {
            content: '';
            display: block;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, <?php echo $borderColor; ?>, transparent);
            margin: 6px auto 0;
        }

        .cert-body-text {
            font-family: 'EB Garamond', serif;
            font-size: clamp(13px, 2vw, 18px);
            color: rgba(255,255,255,0.75);
            text-align: center;
            max-width: 85%;
        }

        .cert-event-name {
            font-family: 'Cinzel', serif;
            font-size: clamp(14px, 2.5vw, 22px);
            font-weight: 600;
            color: <?php echo $borderColor; ?>;
            letter-spacing: 1px;
        }

        .cert-rank-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: <?php echo $borderColor; ?>20;
            border: 2px solid <?php echo $borderColor; ?>;
            border-radius: 6px;
            padding: 8px 24px;
            margin: 4px 0;
        }

        .cert-rank-text {
            font-family: 'Cinzel', serif;
            font-size: clamp(14px, 2.5vw, 20px);
            font-weight: 900;
            color: <?php echo $borderColor; ?>;
            letter-spacing: 4px;
        }

        .cert-date-info {
            font-family: 'EB Garamond', serif;
            font-size: clamp(12px, 1.8vw, 15px);
            color: rgba(255,255,255,0.5);
            letter-spacing: 1px;
        }

        /* Bottom section */
        .cert-bottom {
            width: 100%;
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .cert-signature-block {
            text-align: center;
            min-width: 180px;
        }

        .cert-sig-line {
            width: 100%;
            height: 1px;
            background: <?php echo $borderColor; ?>80;
            margin-bottom: 6px;
        }

        .cert-sig-name {
            font-family: 'Cinzel', serif;
            font-size: clamp(9px, 1.2vw, 12px);
            color: white;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .cert-sig-title {
            font-family: 'EB Garamond', serif;
            font-size: clamp(9px, 1.2vw, 11px);
            color: rgba(255,255,255,0.5);
            font-style: italic;
        }

        .cert-id-block {
            text-align: center;
        }

        .cert-id-label {
            font-size: clamp(8px, 1vw, 10px);
            color: rgba(255,255,255,0.35);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .cert-id-value {
            font-family: 'Inter', monospace;
            font-size: clamp(9px, 1.2vw, 11px);
            color: <?php echo $borderColor; ?>aa;
            letter-spacing: 1px;
        }

        <?php if ($isAchievement): ?>
        /* Glow effect for achievement */
        .certificate::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 0%, <?php echo $borderColor; ?>20 0%, transparent 60%);
            pointer-events: none;
        }
        <?php endif; ?>

        /* Print styles */
        @media print {
            body { background: white; padding: 0; }
            .controls { display: none !important; }
            .certificate {
                width: 297mm;
                height: 210mm;
                aspect-ratio: unset;
                box-shadow: none;
                max-width: unset;
                page-break-inside: avoid;
            }
            .certificate-wrapper { max-width: unset; width: 297mm; }
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }
    </style>
</head>
<body>

    <!-- Controls (hidden on print) -->
    <div class="controls">
        <a href="my-events.php" class="btn-ctrl btn-back">
            ← Back to My Events
        </a>
        <button onclick="window.print()" class="btn-ctrl btn-print">
            🖨 Download / Print as PDF
        </button>
    </div>

    <div class="certificate-wrapper">
        <div class="certificate" id="certificate">
            <!-- Decorative borders -->
            <div class="cert-border-outer"></div>
            <div class="cert-border-inner"></div>
            <div class="corner corner-tl"></div>
            <div class="corner corner-tr"></div>
            <div class="corner corner-bl"></div>
            <div class="corner corner-br"></div>
            <div class="cert-watermark">EVENT<br>EASE</div>

            <!-- Top -->
            <div class="cert-top">
                <div class="cert-logo">🎓</div>
                <div class="cert-college"><?php echo $collegeName; ?></div>
                <div class="cert-divider"></div>
                <div class="cert-title"><?php echo $titleText; ?></div>
                <div class="cert-divider"></div>
            </div>

            <!-- Middle -->
            <div class="cert-middle">
                <div class="cert-presented">This is to certify that</div>

                <div class="cert-name"><?php echo htmlspecialchars($cert['student_name']); ?></div>

                <div class="cert-body-text"><?php echo $bodyText; ?></div>

                <div class="cert-event-name"><?php echo htmlspecialchars($cert['event_title']); ?></div>

                <?php if ($isAchievement && $rankLabel): ?>
                <div class="cert-rank-badge">
                    <span>🏆</span>
                    <span class="cert-rank-text"><?php echo $rankLabel; ?></span>
                    <span>🏆</span>
                </div>
                <?php endif; ?>

                <div class="cert-date-info">
                    Held on <?php echo $eventDate; ?>
                </div>
            </div>

            <!-- Bottom -->
            <div class="cert-bottom">
                <div class="cert-signature-block">
                    <div class="cert-sig-line"></div>
                    <div class="cert-sig-name"><?php echo $signatoryName; ?></div>
                    <div class="cert-sig-title"><?php echo $signatoryTitle; ?></div>
                </div>

                <div class="cert-id-block">
                    <div class="cert-id-label">Certificate ID</div>
                    <div class="cert-id-value"><?php echo htmlspecialchars($cert['certificate_id']); ?></div>
                    <div class="cert-id-label" style="margin-top:4px;">Issued: <?php echo $issuedDate; ?></div>
                </div>

                <div class="cert-signature-block">
                    <div class="cert-sig-line"></div>
                    <div class="cert-sig-name"><?php echo $organizerName; ?></div>
                    <div class="cert-sig-title">Event Organizer</div>
                </div>
            </div>
        </div>
    </div>

    <div style="color:rgba(255,255,255,0.4); font-size:12px; margin-top:20px; text-align:center;">
        <p>To save as PDF: Click "Download / Print as PDF" → Select "Save as PDF" in your browser's print dialog → Set paper size to A4 Landscape.</p>
    </div>

</body>
</html>
