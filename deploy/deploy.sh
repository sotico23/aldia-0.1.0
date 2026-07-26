#!/bin/bash
# ============================================================================
# AlDia - Script de Despliegue a Producción
# Ejecutar desde la raíz del proyecto en el servidor
# ============================================================================

set -e

APP_DIR="/srv/aldia"
REPO_URL="https://github.com/usuario/aldia-0.1.0.git"
BRANCH="main"
PHP_VERSION="8.4"
NODE_VERSION="20"

echo "============================================="
echo "  AlDia - Despliegue a Producción"
echo "============================================="

# 1. Si es primera vez, clonar el repo
if [ ! -d "$APP_DIR" ]; then
    echo "[1/12] Clonando repositorio..."
    sudo git clone -b $BRANCH $REPO_URL $APP_DIR
    cd $APP_DIR
else
    echo "[1/12] Actualizando codigo..."
    cd $APP_DIR
    sudo git fetch origin
    sudo git reset --hard origin/$BRANCH
fi

# 2. Instalar dependencias PHP
echo "[2/12] Instalando dependencias PHP..."
sudo composer install --no-dev --optimize-autoloader --no-interaction

# 3. Instalar dependencias Node
echo "[3/12] Instalando dependencias Node..."
sudo npm ci --production=false

# 4. Build frontend
echo "[4/12] Construyendo assets frontend..."
sudo npm run build

# 5. Configurar .env
echo "[5/12] Configurando .env..."
if [ ! -f .env ]; then
    sudo cp .env.production .env
    sudo php artisan key:generate --force
    echo "  >> .env creado desde .env.production"
    echo "  >> IMPORTANTE: Edita .env con los secrets reales antes de continuar"
else
    echo "  >> .env ya existe, saltando"
fi

# 6. Optimizaciones Laravel
echo "[6/12] Ejecutando optimizaciones..."
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
sudo php artisan event:cache

# 7. Migraciones de base de datos
echo "[7/12] Ejecutando migraciones..."
sudo php artisan migrate --force

# 8. Seeds (solo si es primera vez)
echo "[8/12] Verificando seeds..."
if [ "$1" = "--seed" ]; then
    sudo php artisan db:seed --force
fi

# 9. Permisos
echo "[9/12] Configurando permisos..."
sudo chown -R www-data:www-data $APP_DIR
sudo find $APP_DIR -type d -exec chmod 755 {} \;
sudo find $APP_DIR -type f -exec chmod 644 {} \;
sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

# 9b. PHP upload limits (profile photos, cover photos)
echo "  Configurando PHP upload limits..."
PHP_INI=$(php -r 'echo php_ini_loaded_file();' 2>/dev/null || echo "/etc/php/8.4/fpm/php.ini")
if [ -f "$PHP_INI" ]; then
    sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' "$PHP_INI"
    sudo sed -i 's/post_max_size = .*/post_max_size = 12M/' "$PHP_INI"
    echo "  >> upload_max_filesize=10M, post_max_size=12M en $PHP_INI"
fi

# 10. Supervisor - Workers de cola
echo "[10/12] Configurando supervisor workers..."
sudo cp $APP_DIR/deploy/supervisor/aldia-worker.conf /etc/supervisor/conf.d/
sudo cp $APP_DIR/deploy/supervisor/reverb.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all

# 11. Nginx
echo "[11/12] Configurando Nginx..."
sudo cp $APP_DIR/deploy/nginx/aldiaproyect.conf /etc/nginx/sites-available/aldiaproyect
sudo ln -sf /etc/nginx/sites-available/aldiaproyect /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# 12. SSL (Let's Encrypt)
echo "[12/12] Verificando SSL..."
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
echo "  1. Edita .env si no lo has hecho (CHANGE_ME values)"
echo "  2. Configura SSL: sudo certbot --nginx -d aldiaproyect.com"
echo "  3. Verifica: curl -I https://aldiaproyect.com"
echo "  4. Verifica WebSocket: wscat -c wss://aldiaproyect.com/app/..."
echo ""
