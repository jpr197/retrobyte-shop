<?php
/**
 * mailer.php
 * Sends an order notification email to the store owner using PHP's
 * built-in mail() function. This requires the server to have a working
 * mail transport (sendmail/postfix) configured -- most shared hosts
 * have this out of the box. For local testing without a mail server,
 * every email is also logged to data/email_log.txt so you can see
 * exactly what would have been sent.
 */

function send_order_notification(array $order, array $items): bool
{
    $subject = '🛒 New order #' . $order['id'] . ' on ' . STORE_NAME;

    $lines = [];
    $lines[] = "New order received!";
    $lines[] = "";
    $lines[] = "Order #: " . $order['id'];
    $lines[] = "Customer: " . $order['customer_name'];
    $lines[] = "Email: " . $order['customer_email'];
    $lines[] = "Placed at: " . $order['created_at'];
    $lines[] = "";
    $lines[] = "Items:";
    foreach ($items as $item) {
        $lines[] = sprintf(
            "  - %s x%d @ %s = %s",
            $item['product']['name'],
            $item['quantity'],
            format_price((int)$item['product']['price_cents']),
            format_price($item['line_total_cents'])
        );
    }
    $lines[] = "";
    $lines[] = "Total: " . format_price($order['total_cents']);

    $body = implode("\n", $lines);

    $headers = [
        'From: ' . MAIL_FROM,
        'Reply-To: ' . $order['customer_email'],
        'Content-Type: text/plain; charset=UTF-8',
    ];

    // Always log locally -- handy for local dev/testing and as an audit trail.
    log_email_locally($subject, $body);

    // Attempt to actually send via the server's mail transport.
    // Suppress warnings here since many dev environments lack sendmail;
    // the local log above ensures nothing is silently lost.
    return @mail(OWNER_EMAIL, $subject, $body, implode("\r\n", $headers));
}

function log_email_locally(string $subject, string $body): void
{
    $logFile = __DIR__ . '/../data/email_log.txt';
    $entry = "===== " . date('Y-m-d H:i:s') . " =====\n"
           . "To: " . OWNER_EMAIL . "\n"
           . "Subject: $subject\n\n"
           . "$body\n\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}
