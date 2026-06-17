#!/bin/bash
# =============================================================================
# Installation du .env de production sur le serveur
# À exécuter UNE SEULE FOIS depuis ton poste local
#
# Usage :
#   chmod +x scripts/install-env-production.sh
#   ./scripts/install-env-production.sh user@careasy.cap-epac.bj
# =============================================================================

SERVER="${1:-}"

if [ -z "$SERVER" ]; then
  echo "Usage : ./scripts/install-env-production.sh user@careasy.cap-epac.bj"
  exit 1
fi

ENV_FILE="$(dirname "$0")/../.env.production"

if [ ! -f "$ENV_FILE" ]; then
  echo "❌ Fichier .env.production introuvable : $ENV_FILE"
  exit 1
fi

echo "📤 Envoi du .env.production vers $SERVER:/var/www/careasy/.env ..."
scp "$ENV_FILE" "$SERVER:/var/www/careasy/.env"

echo "🔒 Sécurisation des permissions..."
ssh "$SERVER" "chmod 600 /var/www/careasy/.env"

echo ""
echo "✅ .env installé sur le serveur."
echo "   Vérification :"
ssh "$SERVER" "grep '^DB_CONNECTION=\|^APP_URL=\|^APP_ENV=' /var/www/careasy/.env"
