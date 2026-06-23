<?php
/**
 * admin.php — Simple password-protected admin panel.
 *
 * Lets you:
 *  - edit a product's stock and price
 *  - reset all stock back to original seed values
 *  - view all orders that have been placed
 *
 * This is intentionally simple (one shared password, no user accounts)
 * since it's meant for a single store owner managing their own site,
 * not a multi-admin system.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';

$pdo = get_db();

// ---------- login ----------
if (isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
    } else {
        $_SESSION['admin_login_error'] = 'Incorrect password.';
    }
    header('Location: admin.php');
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header('Location: admin.php');
    exit;
}

$isAdmin = !empty($_SESSION['is_admin']);

// ---------- admin actions (only reachable once logged in) ----------
$flash = null;

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_product') {
        $id = (int)($_POST['product_id'] ?? 0);
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $priceDollars = (float)($_POST['price'] ?? 0);
        $priceCents = (int)round($priceDollars * 100);

        $stmt = $pdo->prepare('UPDATE products SET stock = ?, price_cents = ? WHERE id = ?');
        $stmt->execute([$stock, $priceCents, $id]);
        $flash = ['type' => 'success', 'text' => 'Product updated.'];

    } elseif ($action === 'reset_all_stock') {
        // Wipes and re-seeds everything -- products AND clears order history.
        $pdo->exec('DROP TABLE IF EXISTS order_items');
        $pdo->exec('DROP TABLE IF EXISTS orders');
        $pdo->exec('DROP TABLE IF EXISTS products');
        init_schema($pdo);
        seed_products($pdo);
        cart_clear();
        $flash = ['type' => 'success', 'text' => 'Store reset to original seed data. All orders cleared.'];
    }

    header('Location: admin.php');
    exit;
}

// Flash messages survive exactly one redirect via session.
if (isset($_SESSION['admin_login_error'])) {
    $flash = ['type' => 'error', 'text' => $_SESSION['admin_login_error']];
    unset($_SESSION['admin_login_error']);
}

$products = $isAdmin ? $pdo->query('SELECT * FROM products ORDER BY id ASC')->fetchAll() : [];
$orders = $isAdmin ? $pdo->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll() : [];

// Pre-load order items grouped by order_id for display.
$orderItemsByOrder = [];
if ($isAdmin && $orders) {
    $allItems = $pdo->query('SELECT * FROM order_items ORDER BY id ASC')->fetchAll();
    foreach ($allItems as $item) {
        $orderItemsByOrder[$item['order_id']][] = $item;
    }
}

$pageTitle = 'Admin';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="padding-top:40px; padding-bottom:70px;">

<?php if (!$isAdmin): ?>

    <div class="section-head" style="margin-top:0;">
        <h2><span class="dot">//</span> Admin login</h2>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="admin.php" class="form-box">
        <div class="field">
            <label for="admin_password">Password</label>
            <input type="password" id="admin_password" name="admin_password" required autofocus>
        </div>
        <button type="submit" class="btn btn-add">Log in</button>
    </form>

<?php else: ?>

    <div class="section-head" style="margin-top:0;">
        <h2><span class="dot">//</span> Admin panel</h2>
        <a href="admin.php?logout=1" class="btn btn-ghost" style="padding:8px 14px; text-decoration:none;">Log out</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>

    <!-- ---------- Products ---------- -->
    <h3 style="font-family:var(--mono); color:var(--ink-dim); text-transform:uppercase; font-size:0.9rem; letter-spacing:1px;">
        Products
    </h3>

    <table class="cart-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Price ($)</th>
                <th>Stock</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td>
                    <span class="cart-item-emoji"><?= $p['image_emoji'] ?></span>
                    <span class="cart-item-name"><?= htmlspecialchars($p['name']) ?></span>
                </td>
                <td colspan="3">
                    <form method="post" action="admin.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="update_product">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <span style="font-family:var(--mono); color:var(--ink-dim);">$</span>
                        <input type="number" step="0.01" min="0" name="price"
                               value="<?= number_format($p['price_cents'] / 100, 2) ?>"
                               class="qty-input" style="width:80px;">
                        <input type="number" min="0" name="stock" value="<?= (int)$p['stock'] ?>"
                               class="qty-input" title="Stock">
                        <span class="stock-note <?= $p['stock'] <= 0 ? '' : '' ?>" style="<?= (int)$p['stock'] <= 0 ? 'color:var(--danger);' : '' ?>">
                            <?= (int)$p['stock'] <= 0 ? 'Sold out' : (int)$p['stock'] . ' in stock' ?>
                        </span>
                        <button type="submit" class="btn btn-ghost" style="padding:6px 14px;">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" action="admin.php" style="margin-top:18px;"
          onsubmit="return confirm('This resets ALL stock/prices to the original seed values and deletes every order. Are you sure?');">
        <input type="hidden" name="action" value="reset_all_stock">
        <button type="submit" class="btn btn-danger">Reset entire store to seed data</button>
    </form>

    <!-- ---------- Orders ---------- -->
    <h3 style="font-family:var(--mono); color:var(--ink-dim); text-transform:uppercase; font-size:0.9rem; letter-spacing:1px; margin-top:48px;">
        Orders (<?= count($orders) ?>)
    </h3>

    <?php if (empty($orders)): ?>
        <p style="color:var(--ink-dim);">No orders placed yet.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="cart-summary-box" style="width:100%; max-width:640px; margin-bottom:16px; text-align:left;">
                <div class="cart-summary-row" style="font-weight:700; color:var(--ink);">
                    <span>Order #<?= (int)$order['id'] ?> — <?= htmlspecialchars($order['customer_name']) ?></span>
                    <span><?= htmlspecialchars($order['created_at']) ?></span>
                </div>
                <div class="cart-summary-row" style="color:var(--ink-dim); font-size:0.8rem;">
                    <span><?= htmlspecialchars($order['customer_email']) ?></span>
                    <span></span>
                </div>
                <?php foreach ($orderItemsByOrder[$order['id']] ?? [] as $item): ?>
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
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
