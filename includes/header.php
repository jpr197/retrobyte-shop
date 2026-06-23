<?php
/** partials/header.php — expects $pdo to be available if cart count is needed */
$cartCount = cart_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= STORE_NAME ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site">
    <div class="site-bar">
        <a href="index.php" class="brand" style="text-decoration:none;">
            <span class="blip">●</span> <?= STORE_NAME ?>
        </a>
        <a href="cart.php" class="cart-link">
            🛒 Cart<?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
        </a>
    </div>
</header>
