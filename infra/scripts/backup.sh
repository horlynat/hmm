#!/usr/bin/env bash
#
# Sauvegarde chiffrée de la base (règle 3-2-1, guide hardening serveur §12).
# 1 copie locale (rotation), chiffrée avec age avant toute sortie du serveur
# — la 2e copie hors-site (support différent) reste à brancher (rclone vers
# S3/B2/etc., cf. README) une fois le provider de stockage choisi.
#
# Prérequis :
#   - age installé (`apt install age`) et une clé générée :
#       age-keygen -o /root/.age/backup-key.txt
#     -> ne JAMAIS commit cette clé. Garder une copie hors du VPS (c'est la
#        seule façon de restaurer).
#   - AGE_RECIPIENT = clé publique correspondante (age1...)
#
# Usage : ./backup.sh [--restore <fichier.sql.gz.age>]
# Cron (à poser via crontab -e du user deploy ou un timer systemd) :
#   0 3 * * * DEPLOY_PATH=/opt/hmm AGE_RECIPIENT=age1... /opt/hmm/infra/scripts/backup.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/hmm}"
COMPOSE_FILE="$DEPLOY_PATH/infra/docker-compose.prod.yml"
BACKUP_DIR="${BACKUP_DIR:-$DEPLOY_PATH/infra/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
AGE_RECIPIENT="${AGE_RECIPIENT:-}"

log() { echo -e "\n\033[1;32m==>\033[0m $1"; }

restore() {
  local encrypted_file="$1"
  local age_key="${AGE_KEY_FILE:-/root/.age/backup-key.txt}"
  echo "⚠️  Restauration : va ÉCRASER la base actuelle. Ctrl+C pour annuler."
  read -r -p "Continuer ? [y/N] " confirm
  [[ "$confirm" == "y" ]] || exit 0

  age -d -i "$age_key" "$encrypted_file" | gunzip | \
    docker compose -f "$COMPOSE_FILE" exec -T database \
      psql -U "${POSTGRES_USER:-app}" -d "${POSTGRES_DB:-app}"
  echo "Restauration terminée."
}

if [[ "${1:-}" == "--restore" ]]; then
  [[ -n "${2:-}" ]] || { echo "Usage: $0 --restore <fichier.sql.gz.age>" >&2; exit 1; }
  restore "$2"
  exit 0
fi

if [[ -z "$AGE_RECIPIENT" ]]; then
  echo "AGE_RECIPIENT non défini — impossible de chiffrer la sauvegarde." >&2
  echo "Génère une clé : age-keygen -o /root/.age/backup-key.txt" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ 2>/dev/null || date +%Y%m%d%H%M%S)"
OUT="$BACKUP_DIR/db-${STAMP}.sql.gz.age"

log "Dump PostgreSQL (via le conteneur database)"
docker compose -f "$COMPOSE_FILE" exec -T database \
  pg_dump -U "${POSTGRES_USER:-app}" "${POSTGRES_DB:-app}" \
  | gzip \
  | age -r "$AGE_RECIPIENT" -o "$OUT"

echo "Sauvegarde chiffrée : $OUT ($(du -h "$OUT" | cut -f1))"

log "Rotation (garde ${RETENTION_DAYS} jours)"
find "$BACKUP_DIR" -name 'db-*.sql.gz.age' -mtime "+${RETENTION_DAYS}" -delete -print

log "Hors-site"
echo "Pas de destination hors-site configurée dans ce script — brancher ici"
echo "un envoi (rclone/restic vers S3, B2, autre VPS...) une fois choisi."
echo "Exemple : rclone copy '$OUT' remote:hmm-backups/"

echo
echo "Rappel guide §12 : une sauvegarde jamais restaurée n'est pas une"
echo "sauvegarde — teste '$0 --restore <fichier>' sur un environnement de"
echo "test au moins une fois avant d'en dépendre en prod."
