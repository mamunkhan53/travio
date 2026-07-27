#!/usr/bin/env bash
set -e

# ============================================================
# South Zone ERP – Replit startup script
# Initialises MariaDB (first run only), then starts PHP server
# ============================================================

MYSQL_DATA_DIR="/home/runner/mysql-data"
MYSQL_SOCKET="/home/runner/mysql.sock"
MYSQL_LOG="/home/runner/mysql-error.log"
MYSQL_BIN="/nix/store/a4jsa8kjdn3wlccj2wkvhxqza38rpxzf-mariadb-server-10.11.13/bin"

DB_NAME="${DB_NAME:-southzone_erp}"
DB_USER="${DB_USER:-southzone}"
DB_PASS="${DB_PASS:-southzone_local}"

# ── 1. Kill any stale mysqld from a previous run ──────────────────────────────
pkill -f mysqld || true
sleep 1

# ── 2. Initialise data directory (only on first run) ──────────────────────────
if [ ! -d "$MYSQL_DATA_DIR/mysql" ]; then
    echo ">>> Initialising MariaDB data directory..."
    "$MYSQL_BIN/mysql_install_db" \
        --datadir="$MYSQL_DATA_DIR" \
        --auth-root-authentication-method=normal \
        --skip-test-db \
        2>&1 | tail -5
fi

# ── 3. Start MariaDB in the background ───────────────────────────────────────
echo ">>> Starting MariaDB..."
"$MYSQL_BIN/mysqld_safe" \
    --datadir="$MYSQL_DATA_DIR" \
    --socket="$MYSQL_SOCKET" \
    --pid-file="/home/runner/mysql.pid" \
    --log-error="$MYSQL_LOG" \
    --skip-networking \
    --bind-address=127.0.0.1 \
    --port=3306 \
    &

# Wait until MariaDB is ready (up to 30 s)
echo ">>> Waiting for MariaDB to be ready..."
for i in $(seq 1 30); do
    if "$MYSQL_BIN/mysqladmin" -u root --socket="$MYSQL_SOCKET" ping --silent 2>/dev/null; then
        echo ">>> MariaDB is up."
        break
    fi
    sleep 1
done

# ── 4. Create database / user / schema (idempotent) ──────────────────────────
"$MYSQL_BIN/mysql" -u root --socket="$MYSQL_SOCKET" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# Load the schema (safe to re-run; uses IF NOT EXISTS / ON DUPLICATE KEY)
echo ">>> Loading schema..."
"$MYSQL_BIN/mysql" -u root --socket="$MYSQL_SOCKET" "$DB_NAME" < /home/runner/workspace/database_schema.sql

echo ">>> Database ready."

# ── 5. Start PHP built-in web server on port 5000 ────────────────────────────
echo ">>> Starting PHP server on port 5000..."
cd /home/runner/workspace
exec php -S 0.0.0.0:5000 index.php
