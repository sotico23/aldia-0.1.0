# Despliegue en produccion

Esta guia resume los pasos que ejecuta `deploy/deploy.sh` y las comprobaciones
que deben hacerse en el servidor.

## Requisitos

- PHP 8.4 con las extensiones requeridas por Laravel.
- Composer, Node 20, Nginx, Supervisor y MySQL.
- `.env.production` en la raiz del proyecto, creado desde
  `.env.production.example`.
- Certificado SSL de Let's Encrypt para `aldiaproyect.com` y
  `www.aldiaproyect.com`.

## Primer despliegue

1. Copiar la plantilla y completar todos los `CHANGE_ME`:

   ```bash
   cp .env.production.example .env.production
   ```

   Como minimo hay que rellenar `APP_KEY`, credenciales de base de datos,
   correo, Reverb (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`) y
   los secretos de integraciones que se usen.

2. Ejecutar el despliegue:

   ```bash
   bash deploy/deploy.sh
   ```

   El script copia `.env.production` a `.env` solo si `.env` no existe. Por eso
   `.env` debe crearse antes de `npm run build`: Vite compila las variables
   `VITE_*` en los assets.

3. Configurar SSL si aun no existe:

   ```bash
   sudo certbot --nginx -d aldiaproyect.com -d www.aldiaproyect.com
   ```

## Despliegues posteriores

```bash
cd /srv/aldia
bash deploy/deploy.sh
```

El script actualiza el codigo, instala dependencias, compila assets, ejecuta
migraciones y recarga Nginx/Supervisor.

## Nginx

El bloque de Reverb usa `location ~ ^/apps?(/|$)`, de modo que proxea `/app`
(WebSocket) y `/apps` (API de Reverb) sin capturar rutas como
`/appointments`.

Validar y recargar:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Si `nginx -t` falla, no recargar. Revisar primero el archivo
`/etc/nginx/sites-available/aldiaproyect`.

## Supervisor

Los programas son `aldia-queue` (workers de cola) y `aldia-reverb`.

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
sudo supervisorctl restart aldia-queue:* aldia-reverb
```

El worker no fija la conexion de cola: usa `QUEUE_CONNECTION` del `.env`
(`database` o `redis`). La plantilla de produccion usa `database`.

## Reverb y WebSockets

- Reverb escucha en `127.0.0.1:8080`; Nginx publica `/app` y `/apps` por HTTPS.
- `REVERB_ALLOWED_ORIGINS` debe contener los dominios publicos separados por
  coma, por ejemplo:
  `https://aldiaproyect.com,https://www.aldiaproyect.com`.
- `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT` y `REVERB_PUBLIC_SCHEME` se usan
  para construir el CSP. En produccion: `aldiaproyect.com`, `443`, `https`.
- `/broadcasting/auth` lo resuelve Laravel con la sesion del usuario; no debe
  proxearse a Reverb.

Comprobar el puerto y el WebSocket:

```bash
ss -ltnp | grep 8080
wscat -c "wss://aldiaproyect.com/app/<APP_KEY>?protocol=7&client=js&version=8.4.0&flash=false"
```

## Colas y scheduler

```bash
php artisan queue:monitor default,emails,notifications,uptime --max=100
php artisan schedule:list
```

El scheduler debe estar configurado en cron:

```cron
* * * * * cd /srv/aldia && php artisan schedule:run >> /dev/null 2>&1
```

## Comprobaciones finales

```bash
php artisan about
php artisan config:show session.secure
php artisan config:show reverb.public
curl -I https://aldiaproyect.com
```

- `session.secure` debe ser `true` en produccion.
- No deben quedar `CHANGE_ME` en `.env`.
- `storage` y `bootstrap/cache` deben ser escribibles por `www-data`.
- Los logs deben rotar a `storage/logs/laravel-YYYY-MM-DD.log`.
