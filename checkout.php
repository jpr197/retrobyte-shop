<?php
/**
 * checkout.php — Collects customer name + email, then hands off to
 * place_order.php to actually commit the purchase.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cart.php';

$pdo = get_db();
$details = cart_details($pdo);
$total = cart_total_cents($details);

if (empty($details)) {
    header('Location: cart.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');

    if ($name === '') {
        $errors['customer_name'] = 'Please enter your name.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['customer_email'] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // Hand off to place_order.php logic via direct include so we keep
        // a single clean POST/redirect/GET flow.
        $_SESSION['checkout_name'] = $name;
        $_SESSION['checkout_email'] = $email;
        header('Location: place_order.php');
        exit;
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="padding-top:40px; padding-bottom:60px;">
    <div class="section-head" style="margin-top:0;">
        <h2><span class="dot">//</span> Checkout</h2>
    </div>

    <div class="cart-summary-box" style="width:100%; max-width:460px; margin-bottom:24px;">
        <?php foreach ($details as $row): ?>
            <div class="cart-summary-row">
                <span><?= htmlspecialchars($row['product']['name']) ?> &times;<?= $row['quantity'] ?></span>
                <span><?= format_price($row['line_total_cents']) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="cart-summary-row total">
            <span>Total due</span>
            <span><?= format_price($total) ?></span>
        </div>
    </div>

    <form method="post" action="checkout.php" class="form-box">
        <div class="field">
            <label for="customer_name">Full name</label>
            <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($name) ?>" required>
            <?php if (isset($errors['customer_name'])): ?><div class="field-error"><?= $errors['customer_name'] ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label for="customer_email">Email address</label>
            <input type="email" id="customer_email" name="customer_email" value="<?= htmlspecialchars($email) ?>" required>
            <?php if (isset($errors['customer_email'])): ?><div class="field-error"><?= $errors['customer_email'] ?></div><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-add">Place order — <?= format_price($total) ?></button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
