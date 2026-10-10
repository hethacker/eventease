<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/whatsapp-service.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

$eventId = intval($_POST['event_id'] ?? 0);
$userId  = $_SESSION['user_id'];

if ($eventId <= 0) {
    setFlash('danger', 'Invalid event selected.');
    header('Location: events.php');
    exit;
}

$db = getDB();

// 1. Check if event exists & is active
$stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND status = 'active'");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('danger', 'Event not found or is no longer active.');
    header('Location: events.php');
    exit;
}

// 2. Prevent duplicate registration for this user
$checkStmt = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
$checkStmt->execute([$userId, $eventId]);
if ($checkStmt->fetch()) {
    setFlash('warning', 'You are already registered for this event!');
    header('Location: my-events.php');
    exit;
}

// 3. Determine registration type (individual or team)
$regTypeRequested = trim($_POST['registration_type'] ?? 'individual');
$eventTypeAllowed = $event['event_type'] ?? 'both';

if ($eventTypeAllowed === 'team') {
    $regType = 'team';
} elseif ($eventTypeAllowed === 'individual') {
    $regType = 'individual';
} else {
    $regType = ($regTypeRequested === 'team') ? 'team' : 'individual';
}

$teamName = null;
$teamMembersJson = null;
$teamSize = 1;

if ($regType === 'team') {
    $teamName = trim($_POST['team_name'] ?? '');
    if (empty($teamName)) {
        setFlash('danger', 'Please provide a valid Team Name.');
        header('Location: event-details.php?id=' . $eventId);
        exit;
    }

    $minTeamSize = max(1, intval($event['min_team_size'] ?? 2));
    $maxTeamSize = max($minTeamSize, intval($event['max_team_size'] ?? 4));

    // Parse team members (excluding the logged-in team leader)
    $memberNames      = $_POST['member_name'] ?? [];
    $memberCollegeIds = $_POST['member_college_id'] ?? [];
    $memberEmails     = $_POST['member_email'] ?? [];
    $memberPhones     = $_POST['member_phone'] ?? [];

    $cleanMembers = [];
    if (is_array($memberNames)) {
        for ($i = 0; $i < count($memberNames); $i++) {
            $mName  = trim($memberNames[$i] ?? '');
            $mId    = trim($memberCollegeIds[$i] ?? '');
            $mEmail = trim($memberEmails[$i] ?? '');
            $mPhone = trim($memberPhones[$i] ?? '');

            if ($mName !== '' && $mId !== '') {
                $cleanMembers[] = [
                    'name'       => $mName,
                    'college_id' => $mId,
                    'email'      => $mEmail,
                    'phone'      => $mPhone
                ];
            }
        }
    }

    // Team Leader is counted as 1 member
    $teamSize = 1 + count($cleanMembers);

    if ($teamSize < $minTeamSize) {
        setFlash('danger', "A minimum of {$minTeamSize} members is required for this event (including Team Leader). Please add more team members.");
        header('Location: event-details.php?id=' . $eventId);
        exit;
    }

    if ($teamSize > $maxTeamSize) {
        setFlash('danger', "Maximum allowed team size is {$maxTeamSize} members.");
        header('Location: event-details.php?id=' . $eventId);
        exit;
    }

    $teamMembersJson = json_encode($cleanMembers, JSON_UNESCAPED_UNICODE);
}

// 4. Check seat availability
$remaining = $event['capacity'] - $event['registered_count'];
if ($remaining < $teamSize) {
    setFlash('danger', "Sorry, not enough seats remaining. Only {$remaining} seat(s) available, but your registration requires {$teamSize}.");
    header('Location: event-details.php?id=' . $eventId);
    exit;
}

// 5. Generate unique registration code
$prefix = ($regType === 'team') ? 'EE-TEAM-' : 'EE-2026-';
$registrationCode = $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

// 6. Begin transaction
try {
    $db->beginTransaction();

    // Insert registration with team fields
    $insertStmt = $db->prepare("
        INSERT INTO registrations 
            (user_id, event_id, registration_type, team_name, team_members, team_size, registration_code, qr_code, checkin_status) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $insertStmt->execute([
        $userId,
        $eventId,
        $regType,
        $teamName,
        $teamMembersJson,
        $teamSize,
        $registrationCode,
        $registrationCode
    ]);

    // Increment registered count by team size
    $updateStmt = $db->prepare("UPDATE events SET registered_count = registered_count + ? WHERE id = ?");
    $updateStmt->execute([$teamSize, $eventId]);

    $db->commit();

    // 7. WhatsApp registration notification (non-blocking)
    try {
        $waSvc = new WhatsAppService();
        $waSvc->notifyRegistration($userId, $eventId);
    } catch (Exception $e) {
        error_log('WhatsApp notification error: ' . $e->getMessage());
    }

    if ($regType === 'team') {
        setFlash('success', "Team '{$teamName}' registered successfully! Your Team QR Pass for all {$teamSize} members is ready below.");
    } else {
        setFlash('success', 'Registration successful! Your unique QR pass is ready below.');
    }
    
    header('Location: my-events.php');
    exit;

} catch (Exception $e) {
    $db->rollBack();
    error_log('Registration Error: ' . $e->getMessage());
    setFlash('danger', 'Registration failed due to a database error. Please try again.');
    header('Location: event-details.php?id=' . $eventId);
    exit;
}
