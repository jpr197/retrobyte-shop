<?php
/**
 * cart.php — Shopping cart review page.
 * Lets the customer update quantities or remove items, then proceeds
 * to checkout. Quantities are always clamped to live stock.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_qty') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);

        // Clamp to available stock so a customer can't request more than exists.
        $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        if ($row) {
            $qty = min($qty, (int)$row['stock']);
        }
        cart_set_qty($productId, $qty);
    } elseif ($action === 'remove') {
        cart_remove((int)($_POST['product_id'] ?? 0));
    }

    header('Location: cart.php');
    exit;
}

$details = cart_details($pdo);
$total = cart_total_cents($details);
$pageTitle = 'Your cart';

$orderError = $_SESSION['order_error'] ?? null;
unset($_SESSION['order_error']);

require __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="padding-top:40px;">
    <div class="section-head" style="margin-top:0;">
        <h2><span class="dot">//</span> Your cart</h2>
        <span class="count"><?= count($details) ?> item<?= count($details) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($orderError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($orderError) ?> Please review your cart and try again.</div>
    <?php endif; ?>

    <?php if (empty($details)): ?>
        <div class="cart-empty">
            <div class="big">🛒</div>
            <p>Your cart is empty. Time to fix that.</p>
            <a href="index.php" class="btn btn-add" style="display:inline-block;width:auto;padding:10px 22px;">Browse the catalog</a>
        </div>
    <?php else: ?>

        <table class="cart-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($details as $row):
                $p = $row['product'];
            ?>
                <tr>
                    <td>
                        <span class="cart-item-emoji"><?= $p['image_emoji'] ?></span>
                        <span class="cart-item-name"><?= htmlspecialchars($p['name']) ?></span>
                    </td>
                    <td><?= format_price((int)$p['price_cents']) ?></td>
                    <td>
                        <form method="post" action="cart.php" style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="action" value="update_qty">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <input type="number" name="quantity" value="<?= $row['quantity'] ?>"
                                   min="0" max="<?= (int)$p['stock'] ?>" class="qty-input">
                            <button type="submit" class="btn btn-ghost" style="padding:6px 10px;">Update</button>
                        </form>
                    </td>
                    <td><?= format_price($row['line_total_cents']) ?></td>
                    <td>
                        <form method="post" action="cart.php">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding:6px 10px;">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
            <div class="cart-summary-box">
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span><?= format_price($total) ?></span>
                </div>
                <div class="cart-summary-row total">
                    <span>Total</span>
                    <span><?= format_price($total) ?></span>
                </div>
                <a href="checkout.php" class="btn btn-add" style="display:block; text-align:center; margin-top:14px; text-decoration:none;">
                    Proceed to checkout
                </a>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
