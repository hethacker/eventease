<?php
/**
 * EventEase - Certificate Service
 * Handles certificate creation, unique ID generation, eligibility checks,
 * and secure certificate download URLs.
 */

require_once __DIR__ . '/../config/database.php';

class CertificateService {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Check if a student is eligible for a participation certificate.
     * Requires: event ended + student checked in + certs_issued flag set.
     */
    public function isEligibleForParticipation(int $userId, int $eventId): array {
        $stmt = $this->db->prepare("
            SELECT r.checkin_status, r.id as reg_id,
                   e.event_date, e.status as event_status,
                   COALESCE(cs.certs_issued, 0) as certs_issued
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            LEFT JOIN certificate_settings cs ON cs.event_id = e.id
            WHERE r.user_id = ? AND r.event_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $eventId]);
        $data = $stmt->fetch();

        if (!$data) {
            return ['eligible' => false, 'reason' => 'No registration found.'];
        }

        if ($data['checkin_status'] !== 'checked_in') {
            return ['eligible' => false, 'reason' => 'Student did not check in.'];
        }

        $eventOver = (strtotime($data['event_date']) < strtotime('today'));
        if (!$eventOver) {
            return ['eligible' => false, 'reason' => 'Event has not ended yet.'];
        }

        if (!$data['certs_issued']) {
            return ['eligible' => false, 'reason' => 'Certificates not yet issued for this event.'];
        }

