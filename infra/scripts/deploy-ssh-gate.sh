#!/usr/bin/env bash
#
# Commande forcée de la clé SSH de déploiement CI (GitHub Actions -> VPS,
# utilisateur `deploy`). Installée HORS du dépôt synchronisé, propriété de
# root (sinon un rsync pourrait la réécrire) :
#
#   sudo install -o root -g root -m 0755 infra/scripts/deploy-ssh-gate.sh \
#        /usr/local/sbin/hmm-deploy-gate
#
# puis, dans ~deploy/.ssh/authorized_keys, préfixer la clé CI :
#
#   restrict,command="/usr/local/sbin/hmm-deploy-gate" ssh-ed25519 AAAA... ci-deploy
#
# (`restrict` : ni pty, ni redirection de port/agent/X11 — la clé ne peut
# plus servir de tunnel vers 127.0.0.1:3306, la passerelle de build, etc.)
#
# N'accepte que les DEUX commandes envoyées par .github/workflows/deploy.yml,
# à l'identique de ce qu'elles font sans cette passerelle (bascule sans
# coupure, cf. README §11) :
#   1. rsync en ÉCRITURE SEULE vers exactement <ROOT>/ — délégué à rrsync
#      (paquet rsync), qui filtre les options serveur dangereuses et
#      confine les chemins sous <ROOT> ;
#   2. bash <ROOT>/infra/scripts/deploy-remote.sh <tag>, tag validé
#      (sha hex 7-40 caractères ou "latest").
# Tout le reste est refusé et journalisé (journalctl -t hmm-deploy-gate).
#
# Limite assumée : deploy-remote.sh lui-même arrive par le rsync autorisé
# en (1). Une clé volée permet donc toujours d'exécuter du code en tant que
# `deploy` (membre du groupe docker, équivalent root) en poussant un script
# modifié : cette passerelle retire le shell interactif et les tunnels, pas
# ce risque. La vraie protection de la clé reste l'environnement GitHub
# `production` (approbation manuelle, branche main uniquement).

set -euo pipefail

ROOT="${HMM_DEPLOY_ROOT:-/opt/hmm}"
RRSYNC="${HMM_RRSYNC:-/usr/bin/rrsync}"
cmd="${SSH_ORIGINAL_COMMAND:-}"

log() { logger -t hmm-deploy-gate -- "$*" 2>/dev/null || true; }
deny() {
  log "REFUS : ${cmd:0:300}"
  echo "hmm-deploy-gate : commande refusée." >&2
  exit 1
}

case "$cmd" in
  "rsync --server "*)
    # --sender = le client LIT depuis le serveur : jamais (écriture seule).
    [[ " $cmd " == *" --sender "* ]] && deny
    # Destination exacte « . <ROOT>/ » en fin de commande, rien d'autre.
    [[ "$cmd" == *" . ${ROOT}/" ]] || deny
    [[ -x "$RRSYNC" ]] || { log "rrsync introuvable ($RRSYNC)"; echo "rrsync absent sur le serveur" >&2; exit 1; }
    # rrsync interprète les chemins relativement à son dossier racine : on
    # remplace la destination absolue par « / » (= <ROOT>) pour que le
    # client garde exactement la même commande avec ou sans passerelle.
    log "rsync -> ${ROOT}/"
    export SSH_ORIGINAL_COMMAND="${cmd% "${ROOT}/"} /"
    exec "$RRSYNC" -wo "$ROOT"
    ;;
  *)
    re="^bash ${ROOT//./\\.}/infra/scripts/deploy-remote\\.sh ([0-9a-f]{7,40}|latest)\$"
    if [[ "$cmd" =~ $re ]]; then
      tag="${BASH_REMATCH[1]}"
      log "deploy-remote.sh ${tag}"
      exec bash "${ROOT}/infra/scripts/deploy-remote.sh" "$tag"
    fi
    deny
    ;;
esac
