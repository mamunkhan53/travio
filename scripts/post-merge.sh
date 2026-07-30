#!/bin/bash
# Post-merge setup for Travio ERP (PHP / Hostinger Shared Hosting)
# No build steps needed — DB migrations run on first page load via includes/db.php.
# This script just validates that key PHP files have no syntax errors.
set -e

echo "Running PHP syntax checks..."
php -l includes/db.php
php -l includes/config.php
php -l includes/actions_agency.php
php -l pages/agency_app.php
echo "All syntax checks passed."
