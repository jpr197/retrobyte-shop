# Dockerfile for deploying RetroByte to Render (or any Docker host).
#
# Render doesn't have a native "PHP" button -- it deploys Docker images.
# This image uses PHP's built-in web server, which is plenty for a
# small school-project demo. It is NOT meant for serious production
# traffic, but is simple, reliable, and starts fast.

FROM php:8.3-cli

# pdo_sqlite is required by this app (includes/db.php uses PDO+SQLite).
# The PHP extension installer needs SQLite's development headers
# present first, or the compile step fails -- that's what
# libsqlite3-dev provides.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_sqlite

WORKDIR /var/www/html
COPY . /var/www/html

# Render's free web services have an EPHEMERAL filesystem: anything
# written to disk (like data/store.sqlite) is wiped on every restart
# or redeploy. That's fine for a demo -- the app recreates the
# database with fresh seed data automatically on first request.
RUN mkdir -p /var/www/html/data && chmod -R 777 /var/www/html/data

# Render injects the port to listen on via the PORT env var.
# PHP's built-in server needs a fixed port at start time, so we read
# $PORT at container start via a small shell wrapper.
ENV PORT=10000
EXPOSE 10000

CMD php -S 0.0.0.0:$PORT -t /var/www/html