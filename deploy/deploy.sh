#!/bin/bash
# ============================================================================
# AlDia - Script de Despliegue a Producción
# Ejecutar desde la raíz del proyecto en el servidor.
#
# Requisitos previos:
#   - .env.production en la raíz (copia .env.production.example y rellena
#     los valores reales). El script lo copia a .env en el primer despliegue.
#   - Nginx, PHP-FPM, Supervisor, Node y Composer instalados.
# ============================================================================

set -e

APP_DIR="/srv/aldia"
REPO_URL="https://github.com/sotico23/aldia-0.1.0.git"
BRANCH="main"
PHP_VERSION="8.4"
NODE_VERSION="20"

echo "============================================="
echo "  AlDia - Despliegue a Producción"
echo "============================================="

# 1. Si es primera vez, clonar el repo
if [ ! -d "$APP_DIR" ]; then
    echo "[1/14] Clonando repositorio..."
    sudo git clone -b "$BRANCH" "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
else
    echo "[1/14] Actualizando codigo..."
    cd "$APP_DIR"
    sudo git fetch origin
    sudo git reset --hard "origin/$BRANCH"
fi

# 2. Instalar dependencias PHP
echo "[2/14] Instalando dependencias PHP..."
sudo composer install --no-dev --optimize-autoloader --no-interaction

# 3. Configurar .env (debe existir antes del build: Vite compila VITE_*)
echo "[3/14] Configurando .env..."
if [ ! -f .env ]; then
    if [ ! -f .env.production ]; then
        echo "  ERROR: no existe .env.production." >&2
        echo "  Copia .env.production.example a .env.production, rellena los" >&2
        echo "  valores reales (DB, APP_KEY, Reverb, correo) y vuelve a ejecutar." >&2
        exit 1
    fi

    sudo cp .env.production .env
    sudo php artisan key:generate --force
    echo "  >> .env creado desde .env.production"
else
    echo "  >> .env ya existe, saltando"
fi

# 4. Instalar dependencias Node
echo "[4/14] Instalando dependencias Node..."
sudo npm ci --production=false

# 5. Build frontend
echo "[5/14] Construyendo assets frontend..."
sudo npm run build

# 6. Enlace de storage
echo "[6/14] Creando enlace de storage..."
sudo php artisan storage:link --force

# 7. Optimizaciones Laravel
echo "[7/14] Ejecutando optimizaciones..."
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
sudo php artisan event:cache

# 8. Migraciones de base de datos
echo "[8/14] Ejecutando migraciones..."
sudo php artisan migrate --force

# 9. Seeds (solo si es primera vez)
echo "[9/14] Verificando seeds..."
if [ "${1:-}" = "--seed" ]; then
    sudo php artisan db:seed --force
fi

# 10. Permisos
echo "[10/14] Configurando permisos..."
sudo chown -R www-data:www-data "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 755 {} \;
sudo find "$APP_DIR" -type f -exec chmod 644 {} \;
sudo chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
# El .env contiene secretos: solo lectura para el usuario de la app.
sudo chmod 600 "$APP_DIR/.env"

# 11. PHP upload limits (profile photos, cover photos)
echo "[11/14] Configurando PHP upload limits..."
PHP_INI=$(php -r 'echo php_ini_loaded_file();' 2>/dev/null || echo "/etc/php/$PHP_VERSION/fpm/php.ini")
if [ -f "$PHP_INI" ]; then
    sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' "$PHP_INI"
    sudo sed -i 's/post_max_size = .*/post_max_size = 12M/' "$PHP_INI"
    echo "  >> upload_max_filesize=10M, post_max_size=12M en $PHP_INI"
fi

# 12. Supervisor - Workers de cola y Reverb
echo "[12/14] Configurando supervisor workers..."
sudo cp "$APP_DIR/deploy/supervisor/aldia-worker.conf" /etc/supervisor/conf.d/
sudo cp "$APP_DIR/deploy/supervisor/reverb.conf" /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
# Reinicio selectivo: no toca otros programas del servidor.
sudo supervisorctl restart aldia-queue:* aldia-reverb

# 13. Nginx
echo "[13/14] Configurando Nginx..."
sudo cp "$APP_DIR/deploy/nginx/aldiaproyect.conf" /etc/nginx/sites-available/aldiaproyect
sudo ln -sf /etc/nginx/sites-available/aldiaproyect /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# 14. SSL (Let's Encrypt)
echo "[14/14] Verificando SSL..."
if [ ! -f /etc/letsencrypt/live/aldiaproyect.com/fullchain.pem ]; then
    echo "  >> SSL no configurado. Ejecuta:"
    echo "     sudo certbot --nginx -d aldiaproyect.com -d www.aldiaproyect.com"
else
    echo "  >> SSL ya configurado"
fi

echo ""
echo "============================================="
echo "  Despliegue completado!"
echo "============================================="
echo ""
echo "Pasos pendientes:"
echo "  1. Revisa .env (no debe quedar ningún CHANGE_ME)"
echo "  2. Configura SSL: sudo certbot --nginx -d aldiaproyect.com"
echo "  3. Verifica: curl -I https://aldiaproyect.com"
echo "  4. Verifica WebSocket: wscat -c wss://aldiaproyect.com/app/..."
echo ""
