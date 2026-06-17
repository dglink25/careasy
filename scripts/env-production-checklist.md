# .env Production — Checklist CarEasy

Variables **obligatoires** à vérifier/corriger sur le serveur de production.

## Valeurs critiques

```dotenv
# ── Application ──────────────────────────────────────────────────────────────
APP_ENV=production          # ← NE PAS laisser "local"
APP_DEBUG=false             # ← NE PAS laisser "true" (fuite de stack traces)
APP_URL=https://ton-domaine.com   # ← doit correspondre au vrai domaine HTTPS
                                  #   sinon file_url sera http://localhost:8000/...

# ── Stockage ─────────────────────────────────────────────────────────────────
FILESYSTEM_DISK=public      # ← obligatoire pour les médias uploadés

# ── Queue (FCM, notifications async) ─────────────────────────────────────────
QUEUE_CONNECTION=database   # ← ou redis si disponible

# ── Log ──────────────────────────────────────────────────────────────────────
LOG_LEVEL=warning           # ← "debug" en production = logs trop verbeux
```

## Vérification rapide sur le VPS

```bash
# Vérifie APP_URL
php artisan tinker --execute="echo config('app.url');"

# Vérifie que storage:link pointe au bon endroit
readlink /var/www/careasy/public/storage

# Vérifie les limites PHP actives
php -r "echo ini_get('upload_max_filesize');"   # doit afficher 100M
php -r "echo ini_get('post_max_size');"          # doit afficher 105M

# Vérifie que les workers tournent
supervisorctl status careasy-worker:*
```

## Nginx — bloc server minimal

```nginx
server {
    listen 443 ssl;
    server_name ton-domaine.com;
    root /var/www/careasy/public;

    client_max_body_size 105M;   # ← OBLIGATOIRE pour upload vidéo

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;    # ← upload vidéo long
    }

    # Servir les fichiers médias directement (images, vidéos, vocaux)
    location /storage/ {
        alias /var/www/careasy/storage/app/public/;
        expires 30d;
        add_header Cache-Control "public, immutable";
        add_header Access-Control-Allow-Origin "*";   # ← CORS pour Flutter
    }
}
```
