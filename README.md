# RetroByte — PHP Shopping Cart

A small retro-tech gadget store: product catalog with live stock, a
session-based shopping cart, checkout, order storage, and an email
notification sent to the store owner on every purchase. Built in plain
PHP + SQLite — no framework, no external services, runs on almost any
PHP host.

## Requirements

- PHP 8.0+ with the `pdo_sqlite` extension (included in most PHP installs)
- A web server that can run PHP (Apache, Nginx+PHP-FPM, or even PHP's
  built-in server for local testing)
- For real emails: a working mail transport on the server (sendmail /
  Postfix / etc). Most shared hosts have this already. If you don't
  have one, see "Email notifications" below.

## Running it locally

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/` in a browser. The SQLite database
and its 10 seed products are created automatically on the first
request (file: `data/store.sqlite`).

## Admin panel

Visit `/admin.php` (e.g. `http://localhost:8000/admin.php`) to:

- edit any product's stock or price and save it instantly
- see every order that's been placed, with customer name, email, items, and total
- reset the entire store back to its original seed data (also wipes
  all order history) with one button

It's protected by a single shared password, set in `includes/config.php`:

```php
define('ADMIN_PASSWORD', 'changeme123');
```

**Change this before showing the project to anyone else** — it's a
plain-text password check meant for a single store owner on their own
machine, not real multi-user security. There's no link to `/admin.php`
anywhere in the customer-facing pages; you just navigate to it directly.

## Deploying to a real host

1. Upload the whole folder to your host (e.g. via FTP or your host's
   file manager).
2. Make sure the `data/` folder is writable by PHP (it stores the
   SQLite database and an email log). `chmod 755 data` is usually enough.
3. Open `includes/config.php` and set `OWNER_EMAIL` to the address
   that should receive order notifications. Also update `MAIL_FROM`
   to an address on your own domain — many hosts reject mail whose
   "From" address doesn't match their domain.
4. Visit the site. That's it — no database server, no build step.

## Deploying to Render

Render doesn't have a native "PHP" runtime button — it deploys Docker
images, so this project includes a `Dockerfile` that does that for you.

1. Push this project to a GitHub repository (Render deploys from Git).
2. On [render.com](https://render.com), click **New > Web Service**
   and connect your repo.
3. Render should auto-detect the `Dockerfile`. If asked, set:
   - **Environment**: Docker
   - **Instance type**: Free (fine for a class demo)
4. Deploy. Render builds the image and gives you a URL like
   `https://your-app-name.onrender.com`.

**Important caveat for free Render web services:** they have an
*ephemeral* filesystem — anything written to disk, including
`data/store.sqlite`, is wiped every time the service restarts,
redeploys, or spins down after inactivity. For a class demo this is
usually fine: the site just recreates the database with fresh seed
data automatically on the next request. But it does mean:

- Don't expect orders/admin edits to persist long-term on the free tier.
- If your demo URL has been sitting idle and spun down, the *first*
  request after waking it up may take a few extra seconds (and you'll
  get a brand-new, freshly seeded store).
- If you need real persistence (e.g. grading happens over multiple
  days and stock changes need to stick), you'd need a paid Render
  instance with a [persistent disk](https://render.com/docs/disks)
  mounted at the `data/` path — not necessary for a one-time demo.

Before deploying, update `includes/config.php` with your own
`ADMIN_PASSWORD` and `OWNER_EMAIL` — don't leave the placeholder
values live on a public URL.

## How it's organized

```
index.php              Homepage / product catalog, "Add to cart"
cart.php                View cart, change quantities, remove items
checkout.php            Collects customer name + email
place_order.php         Commits the order (this is where stock is decremented)
order_confirmation.php  "Thanks!" page after a successful order
admin.php               Password-protected: edit stock/price, view orders, reset store
Dockerfile              Lets Render (or any Docker host) build & run this app
includes/
  config.php            Store name, owner email, admin password, starts the PHP session
  db.php                Opens the SQLite DB, creates tables + seed data on first run
  cart.php              Cart helper functions (session-based)
  mailer.php            Sends + logs the order notification email
  header.php / footer.php   Shared page chrome
assets/css/style.css   All styling
data/                  SQLite DB + email log live here (auto-created)
```

## How "sold out" works

Stock lives in the `products` table. `place_order.php` decrements it
inside a database transaction at the moment an order is placed, with
a re-check of current stock right before decrementing. That means:

- The homepage always reflects true current stock — the instant the
  last unit of an item is bought, the next page load shows a
  "Sold out" stamp and disables the Add to cart button.
- If two customers are checking out the same last item at nearly the
  same time, only one purchase succeeds; the other is bounced back to
  their cart with a clear message instead of overselling the item.

## Email notifications

`includes/mailer.php` calls PHP's built-in `mail()` function to send
a plain-text order summary to `OWNER_EMAIL` every time an order is
placed. This requires your server to have a configured mail transport.

Every notification is **also** written to `data/email_log.txt`
regardless of whether `mail()` succeeds — this is useful for local
testing (where there's usually no mail server) and as a simple audit
trail. Open that file any time to see exactly what would have been
emailed.

If you want more reliable delivery on a host without a good mail
setup, swap the `mail()` call in `send_order_notification()` for an
API-based provider (Postmark, SendGrid, Mailgun, etc.) — the function
is a single, isolated place to make that change.

## Customizing the catalog

Products are seeded once, in `includes/db.php`, inside
`seed_products()`. To change them, either edit that array before the
database is first created, or just edit the rows directly in
`data/store.sqlite` (e.g. with a tool like DB Browser for SQLite) —
delete the file and reload the site to start over with a fresh seed.

## Notes / things to harden before going live with real money

- No admin panel is included for managing stock/products — it's all
  direct DB edits for now. Happy to add one if useful.
- This is a self-contained demo-quality store: there's no payment
  processor integration. "Place order" records the order and emails
  you, it doesn't charge a card. Wire in Stripe/PayPal/etc. inside
  `place_order.php` before the transaction commits if you need to
  actually collect payment.
- The admin panel uses one shared password with no rate-limiting or
  account lockout. Fine for a personal/school project on your own
  machine; not something to expose on the public internet as-is.
