#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────────────────────────
# Deploy checklist — AlDia Production
# ──────────────────────────────────────────────────────────────────────────────

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

pass() { echo -e "${GREEN}[PASS]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
fail() { echo -e "${RED}[FAIL]${NC} $1"; }

echo ""
echo "============================================="
echo "  AlDia - Production Deployment Checklist"
echo "============================================="
echo ""

# 1. PHP Version
echo "--- PHP ---"
PHP_VER=$(php -r 'echo PHP_VERSION;')
if [[ "$PHP_VER" == 8.4* ]]; then
    pass "PHP $PHP_VER"
else
    warn "PHP $PHP_VER (expected 8.4.x)"
fi

# 2. Composer dependencies
if [ -f vendor/autoload.php ]; then
    pass "Composer dependencies installed"
else
    fail "vendor/autoload.php not found - run: composer install --no-dev"
fi

# 3. Node build
if [ -d public/build ]; then
    pass "Frontend build exists (public/build/)"
else
    fail "public/build/ not found - run: npm run build"
fi

# 4. .env file
if [ -f .env ]; then
    # Check for placeholder values
    CHANGES=$(grep -c "CHANGE_ME" .env || true)
    if [ "$CHANGES" -gt 0 ]; then
        warn ".env has $CHANGES CHANGE_ME values that need real secrets"
    else
        pass ".env configured"
    fi
else
    fail ".env not found - copy from .env.production"
fi

# 5. App key
if grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    pass "APP_KEY is set"
else
    fail "APP_KEY not set - run: php artisan key:generate"
fi

# 6. Cache
if [ -f bootstrap/cache/config.php ]; then
    pass "Config cached"
else
    warn "Config not cached - run: php artisan config:cache"
fi

if [ -f bootstrap/cache/routes-v7.php ] || [ -f bootstrap/cache/routes.php ]; then
    pass "Routes cached"
else
    warn "Routes not cached - run: php artisan route:cache"
fi

# 7. Storage link
if [ -L public/storage ]; then
    pass "Storage link exists"
else
    warn "Storage link missing - run: php artisan storage:link"
fi

# 8. Supervisor
echo ""
echo "--- Supervisor ---"
if command -v supervisorctl &> /dev/null; then
    if sudo supervisorctl status aldia-queue:* &> /dev/null; then
        pass "Queue workers running"
    else
        warn "Queue workers not running - check: supervisorctl status"
    fi
    if sudo supervisorctl status aldia-reverb &> /dev/null; then
        pass "Reverb WebSocket running"
    else
        warn "Reverb not running - check: supervisorctl status"
    fi
else
    warn "Supervisor not installed"
fi

# 9. Redis
echo ""
echo "--- Redis ---"
if command -v redis-cli &> /dev/null; then
    if redis-cli ping &> /dev/null; then
        pass "Redis responding"
    else
        fail "Redis not responding - check: systemctl status redis"
    fi
else
    warn "redis-cli not found"
fi

# 10. Nginx
echo ""
echo "--- Nginx ---"
if command -v nginx &> /dev/null; then
    if sudo nginx -t &> /dev/null; then
        pass "Nginx config valid"
    else
        fail "Nginx config invalid"
    fi
    if [ -f /etc/nginx/sites-enabled/aldiaproyect ]; then
        pass "AlDia site enabled"
    else
        warn "AlDia site not in sites-enabled"
    fi
else
    warn "Nginx not installed"
fi

# 11. SSL
echo ""
echo "--- SSL ---"
if [ -f /etc/letsencrypt/live/aldiaproyect.com/fullchain.pem ]; then
    pass "SSL certificate exists"
    # Check expiry
    EXPIRY=$(sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/aldiaproyect.com/fullchain.pem | cut -d= -f2)
    echo "       Expires: $EXPIRY"
else
    warn "SSL not configured - run: certbot --nginx -d aldiaproyect.com"
fi

# 12. Crontab
echo ""
echo "--- Crontab ---"
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    pass "Scheduler cron job configured"
else
    warn "Scheduler cron not found - add: * * * * * cd /srv/aldia && php artisan schedule:run"
fi

# 13. Database
echo ""
echo "--- Database ---"
if php artisan migrate:status --no-interaction 2>/dev/null | grep -q "Yes"; then
    pass "Migrations up to date"
else
    warn "Check migrations: php artisan migrate:status"
fi

# 14. Final
echo ""
echo "============================================="
echo "  Checklist complete!"
echo "============================================="
echo ""
echo "Commands to run on first deploy:"
echo "  sudo cp deploy/supervisor/*.conf /etc/supervisor/conf.d/"
echo "  sudo cp deploy/nginx/aldiaproyect.conf /etc/nginx/sites-available/"
echo "  sudo ln -sf /etc/nginx/sites-available/aldiaproyect /etc/nginx/sites-enabled/"
echo "  sudo rm -f /etc/nginx/sites-enabled/default"
echo "  sudo nginx -t && sudo systemctl reload nginx"
echo "  sudo certbot --nginx -d aldiaproyect.com -d www.aldiaproyect.com"
echo "  * * * * * cd /srv/aldia && php artisan schedule:run >> /dev/null 2>&1"
echo ""
