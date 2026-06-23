<?php
/**
 * cart.php
 * Session-based shopping cart helpers.
 * Cart is stored as: $_SESSION['cart'][product_id] = quantity
 */

function cart_get(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $productId, int $qty = 1): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qty;
}

function cart_set_qty(int $productId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }
    $_SESSION['cart'][$productId] = $qty;
}

function cart_remove(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_count(): int
{
    return array_sum(cart_get());
}

/**
 * Returns full cart details joined with current product data,
 * clamping quantities to available stock (in case stock changed
 * since the item was added, e.g. someone else bought the last one).
 */
function cart_details(PDO $pdo): array
{
    $cart = cart_get();
    if (empty($cart)) {
        return [];
    }

    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    $details = [];
    foreach ($products as $product) {
        $qty = $cart[$product['id']];
        $clampedQty = min($qty, (int)$product['stock']);
        if ($clampedQty <= 0) {
            continue; // out of stock entirely, drop from displayed cart
        }
        $details[] = [
            'product' => $product,
            'quantity' => $clampedQty,
            'line_total_cents' => $clampedQty * (int)$product['price_cents'],
        ];
    }
    return $details;
}

function cart_total_cents(array $details): int
{
    return array_sum(array_column($details, 'line_total_cents'));
}

function format_price(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}
