#!/bin/bash
# =============================================================================
# CarEasy — Script de configuration initiale du VPS
# À exécuter UNE SEULE FOIS sur le serveur de production via SSH
#
# Usage : bash scripts/setup-production.sh
# =============================================================================

set -e

APP_PATH="/var/www/careasy"
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
PHP_FPM_INI="/etc/php/${PHP_VERSION}/fpm/conf.d/99-careasy.ini"
NGINX_CONF="/etc/nginx/sites-available/careasy"

echo "📦 PHP version détectée : ${PHP_VERSION}"

# =============================================================================
# 1. PHP-FPM — Limites upload pour les médias (images/vidéos/vocaux)
# =============================================================================
echo "⚙️  Configuration PHP-FPM..."

cat > "${PHP_FPM_INI}" << 'EOF'
; CarEasy — limites upload médias
upload_max_filesize = 100M
post_max_size       = 105M
max_execution_time  = 300
max_input_time      = 300
memory_limit        = 256M
EOF

echo "✅ PHP-FPM configuré : ${PHP_FPM_INI}"

# =============================================================================
# 2. Nginx — Limite taille requête
# =============================================================================
echo "⚙️  Vérification config Nginx..."

if [ -f "${NGINX_CONF}" ]; then
    if ! grep -q "client_max_body_size" "${NGINX_CONF}"; then
        # Ajoute la directive dans le bloc server
        sed -i '/server {/a\    client_max_body_size 105M;' "${NGINX_CONF}"
        echo "✅ client_max_body_size 105M ajouté dans Nginx"
    else
        echo "ℹ️  client_max_body_size déjà présent dans Nginx"
    fi
else
    echo "⚠️  Config Nginx non trouvée à ${NGINX_CONF}"
    echo "   Ajoute manuellement dans le bloc server :"
    echo "       client_max_body_size 105M;"
fi

# =============================================================================
# 3. Permissions storage
# =============================================================================
echo "⚙️  Permissions storage..."

cd "${APP_PATH}"
mkdir -p storage/app/public/messages
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
echo "✅ Permissions OK"

# =============================================================================
# 4. Symlink public/storage
# =============================================================================
echo "⚙️  Symlink storage..."

rm -f public/storage
php artisan storage:link
echo "✅ Symlink créé : $(readlink public/storage)"

# =============================================================================
# 5. Queue worker — Supervisor
# =============================================================================
SUPERVISOR_CONF="/etc/supervisor/conf.d/careasy-worker.conf"
echo "⚙️  Configuration Supervisor (queue worker)..."

cat > "${SUPERVISOR_CONF}" << EOF
[program:careasy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_PATH}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=${APP_PATH}/storage/logs/worker.log
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update
supervisorctl start careasy-worker:*
echo "✅ Queue workers démarrés"

# =============================================================================
# 6. Reload services
# =============================================================================
echo "🔄 Reload PHP-FPM et Nginx..."

systemctl reload "php${PHP_VERSION}-fpm" 2>/dev/null || \
  systemctl reload php-fpm 2>/dev/null || \
  echo "⚠️  Reload PHP-FPM manuel requis"

nginx -t && systemctl reload nginx || echo "⚠️  Reload Nginx manuel requis"

# =============================================================================
echo ""
echo "============================================="
echo "✅ Configuration production CarEasy terminée"
echo "============================================="
echo ""
echo "⚠️  N'oublie pas de vérifier le .env de production :"
echo "   APP_ENV=production"
echo "   APP_DEBUG=false"
echo "   APP_URL=https://ton-domaine.com"
echo "   FILESYSTEM_DISK=public"