        return ['eligible' => true, 'reg_id' => $data['reg_id']];
    }

    /**
     * Get (or create) a participation certificate record for a student.
     * Returns the certificate row or null.
     */
    public function getOrCreateParticipationCert(int $userId, int $eventId): ?array {
        $eligibility = $this->isEligibleForParticipation($userId, $eventId);
        if (!$eligibility['eligible']) {
            return null;
        }

        // Check for existing cert
        $existing = $this->getCertificate($userId, $eventId, 'participation');
        if ($existing) {
            return $existing;
        }

        // Get certificate settings
        $settings = $this->getEventSettings($eventId);

        // Create new cert
        $certId = $this->generateCertificateId();
        $this->db->prepare("
            INSERT INTO certificates
            (certificate_id, user_id, event_id, registration_id, cert_type,
             college_name, organizer_name, signatory_name, signatory_title)
            VALUES (?, ?, ?, ?, 'participation', ?, ?, ?, ?)
        ")->execute([
            $certId, $userId, $eventId, $eligibility['reg_id'],
            $settings['college_name']    ?? 'EventEase College',
            $settings['organizer_name']  ?? null,
            $settings['signatory_name']  ?? null,
            $settings['signatory_title'] ?? 'Event Coordinator',
        ]);

        return $this->getCertificate($userId, $eventId, 'participation');
    }

    /**
     * Get (or create) an achievement certificate for a ranked student.
     */
    public function getOrCreateAchievementCert(int $userId, int $eventId): ?array {
        // Check ranking
        $rankStmt = $this->db->prepare("
            SELECT er.id as ranking_id, er.rank, er.registration_id,
                   er.published
            FROM event_rankings er
            WHERE er.user_id = ? AND er.event_id = ?
            LIMIT 1
        ");
        $rankStmt->execute([$userId, $eventId]);
        $ranking = $rankStmt->fetch();

        if (!$ranking || !$ranking['published']) {
            return null;
        }

        // Event must be over
        $eventStmt = $this->db->prepare("SELECT event_date FROM events WHERE id = ?");
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch();
        if (!$event || strtotime($event['event_date']) >= strtotime('today')) {
            return null;
        }

        // Check for existing cert
        $existing = $this->getCertificate($userId, $eventId, 'achievement');
        if ($existing) {
            return $existing;
        }

        // Get settings
        $settings = $this->getEventSettings($eventId);
        $certId   = $this->generateCertificateId();

        $this->db->prepare("
            INSERT INTO certificates
            (certificate_id, user_id, event_id, registration_id, ranking_id,
             cert_type, rank, college_name, organizer_name, signatory_name, signatory_title)
            VALUES (?, ?, ?, ?, ?, 'achievement', ?, ?, ?, ?, ?)
        ")->execute([
            $certId, $userId, $eventId,
            $ranking['registration_id'], $ranking['ranking_id'],
            $ranking['rank'],
            $settings['college_name']    ?? 'EventEase College',
            $settings['organizer_name']  ?? null,
            $settings['signatory_name']  ?? null,
            $settings['signatory_title'] ?? 'Event Coordinator',
        ]);

        return $this->getCertificate($userId, $eventId, 'achievement');
    }

    /**
     * Retrieve certificate by user, event, and type.
     */
    public function getCertificate(int $userId, int $eventId, string $type): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name as student_name, u.college_id, u.department, u.year,
                   e.title as event_title, e.event_date, e.organizer as event_organizer
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN events e ON c.event_id = e.id
            WHERE c.user_id = ? AND c.event_id = ? AND c.cert_type = ? AND c.status = 'issued'
            LIMIT 1
        ");
        $stmt->execute([$userId, $eventId, $type]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Retrieve certificate by its unique certificate_id string (for download validation).
     */
    public function getCertificateById(string $certId): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name as student_name, u.college_id, u.department, u.year,
                   e.title as event_title, e.event_date, e.organizer as event_organizer
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN events e ON c.event_id = e.id
            WHERE c.certificate_id = ? AND c.status = 'issued'
            LIMIT 1
        ");
        $stmt->execute([$certId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get all certificates for a student (for dashboard display).
     */
    public function getStudentCertificates(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT c.*, e.title as event_title, e.event_date, e.category, e.status as event_status
            FROM certificates c
            JOIN events e ON c.event_id = e.id
            WHERE c.user_id = ? AND c.status = 'issued'
            ORDER BY c.issued_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get certificate status for a registration (for dashboard status badges).
     */
    public function getCertificateStatusForRegistration(int $userId, int $eventId): array {
        $eventStmt = $this->db->prepare("
            SELECT e.event_date,
                   COALESCE(cs.certs_issued, 0) as certs_issued,
                   COALESCE(cs.results_published, 0) as results_published
            FROM events e
            LEFT JOIN certificate_settings cs ON cs.event_id = e.id
            WHERE e.id = ?
        ");
        $eventStmt->execute([$eventId]);
        $eventData = $eventStmt->fetch();

        if (!$eventData) {
            return ['status' => 'unknown', 'label' => 'Unknown', 'badge_class' => 'secondary'];
        }

        $eventOver = strtotime($eventData['event_date']) < strtotime('today');

        if (!$eventOver) {
            return ['status' => 'upcoming', 'label' => 'Event Upcoming', 'badge_class' => 'info'];
        }

        // Check check-in status
        $regStmt = $this->db->prepare("SELECT checkin_status FROM registrations WHERE user_id = ? AND event_id = ?");
        $regStmt->execute([$userId, $eventId]);
        $reg = $regStmt->fetch();

        if (!$reg || $reg['checkin_status'] !== 'checked_in') {
            return ['status' => 'not_attended', 'label' => 'Not Attended', 'badge_class' => 'danger'];
        }

        // Check achievement cert first
        $achCert = $this->getCertificate($userId, $eventId, 'achievement');
        if ($achCert) {
            return ['status' => 'achievement', 'label' => 'Achievement Certificate', 'badge_class' => 'warning', 'cert' => $achCert];
        }

        // Check participation cert
        $partCert = $this->getCertificate($userId, $eventId, 'participation');
        if ($partCert) {
            return ['status' => 'participation', 'label' => 'Certificate Available', 'badge_class' => 'success', 'cert' => $partCert];
        }

        if ($eventData['results_published'] && !$eventData['certs_issued']) {
            return ['status' => 'results_pending', 'label' => 'Awaiting Certificate Issue', 'badge_class' => 'warning'];
        }

        if (!$eventData['certs_issued']) {
            return ['status' => 'pending', 'label' => 'Certificate Not Available Yet', 'badge_class' => 'secondary'];
        }

        return ['status' => 'pending', 'label' => 'Processing...', 'badge_class' => 'secondary'];
    }

    /**
     * Bulk issue participation certificates for an event (called by admin).
     * Returns count of newly issued certs.
     */
    public function bulkIssueParticipationCerts(int $eventId, array $settings): int {
        // Get all checked-in students for this event
        $stmt = $this->db->prepare("
            SELECT r.user_id, r.id as reg_id
            FROM registrations r
            WHERE r.event_id = ? AND r.checkin_status = 'checked_in'
        ");
        $stmt->execute([$eventId]);
        $attendees = $stmt->fetchAll();

        $count = 0;
        foreach ($attendees as $att) {
            // Skip if already has a cert
            $existing = $this->getCertificate($att['user_id'], $eventId, 'participation');
            if ($existing) continue;

            $certId = $this->generateCertificateId();
            try {
                $this->db->prepare("
                    INSERT INTO certificates
                    (certificate_id, user_id, event_id, registration_id, cert_type,
                     college_name, organizer_name, signatory_name, signatory_title)
                    VALUES (?, ?, ?, ?, 'participation', ?, ?, ?, ?)
                ")->execute([
                    $certId, $att['user_id'], $eventId, $att['reg_id'],
                    $settings['college_name']    ?? 'EventEase College',
                    $settings['organizer_name']  ?? null,
                    $settings['signatory_name']  ?? null,
                    $settings['signatory_title'] ?? 'Event Coordinator',
                ]);
                $count++;
            } catch (PDOException $e) {
                // Skip duplicates silently
            }
        }

        // Mark certs as issued in settings
        $this->upsertEventSettings($eventId, ['certs_issued' => 1]);

        return $count;
    }

    /**
     * Issue / update achievement certificate for a ranked student.
     */
    public function issueAchievementCert(int $userId, int $eventId, int $rankingId, int $rank, array $settings): ?array {
        // Remove existing achievement cert if any (admin correction)
        $this->db->prepare(
            "DELETE FROM certificates WHERE user_id = ? AND event_id = ? AND cert_type = 'achievement'"
        )->execute([$userId, $eventId]);

        $certId = $this->generateCertificateId();

        // Get registration id
        $regStmt = $this->db->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1");
        $regStmt->execute([$userId, $eventId]);
        $reg = $regStmt->fetch();

        if (!$reg) return null;

        $this->db->prepare("
            INSERT INTO certificates
            (certificate_id, user_id, event_id, registration_id, ranking_id,
             cert_type, rank, college_name, organizer_name, signatory_name, signatory_title)
            VALUES (?, ?, ?, ?, ?, 'achievement', ?, ?, ?, ?, ?)
        ")->execute([
            $certId, $userId, $eventId, $reg['id'], $rankingId,
            $rank,
            $settings['college_name']    ?? 'EventEase College',
            $settings['organizer_name']  ?? null,
            $settings['signatory_name']  ?? null,
            $settings['signatory_title'] ?? 'Event Coordinator',
        ]);

        return $this->getCertificate($userId, $eventId, 'achievement');
    }

    // ── Helper methods ────────────────────────────────────────────────────────

    public function generateCertificateId(): string {
        do {
            $raw    = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            $certId = 'CERT-' . date('Y') . '-' . $raw;
            $stmt   = $this->db->prepare("SELECT id FROM certificates WHERE certificate_id = ?");
            $stmt->execute([$certId]);
        } while ($stmt->fetch());

        return $certId;
    }

    public function getEventSettings(int $eventId): array {
        $stmt = $this->db->prepare("SELECT * FROM certificate_settings WHERE event_id = ?");
        $stmt->execute([$eventId]);
        return $stmt->fetch() ?: [
            'college_name'    => 'EventEase College',
            'organizer_name'  => null,
            'signatory_name'  => null,
            'signatory_title' => 'Event Coordinator',
            'enable_participation' => 1,
            'enable_ranking'  => 1,
            'results_published' => 0,
            'certs_issued'    => 0,
        ];
    }

    public function upsertEventSettings(int $eventId, array $fields): void {
        $existing = $this->db->prepare("SELECT id FROM certificate_settings WHERE event_id = ?");
        $existing->execute([$eventId]);
        if ($existing->fetch()) {
            $setClauses = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
            $values     = array_values($fields);
            $values[]   = $eventId;
            $this->db->prepare("UPDATE certificate_settings SET $setClauses, updated_at = NOW() WHERE event_id = ?")
                ->execute($values);
        } else {
            $fields['event_id'] = $eventId;
            $cols   = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
            $places = implode(', ', array_fill(0, count($fields), '?'));
            $this->db->prepare("INSERT INTO certificate_settings ($cols) VALUES ($places)")
                ->execute(array_values($fields));
        }
    }
}
