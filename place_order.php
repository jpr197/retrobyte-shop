<?php
/**
 * place_order.php — Commits the order.
 *
 * This is the one place where stock actually gets decremented, inside
 * a transaction, re-checking stock at the moment of purchase. That
 * re-check matters: it's what guarantees the homepage correctly shows
 * "Sold out" the instant the last unit of an item is bought, even if
 * two customers were racing for it at the same time.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/mailer.php';

$pdo = get_db();

$name = $_SESSION['checkout_name'] ?? '';
$email = $_SESSION['checkout_email'] ?? '';

if ($name === '' || $email === '') {
    header('Location: checkout.php');
    exit;
}

$details = cart_details($pdo);
if (empty($details)) {
    header('Location: cart.php');
    exit;
}

$pdo->beginTransaction();

try {
    $orderItems = [];
    $totalCents = 0;

    foreach ($details as $row) {
        $productId = (int)$row['product']['id'];
        $requestedQty = $row['quantity'];

        // Re-read stock INSIDE the transaction, with a row lock implied
        // by SQLite's transaction isolation, so two simultaneous buyers
        // can't both purchase the last unit.
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new RuntimeException('A product in your cart no longer exists.');
        }

        $availableNow = (int)$product['stock'];
        $qtyToBuy = min($requestedQty, $availableNow);

        if ($qtyToBuy <= 0) {
            throw new RuntimeException(htmlspecialchars($product['name']) . ' just sold out before your order went through.');
        }

        // Decrement stock now -- this is what flips the home page to
        // "Sold out" the moment the last item is bought.
        $update = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $update->execute([$qtyToBuy, $productId, $qtyToBuy]);

        if ($update->rowCount() === 0) {
            throw new RuntimeException(htmlspecialchars($product['name']) . ' just sold out before your order went through.');
        }

        $lineTotal = $qtyToBuy * (int)$product['price_cents'];
        $totalCents += $lineTotal;

        $orderItems[] = [
            'product' => $product,
            'quantity' => $qtyToBuy,
            'line_total_cents' => $lineTotal,
        ];
    }

    $orderStmt = $pdo->prepare(
        "INSERT INTO orders (customer_name, customer_email, total_cents) VALUES (?, ?, ?)"
    );
    $orderStmt->execute([$name, $email, $totalCents]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        "INSERT INTO order_items (order_id, product_id, product_name, unit_price_cents, quantity)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($orderItems as $item) {
        $itemStmt->execute([
            $orderId,
            $item['product']['id'],
            $item['product']['name'],
            $item['product']['price_cents'],
            $item['quantity'],
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['order_error'] = $e->getMessage();
    header('Location: cart.php');
    exit;
}

// Order is committed. Now notify the store owner by email.
$orderRecord = [
    'id' => $orderId,
    'customer_name' => $name,
    'customer_email' => $email,
    'total_cents' => $totalCents,
    'created_at' => date('Y-m-d H:i:s'),
];
send_order_notification($orderRecord, $orderItems);

// Clean up: empty the cart and the checkout session data, store the
// order id so the confirmation page can display it.
cart_clear();
unset($_SESSION['checkout_name'], $_SESSION['checkout_email']);
$_SESSION['last_order_id'] = $orderId;

header('Location: order_confirmation.php');
exit;
