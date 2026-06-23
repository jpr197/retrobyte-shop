<?php
/**
 * order_confirmation.php — Shown right after a successful purchase.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';

$pdo = get_db();

$orderId = $_SESSION['last_order_id'] ?? null;
if (!$orderId) {
    header('Location: index.php');
    exit;
}

$orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'Order confirmed';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
    <div class="confirm-box">
        <div class="confirm-icon">✅</div>
        <h1 style="font-family:var(--mono);">Order placed!</h1>
        <p style="color:var(--ink-dim);">Thanks, <?= htmlspecialchars($order['customer_name']) ?> — a confirmation
            was sent to <?= htmlspecialchars($order['customer_email']) ?>.</p>
        <p class="order-id">Order #<?= (int)$order['id'] ?></p>

        <div class="cart-summary-box" style="width:100%; max-width:420px; margin:28px auto 0; text-align:left;">
            <?php foreach ($items as $item): ?>
                <div class="cart-summary-row">
                    <span><?= htmlspecialchars($item['product_name']) ?> &times;<?= (int)$item['quantity'] ?></span>
                    <span><?= format_price((int)$item['unit_price_cents'] * (int)$item['quantity']) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="cart-summary-row total">
                <span>Total</span>
                <span><?= format_price((int)$order['total_cents']) ?></span>
            </div>
        </div>

        <a href="index.php" class="btn btn-add" style="display:inline-block; width:auto; padding:11px 26px; margin-top:30px; text-decoration:none;">
            Back to shop
        </a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
