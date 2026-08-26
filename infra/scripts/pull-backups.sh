#!/usr/bin/env bash
#
# À exécuter sur TA MACHINE PERSO, pas sur le VPS. Complète la copie
# automatique vers S3-compatible (OFFSITE_S3_*, cf. backend/docs/
# incident-data-loss.md) par une vraie 3e copie sur un support différent que
# tu contrôles physiquement — règle 3-2-1.
#
# Tire (rsync) le dossier de sauvegardes locales du VPS (infra/backups/,
# bind-mount de var/backups — cf. docker-compose.prod.yml) : le dump .sql en
# clair de chaque sauvegarde (App\Service\DatabaseBackupService::create,
# déclenché depuis /admin/backup ou en cron via `app:backup:create`), PAS la
# copie hors-site chiffrée (celle-là part déjà, séparément, vers le bucket
# S3-compatible).
#
# ⚠️ Ces fichiers .sql sont en clair (emails, hashs de mot de passe...) —
# arrivent sur ta machine avec les mêmes précautions que n'importe quel
# export de la base de prod (chiffrement disque, pas de synchro cloud
# automatique du dossier de destination).
#
# Usage :
#   VPS_HOST=1.2.3.4 ./pull-backups.sh
#   VPS_HOST=1.2.3.4 VPS_USER=ubuntu DEST_DIR=~/hmm-backups ./pull-backups.sh
#
# Cron/launchd côté machine perso, à poser toi-même (pas dans ce repo — cette
# machine n'est pas gérée par infra/) :
#   0 7 * * * VPS_HOST=1.2.3.4 /chemin/vers/pull-backups.sh >> ~/hmm-backups/pull.log 2>&1

set -euo pipefail

VPS_HOST="${VPS_HOST:?Variable VPS_HOST requise (adresse ou hostname du VPS).}"
VPS_USER="${VPS_USER:-ubuntu}"
VPS_PORT="${VPS_PORT:-2222}"
DEPLOY_PATH="${DEPLOY_PATH:-/opt/hmm}"
DEST_DIR="${DEST_DIR:-$HOME/hmm-backups}"

log() { echo -e "\n\033[1;32m==>\033[0m $1"; }

mkdir -p "$DEST_DIR"
chmod 700 "$DEST_DIR"

log "Rsync depuis ${VPS_USER}@${VPS_HOST}:${DEPLOY_PATH}/infra/backups/"
rsync -az --progress \
  -e "ssh -p ${VPS_PORT}" \
  "${VPS_USER}@${VPS_HOST}:${DEPLOY_PATH}/infra/backups/" \
  "$DEST_DIR/"

log "Terminé — $(find "$DEST_DIR" -name 'backup_*.sql' | wc -l) sauvegarde(s) en local dans $DEST_DIR"
echo "Rappel : une sauvegarde jamais restaurée n'est pas une sauvegarde — teste"
echo "une restauration sur un environnement de test au moins une fois avant"
echo "d'en dépendre (cf. backend/docs/incident-data-loss.md)."
