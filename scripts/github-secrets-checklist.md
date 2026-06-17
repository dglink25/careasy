# GitHub Secrets requis pour le déploiement CarEasy
# github.com/dglink25/careasy → Settings → Secrets and variables → Actions → New repository secret

## SSH
VPS_HOST           = <IP ou domaine du serveur>
VPS_USERNAME       = <nom utilisateur SSH>
SSH_PRIVATE_KEY    = <contenu de ~/.ssh/id_rsa>

## Laravel
APP_KEY            = <base64:xxx — php artisan key:generate --show>

## Base de données (Neon PostgreSQL)
DB_HOST            = <host Neon>
DB_PORT            = 5432
DB_DATABASE        = <nom de la base>
DB_USERNAME        = <utilisateur>
DB_PASSWORD        = <mot de passe>

## Pusher
PUSHER_APP_ID      = <id>
PUSHER_APP_KEY     = <key>
PUSHER_APP_SECRET  = <secret>
PUSHER_APP_CLUSTER = eu

## Mail Gmail
MAIL_USERNAME      = <email>
MAIL_PASSWORD      = <mot de passe application>

## FedaPay
FEDAPAY_SECRET_KEY     = <sk_live_xxx>
FEDAPAY_PUBLIC_KEY     = <pk_live_xxx>
FEDAPAY_WEBHOOK_SECRET = <wh_live_xxx>

## Firebase
FIREBASE_CREDENTIALS = <chemin relatif du fichier json>

## VAPID
VAPID_PUBLIC_KEY   = <clé publique>
VAPID_PRIVATE_KEY  = <clé privée>

## WhatsApp
WHATSAPP_API_SECRET = <secret>

## SMS
SMS_GATEWAY_USER   = <utilisateur>
SMS_GATEWAY_PASS   = <mot de passe>

## Google OAuth
GOOGLE_CLIENT_ID     = <client id>
GOOGLE_CLIENT_SECRET = <client secret>
