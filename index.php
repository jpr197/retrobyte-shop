<?php
/**
 * index.php — Storefront home page.
 * Lists every product with price + live availability. Items at 0 stock
 * are visually stamped "SOLD OUT" and their Add to Cart button is disabled.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';

$pdo = get_db();

// Handle "Add to cart" form submissions (POST so re-loading the page
// never silently re-adds an item).
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $productId = (int)($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $flash = ['type' => 'error', 'text' => 'That product could not be found.'];
    } elseif ((int)$product['stock'] <= 0) {
        $flash = ['type' => 'error', 'text' => $product['name'] . ' is sold out.'];
    } else {
        $currentInCart = cart_get()[$productId] ?? 0;
        if ($currentInCart + 1 > (int)$product['stock']) {
            $flash = ['type' => 'error', 'text' => 'Only ' . $product['stock'] . ' left — you already have the max in your cart.'];
        } else {
            cart_add($productId, 1);
            $flash = ['type' => 'success', 'text' => $product['name'] . ' added to your cart.'];
        }
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY id ASC')->fetchAll();
$pageTitle = 'Shop';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="terminal">
        <div class="line">$ booting retrobyte_storefront.sh</div>
        <div class="line">$ inventory check.... <span style="color:var(--ink-dim)">OK</span></div>
        <h1>Tech that already had its retro phase.</h1>
        <div class="sub">Hand-picked gadgets that look backward and work forward.<span class="cursor"></span></div>
    </div>
</section>

<div class="wrap">

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
        <?= htmlspecialchars($flash['text']) ?>
    </div>
<?php endif; ?>

<div class="section-head">
    <h2><span class="dot">//</span> Catalog</h2>
    <span class="count"><?= count($products) ?> items</span>
</div>

<div class="grid">
<?php foreach ($products as $p):
    $stock = (int)$p['stock'];
    $soldOut = $stock <= 0;
    $lowStock = !$soldOut && $stock <= 3;
?>
    <div class="card <?= $soldOut ? 'is-sold-out' : '' ?>">
        <?php if ($soldOut): ?><div class="sold-stamp">Sold out</div><?php endif; ?>
        <div class="card-art"><?= $p['image_emoji'] ?></div>
        <div class="card-body">
            <p class="card-name"><?= htmlspecialchars($p['name']) ?></p>
            <p class="card-desc"><?= htmlspecialchars($p['description']) ?></p>
            <div class="card-foot">
                <span class="price"><?= format_price((int)$p['price_cents']) ?></span>
                <?php if ($soldOut): ?>
                    <span class="stock-note" style="color:var(--danger);">0 in stock</span>
                <?php elseif ($lowStock): ?>
                    <span class="stock-note low">only <?= $stock ?> left</span>
                <?php else: ?>
                    <span class="stock-note"><?= $stock ?> in stock</span>
                <?php endif; ?>
            </div>
            <form method="post" action="index.php#">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn btn-add" <?= $soldOut ? 'disabled' : '' ?>>
                    <?= $soldOut ? 'Sold out' : 'Add to cart' ?>
                </button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
