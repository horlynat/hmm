#!/usr/bin/env bash
#
# Démarre/arrête l'environnement de dev complet (backend Symfony + frontend
# Next.js + dépendances : MySQL, MailHog). L'app n'est pas conteneurisée :
# seul MailHog tourne dans Docker, tout le reste tourne en process locaux.
#
# Usage: scripts/dev.sh {up|down|status|logs}

set -euo pipefail

# Ce script est versionné dans le worktree git "main" (hmm/main/scripts/dev.sh),
# lui-même un checkout distinct de la vraie racine du monorepo (hmm/), qui
# contient les checkouts de dev réels backend/backend et frontend/frontend.
# On remonte donc l'arborescence jusqu'à trouver ce repère plutôt que de
# supposer une profondeur fixe.
find_monorepo_root() {
  local dir
  dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  while [[ "$dir" != "/" ]]; do
    if [[ -d "$dir/backend/backend" && -d "$dir/frontend/frontend" ]]; then
      echo "$dir"
      return 0
    fi
    dir="$(dirname "$dir")"
  done
  return 1
}

ROOT_DIR="$(find_monorepo_root)" || {
  echo "Impossible de localiser la racine du monorepo (backend/backend et frontend/frontend introuvables)." >&2
  exit 1
}
BACKEND_DIR="$ROOT_DIR/backend/backend"
FRONTEND_DIR="$ROOT_DIR/frontend/frontend"
RUN_DIR="$ROOT_DIR/.dev"
LOG_DIR="$RUN_DIR/logs"
PID_DIR="$RUN_DIR/pids"

MYSQL_SERVICE="mysql"
FRONTEND_PORT="${FRONTEND_PORT:-3000}"

mkdir -p "$LOG_DIR" "$PID_DIR"

log()  { echo "[$1] $2"; }

pid_alive() {
  [[ -f "$1" ]] && kill -0 "$(cat "$1")" 2>/dev/null
}

port_in_use() {
  (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null && exec 3<&- 3>&-
}

start_mysql() {
  if systemctl is-active --quiet "$MYSQL_SERVICE" 2>/dev/null; then
    log deps "mysql : déjà actif"
  else
    log deps "mysql : inactif — démarre-le avec 'sudo systemctl start $MYSQL_SERVICE'"
  fi
}

start_mailhog() {
  log deps "mailhog : démarrage (docker compose)…"
  (cd "$BACKEND_DIR" && docker compose up -d mailer)
}

start_backend() {
  local status_output
  status_output=$(symfony local:server:status --dir="$BACKEND_DIR" 2>/dev/null || true)
  if grep -qi "listening" <<< "$status_output"; then
    log backend "symfony server : déjà en cours"
  else
    log backend "symfony server : démarrage…"
    (cd "$BACKEND_DIR" && symfony server:start -d --no-tls)
  fi

  local dsn
  dsn=$(grep -E '^MESSENGER_TRANSPORT_DSN=' "$BACKEND_DIR/.env" | tail -1 | cut -d= -f2- | tr -d '"')
  if [[ "$dsn" == sync://* || -z "$dsn" ]]; then
    log backend "messenger : transport sync:// -> pas de worker nécessaire"
  elif pid_alive "$PID_DIR/messenger.pid"; then
    log backend "messenger worker : déjà en cours (pid $(cat "$PID_DIR/messenger.pid"))"
  else
    log backend "messenger worker : démarrage (transport $dsn)…"
    (cd "$BACKEND_DIR" && nohup php bin/console messenger:consume async failed -vv \
      > "$LOG_DIR/messenger.log" 2>&1 & echo $! > "$PID_DIR/messenger.pid")
  fi
}

start_frontend() {
  if pid_alive "$PID_DIR/frontend.pid"; then
    log frontend "next dev : déjà en cours (pid $(cat "$PID_DIR/frontend.pid"))"
  elif port_in_use "$FRONTEND_PORT"; then
    log frontend "next dev : port $FRONTEND_PORT déjà occupé (process lancé en dehors du script) — rien à faire"
  else
    log frontend "next dev : démarrage…"
    (cd "$FRONTEND_DIR" && nohup npm run dev -- --port "$FRONTEND_PORT" \
      > "$LOG_DIR/frontend.log" 2>&1 & echo $! > "$PID_DIR/frontend.pid")
  fi
}

cmd_up() {
  start_mysql
  start_mailhog
  start_backend
  start_frontend
  sleep 1
  echo
  echo "Environnement démarré :"
  echo "  MySQL     : service système (127.0.0.1:3306)"
  echo "  MailHog   : http://127.0.0.1:8025  (SMTP 127.0.0.1:1025)"
  echo "  Backend   : https://127.0.0.1:8000  (logs: symfony server:log --dir=$BACKEND_DIR)"
  echo "  Frontend  : http://127.0.0.1:$FRONTEND_PORT  (logs: $LOG_DIR/frontend.log)"
}

cmd_down() {
  if pid_alive "$PID_DIR/frontend.pid"; then
    log frontend "arrêt de next dev…"
    kill "$(cat "$PID_DIR/frontend.pid")" 2>/dev/null || true
  fi
  rm -f "$PID_DIR/frontend.pid"

  if pid_alive "$PID_DIR/messenger.pid"; then
    log backend "arrêt du worker messenger…"
    kill "$(cat "$PID_DIR/messenger.pid")" 2>/dev/null || true
  fi
  rm -f "$PID_DIR/messenger.pid"

  log backend "arrêt du serveur symfony…"
  (cd "$BACKEND_DIR" && symfony server:stop) 2>/dev/null || true

  log deps "arrêt de mailhog…"
  (cd "$BACKEND_DIR" && docker compose stop mailer) 2>/dev/null || true

  echo
  echo "Environnement arrêté (MySQL laissé actif, c'est un service système)."
}

cmd_status() {
  echo "--- MySQL ---"
  systemctl is-active "$MYSQL_SERVICE" 2>/dev/null || echo "inactif"
  echo "--- MailHog ---"
  (cd "$BACKEND_DIR" && docker compose ps mailer)
  echo "--- Backend (symfony) ---"
  symfony local:server:status --dir="$BACKEND_DIR" 2>/dev/null || echo "arrêté"
  echo "--- Messenger worker ---"
  pid_alive "$PID_DIR/messenger.pid" && echo "actif (pid $(cat "$PID_DIR/messenger.pid"))" || echo "arrêté / non nécessaire"
  echo "--- Frontend (next dev) ---"
  pid_alive "$PID_DIR/frontend.pid" && echo "actif (pid $(cat "$PID_DIR/frontend.pid"))" || echo "arrêté"
}

cmd_logs() {
  tail -f "$LOG_DIR"/*.log
}

case "${1:-up}" in
  up)     cmd_up ;;
  down)   cmd_down ;;
  status) cmd_status ;;
  logs)   cmd_logs ;;
  *) echo "Usage: $0 {up|down|status|logs}"; exit 1 ;;
esac
