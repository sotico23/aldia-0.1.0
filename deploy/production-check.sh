#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────────────────────────
# Deploy checklist — RedCliente / AldiaProyect
# ──────────────────────────────────────────────────────────────────────────────

echo ""
echo "=== 1. Supervisor (queue workers) ==="
echo "Copy supervisor config and reload:"
echo "  sudo cp deploy/supervisor/aldia-worker.conf /etc/supervisor/conf.d/"
echo "  sudo supervisorctl reread"
echo "  sudo supervisorctl update"
echo "  sudo supervisorctl start aldia-queue:*"
echo "  sudo supervisorctl status aldia-queue:*"
echo ""

echo "=== 2. Crontab (schedule:run every minute) ==="
echo "Add to crontab via 'crontab -e':"
echo "  * * * * * cd /home/forge/aldiaproyect && php artisan schedule:run >> /dev/null 2>&1"
echo ""

echo "=== 3. Health check monitoring ==="
echo "Configure external monitoring (UptimeRobot / Pingdom) for:"
echo "  GET https://aldiaproyect.com/api/v1/automation/health"
echo "  Header: X-API-Key: <your_n8n_api_key>"
echo "  Expected: 200 with {\"status\":\"healthy\",...}"
echo ""

echo "=== 4. Environment (production .env) ==="
echo "Verify these values in production .env:"
echo "  APP_ENV=production"
echo "  APP_DEBUG=false"
echo "  LOG_LEVEL=warning"
echo "  SLACK_BOT_USER_OAUTH_TOKEN=..."
echo "  SLACK_BOT_USER_DEFAULT_CHANNEL=..."
echo "  MAIL_HOST=smtp.sendgrid.net   # or SES / Postmark — NOT Mailtrap"
echo ""

echo "=== 5. Pulse ==="
echo "  php artisan vendor:publish --tag=pulse-assets"
echo ""

echo "=== 6. Final verification ==="
echo "  php artisan automations:health-check"
echo "  supervisorctl status aldia-queue:*"
echo "  crontab -l | grep schedule"
echo ""
