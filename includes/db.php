<?php
/**
 * db.php
 * Creates (if needed) and returns a connection to the SQLite database.
 * Using SQLite means the whole store works on almost any PHP host with
 * zero extra setup -- no MySQL server to configure.
 */

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../data/store.sqlite';
    $isNew  = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        init_schema($pdo);
        seed_products($pdo);
    }

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE products (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            description TEXT NOT NULL,
            price_cents INTEGER NOT NULL,
            image_emoji TEXT NOT NULL DEFAULT '📦',
            stock       INTEGER NOT NULL DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE orders (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            total_cents   INTEGER NOT NULL,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE order_items (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id    INTEGER NOT NULL REFERENCES orders(id),
            product_id  INTEGER NOT NULL REFERENCES products(id),
            product_name TEXT NOT NULL,
            unit_price_cents INTEGER NOT NULL,
            quantity    INTEGER NOT NULL
        )
    ");
}

function seed_products(PDO $pdo): void
{
    $products = [
        ['Pixel Pop Game Console',   'A handheld console for classic 8-bit style games. Comes with 50 built-in titles.', 4999, '🎮', 8],
        ['Wave Rider Walkman',       'Cassette-style portable player reborn with Bluetooth playback inside.',         3499, '📻', 5],
        ['Glow CRT Mini Monitor',    'A tiny 5-inch CRT-style display for retro computing setups and demos.',         8999, '📺', 3],
        ['Click-Clack Mechanical Keyboard', 'Loud, satisfying blue-switch keyboard with a beige retro shell.',        6499, '⌨️', 12],
        ['Floppy Disk USB Drive',    '3.5" floppy disk shell housing a modern 64GB USB drive. Pure nostalgia.',       1999, '💾', 20],
        ['Boombox Bluetooth Speaker', 'Shoulder-carry boombox styling with modern Bluetooth 5.0 audio.',              7499, '📦', 6],
        ['Rotary Phone Bluetooth Handset', 'A working rotary dial phone that pairs with your smartphone.',           5999, '☎️', 1],
        ['Arcade Joystick USB Controller', 'Full-size arcade stick with clicky buttons for PC and console emulators.', 8499, '🕹️', 4],
        ['Polaroid-Style Instant Camera', 'Modern instant film camera with a retro boxy body design.',                6999, '📷', 0],
        ['Neon Sign "OPEN" LED',     'USB-powered neon-style LED sign, perfect for a retro game room.',              2999, '🔆', 10],
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO products (name, description, price_cents, image_emoji, stock)
         VALUES (:name, :description, :price_cents, :image_emoji, :stock)"
    );

    foreach ($products as [$name, $desc, $price, $emoji, $stock]) {
        $stmt->execute([
            ':name' => $name,
            ':description' => $desc,
            ':price_cents' => $price,
            ':image_emoji' => $emoji,
            ':stock' => $stock,
        ]);
    }
}
