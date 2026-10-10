<?php
/**
 * EventEase - WhatsApp Notification Service
 * Handles sending WhatsApp messages via configured provider.
 * Logs all attempts to the whatsapp_notifications table.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/whatsapp.php';

class WhatsAppService {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ── Public notification triggers ──────────────────────────────────────────

    /**
     * Send registration confirmation WhatsApp message.
     */
    public function notifyRegistration(int $userId, int $eventId): array {
        $data = $this->getRegistrationData($userId, $eventId);
        if (!$data) {
            return ['success' => false, 'reason' => 'Registration data not found.'];
        }

        if (!$this->hasConsent($data)) {
            return ['success' => false, 'reason' => 'Student has not given WhatsApp consent.'];
        }

        $message = "Hello {$data['name']}! Your registration for *{$data['event_title']}* is confirmed. "
            . "📅 Event Date: {$data['event_date']}. 📍 Venue: {$data['venue']}. "
            . "Your Registration ID is *{$data['reg_code']}*. "
            . "Please keep your QR code ready for check-in. Thank you for registering with EventEase!";

        return $this->dispatchNotification(
            $userId, $eventId, null,
            'registration', $data['phone'], $message
        );
    }

    /**
     * Send participation certificate availability notification.
     */
    public function notifyParticipationCert(int $userId, int $eventId, int $certId): array {
        $data = $this->getRegistrationData($userId, $eventId);
        if (!$data) {
            return ['success' => false, 'reason' => 'Data not found.'];
        }

        if (!$this->hasConsent($data)) {
            return ['success' => false, 'reason' => 'No WhatsApp consent.'];
        }

        $link = APP_BASE_URL . '/my-events.php';
        $message = "🎉 Congratulations, {$data['name']}! "
            . "Thank you for participating in *{$data['event_title']}*. "
            . "Your participation certificate is now available. "
            . "Log in to EventEase to download your certificate: {$link}";

        return $this->dispatchNotification(
            $userId, $eventId, $certId,
            'participation_cert', $data['phone'], $message
        );
    }

    /**
     * Send achievement / rank certificate notification.
     */
    public function notifyAchievementCert(int $userId, int $eventId, int $certId, int $rank): array {
        $data = $this->getRegistrationData($userId, $eventId);
        if (!$data) {
            return ['success' => false, 'reason' => 'Data not found.'];
        }

        if (!$this->hasConsent($data)) {
            return ['success' => false, 'reason' => 'No WhatsApp consent.'];
        }

        $link    = APP_BASE_URL . '/my-events.php';
        $message = "🏆 Congratulations, {$data['name']}! "
            . "You secured *Rank {$rank}* in *{$data['event_title']}*. "
            . "Your achievement certificate is ready to download from your EventEase dashboard: {$link}. "
            . "Keep up the great work! 🎓";

        return $this->dispatchNotification(
            $userId, $eventId, $certId,
            'achievement_cert', $data['phone'], $message
        );
    }

    /**
     * Manual retry for a failed notification by its DB id.
     */
    public function retryNotification(int $notificationId): array {
        $stmt = $this->db->prepare("SELECT * FROM whatsapp_notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
        $notif = $stmt->fetch();

        if (!$notif) {
            return ['success' => false, 'reason' => 'Notification record not found.'];
        }

        if ($notif['status'] === 'sent' || $notif['status'] === 'delivered') {
            return ['success' => false, 'reason' => 'Notification already sent/delivered.'];
        }

        if ($notif['retry_count'] >= 5) {
            return ['success' => false, 'reason' => 'Maximum retry limit (5) reached.'];
        }

        // Update retry count and attempt timestamp
        $update = $this->db->prepare(
            "UPDATE whatsapp_notifications SET retry_count = retry_count + 1, last_attempted_at = NOW() WHERE id = ?"
        );
        $update->execute([$notificationId]);

        $result = $this->sendViaProvider($notif['recipient_phone'], $notif['message_body']);
        $status = $result['success'] ? 'sent' : 'failed';

        $this->db->prepare(
            "UPDATE whatsapp_notifications SET status = ?, provider_response = ?, provider_message_id = ? WHERE id = ?"
        )->execute([$status, json_encode($result), $result['message_id'] ?? null, $notificationId]);

        return $result;
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function dispatchNotification(
        int $userId, int $eventId, ?int $certId,
        string $type, string $phone, string $message
    ): array {
        // Idempotency: check if we already sent this exact notification type
        $check = $this->db->prepare(
            "SELECT id, status FROM whatsapp_notifications WHERE user_id = ? AND event_id = ? AND notification_type = ?"
        );
        $check->execute([$userId, $eventId, $type]);
        $existing = $check->fetch();

        if ($existing && in_array($existing['status'], ['sent', 'delivered'])) {
            return ['success' => false, 'reason' => 'Notification already sent (duplicate prevention).'];
        }

        $providerResult = $this->sendViaProvider($phone, $message);
        $status         = $providerResult['success'] ? 'sent' : ($providerResult['skipped'] ? 'skipped' : 'failed');

        if ($existing) {
            // Update existing failed/pending record
            $this->db->prepare(
                "UPDATE whatsapp_notifications
                 SET status = ?, message_body = ?, provider_response = ?, provider_message_id = ?,
                     retry_count = retry_count + 1, last_attempted_at = NOW()
                 WHERE id = ?"
            )->execute([
                $status, $message,
                json_encode($providerResult),
                $providerResult['message_id'] ?? null,
                $existing['id']
            ]);
        } else {
            // Insert new log
            $this->db->prepare(
                "INSERT INTO whatsapp_notifications
                 (user_id, event_id, certificate_id, notification_type, recipient_phone, message_body, status, provider_response, provider_message_id, last_attempted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([
                $userId, $eventId, $certId, $type, $phone, $message,
                $status, json_encode($providerResult), $providerResult['message_id'] ?? null
            ]);
        }

        return $providerResult;
    }

    private function sendViaProvider(string $phone, string $message): array {
        $provider = WA_PROVIDER;

        if ($provider === 'none') {
            return [
                'success'  => false,
                'skipped'  => true,
                'message_id' => null,
                'info'     => 'WhatsApp integration not configured. Set WA_PROVIDER in config/whatsapp.php.',
            ];
        }

        if ($provider === 'twilio') {
            return $this->sendViaTwilio($phone, $message);
        }

        if ($provider === 'meta') {
            return $this->sendViaMeta($phone, $message);
        }

        return ['success' => false, 'skipped' => false, 'info' => 'Unknown provider configured.'];
    }

    private function sendViaTwilio(string $phone, string $message): array {
        // Sanitise number
        $toNumber = 'whatsapp:+' . preg_replace('/[^0-9]/', '', $phone);
        $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

        $payload = http_build_query([
            'From' => TWILIO_FROM_NUMBER,
            'To'   => $toNumber,
            'Body' => $message,
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'skipped' => false, 'info' => 'cURL error: ' . $curlError];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['sid'])) {
            return ['success' => true, 'skipped' => false, 'message_id' => $decoded['sid'], 'raw' => $response];
        }

        return ['success' => false, 'skipped' => false, 'message_id' => null, 'info' => $decoded['message'] ?? 'Twilio error', 'raw' => $response];
    }

    private function sendViaMeta(string $phone, string $message): array {
        $toNumber = preg_replace('/[^0-9]/', '', $phone);
        $endpoint = 'https://graph.facebook.com/' . META_API_VERSION . '/' . META_PHONE_NUMBER_ID . '/messages';

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $toNumber,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . META_ACCESS_TOKEN,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'skipped' => false, 'info' => 'cURL error: ' . $curlError];
        }

        $decoded = json_decode($response, true);

        if ($httpCode === 200 && isset($decoded['messages'][0]['id'])) {
            return ['success' => true, 'skipped' => false, 'message_id' => $decoded['messages'][0]['id'], 'raw' => $response];
        }

        return ['success' => false, 'skipped' => false, 'message_id' => null, 'info' => $decoded['error']['message'] ?? 'Meta API error', 'raw' => $response];
    }

    private function hasConsent(array $data): bool {
        return !empty($data['whatsapp_consent']) && (int)$data['whatsapp_consent'] === 1;
    }

    private function getRegistrationData(int $userId, int $eventId): ?array {
        $stmt = $this->db->prepare("
            SELECT r.registration_code as reg_code, r.id as registration_id,
                   u.name, u.phone, u.whatsapp_consent,
                   COALESCE(u.whatsapp_number, u.phone) as phone,
                   e.title as event_title, e.venue,
                   DATE_FORMAT(e.event_date, '%d %M %Y') as event_date
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            JOIN events e ON r.event_id = e.id
            WHERE r.user_id = ? AND r.event_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $eventId]);
        return $stmt->fetch() ?: null;
    }
}
