<?php
/**
 * config.php
 * Central place for store settings.
 *
 * IMPORTANT: Change OWNER_EMAIL to your real email address before
 * deploying -- this is the address that receives a notification
 * every time a customer completes a purchase.
 */

define('STORE_NAME', 'RetroByte');
define('STORE_TAGLINE', 'Tomorrow\'s tech, yesterday\'s style.');

// 👉 Change this to the email address that should receive order notifications.
define('OWNER_EMAIL', 'owner@example.com');

// The "From" address used when sending notification emails.
// Many hosts require this to match a real domain/mailbox on the server.
define('MAIL_FROM', 'orders@retrobyte.example.com');

// 👉 Change this before deploying anywhere other than your own machine.
// This is the password required to reach admin.php.
define('ADMIN_PASSWORD', 'changeme123');

session_start();
