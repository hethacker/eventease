<?php
/**
 * EventEase WhatsApp Configuration
 * Store this file outside the public root in production.
 * Path: config/whatsapp.php
 *
 * Supported providers:
 *  - 'twilio'   : Twilio Sandbox for WhatsApp (easy to test)
 *  - 'meta'     : Official Meta Cloud API (production)
 *  - 'none'     : Disabled (in-app notifications only)
 */

define('WA_PROVIDER',          'none');          // 'none' | 'twilio' | 'meta'

// ── Twilio WhatsApp Sandbox ────────────────────────────────
define('TWILIO_ACCOUNT_SID',   'YOUR_TWILIO_ACCOUNT_SID');
define('TWILIO_AUTH_TOKEN',    'YOUR_TWILIO_AUTH_TOKEN');
define('TWILIO_FROM_NUMBER',   'whatsapp:+14155238886');  // Twilio sandbox number

// ── Meta Cloud API (Official WhatsApp Business) ───────────
define('META_ACCESS_TOKEN',    'YOUR_META_PERMANENT_ACCESS_TOKEN');
define('META_PHONE_NUMBER_ID', 'YOUR_META_PHONE_NUMBER_ID');
define('META_API_VERSION',     'v19.0');

// ── App URL (used in notification links) ──────────────────
define('APP_BASE_URL', 'http://localhost/hackathon');
