#!/bin/bash
# =============================================================================
# À exécuter UNE SEULE FOIS sur le serveur via SSH
# Installe le .env de production dans /etc/careasy/.env
# Le workflow le copie automatiquement à chaque déploiement
#
# Usage :
#   scp scripts/env.production user@serveur:/tmp/env.production
#   ssh user@serveur "bash -s" < scripts/install-env-production.sh
# =============================================================================

set -e

echo "Installation du .env de production CarEasy..."

# Créer le répertoire sécurisé
sudo mkdir -p /etc/careasy
sudo chmod 700 /etc/careasy

# Copier le .env
if [ -f /tmp/env.production ]; then
  sudo cp /tmp/env.production /etc/careasy/.env
  sudo chmod 600 /etc/careasy/.env
  rm -f /tmp/env.production
  echo " /etc/careasy/.env installé"
elif [ -f /var/www/careasy/scripts/env.production ]; then
  sudo cp /var/www/careasy/scripts/env.production /etc/careasy/.env
  sudo chmod 600 /etc/careasy/.env
  echo " /etc/careasy/.env installé depuis scripts/env.production"
else
  echo " Fichier source introuvable."
  echo "   Place le fichier env.production dans /tmp/ et réessaie."
  exit 1
fi

# Vérification rapide
echo ""
echo "Vérification :"
grep "^DB_CONNECTION=" /etc/careasy/.env
grep "^APP_URL=" /etc/careasy/.env
grep "^FILESYSTEM_DISK=" /etc/careasy/.env

echo ""
echo " Installation terminée. Le prochain git push déploiera correctement."
