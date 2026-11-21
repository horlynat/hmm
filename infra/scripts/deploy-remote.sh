#!/usr/bin/env bash
# Exécuté sur le VPS par le workflow GitHub Actions `deploy.yml` (utilisateur
# `deploy`, jamais `ubuntu`), après rsync de infra/ et frontend/. backend/
# n'est plus rsync : l'image arrive directement de GHCR (cf. commentaire sur
# le champ `image:` dans docker-compose.prod.yml).
#
# Reproduit l'ordre documenté en README §6 pour un redéploiement (backend ->
# migrations -> messenger-worker -> frontend), volontairement identique au
# premier déploiement : migrations toujours avant le worker, sinon collision
# sur la table messenger_messages (auto_setup du transport doctrine).
#
# Usage : deploy-remote.sh [TAG]
# TAG : tag de l'image ghcr.io/horlynat/hmm-backend à déployer (sha hex du
# commit, 7 à 40 caractères, ou "latest" ; tag antérieur pour un rollback
# manuel). À défaut d'argument : variable BACKEND_IMAGE_TAG (ancien mode
# d'appel), puis "latest" (lancement manuel direct sur le VPS). Validé
# ci-dessous AVANT tout usage — c'est aussi la forme exacte qu'accepte la
# commande forcée SSH (deploy-ssh-gate.sh).
set -euo pipefail

BACKEND_IMAGE_TAG="${1:-${BACKEND_IMAGE_TAG:-latest}}"
if [[ ! "$BACKEND_IMAGE_TAG" =~ ^([0-9a-f]{7,40}|latest)$ ]]; then
  echo "Tag d'image invalide : '${BACKEND_IMAGE_TAG}' (attendu : sha hex 7-40 car. ou 'latest')." >&2
  exit 2
fi
export BACKEND_IMAGE_TAG

# rsync tourne avec --no-perms (cf. deploy.yml -- deploy n'est pas
# propriétaire de ces dossiers, cf. README §11) : les fichiers fraîchement
# créés perdent leurs bits d'exécution d'origine. Restaurés ici plutôt que
# de dépendre de rsync pour ça.
chmod +x /opt/hmm/infra/scripts/*.sh 2>/dev/null || true

cd /opt/hmm/infra

# `docker compose` valide TOUT le fichier (y compris les `secrets:` de
# services qu'on ne démarre pas encore dans cette commande précise) avant de
# lancer quoi que ce soit -- un seul fichier manquant fait échouer le
# déploiement dès la première commande, avec un message d'erreur Compose
# assez générique à repérer dans les logs CI. Constaté en prod (secrets
# gemini_api_key/anthropic_api_key jamais provisionnés avant un push infra
# qui les référençait déjà) : on vérifie donc explicitement ici, avant tout
# appel Compose, avec un message qui dit exactement quoi créer.
check_secrets() {
  local missing=()
  local path
  while IFS= read -r path; do
    [[ -f "$path" ]] || missing+=("$path")
  done < <(grep -oP 'file: \K\./secrets/\S+' docker-compose.prod.yml)

  if [[ ${#missing[@]} -gt 0 ]]; then
    echo "==> Secret(s) manquant(s) sur ce serveur, déploiement annulé avant toute action :" >&2
    printf '    %s\n' "${missing[@]}" >&2
    echo "    Provisionner ce(s) fichier(s) (cf. README §5) puis relancer." >&2
    return 1
  fi
}
check_secrets

# Variables de sécurité de .env.prod (Cloudflare Access complet ou absent,
# PUBLIC_API_HOSTS incluant hmm-backend) — cf. check-env-prod.sh.
bash "$(dirname "${BASH_SOURCE[0]}")/check-env-prod.sh" .env.prod

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

# Passerelle éphémère du build frontend (service api-build-gateway, profil
# `build`, cf. docker-compose.prod.yml) : publiée sur l'IP de docker0 et
# jointe par les RUN du build sous le nom `hmm-backend`. Même valeur par défaut
# que dans le compose ; surcharger les deux via .env.prod si docker0 n'est
# pas en 172.17.0.1.
BUILD_GATEWAY_BIND="$(sed -n 's/^HMM_BUILD_GATEWAY_BIND=//p' .env.prod 2>/dev/null | tail -n1 | tr -d "\"' ")"
BUILD_GATEWAY_BIND="${BUILD_GATEWAY_BIND:-172.17.0.1}"
if [[ ! "$BUILD_GATEWAY_BIND" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]; then
  echo "HMM_BUILD_GATEWAY_BIND invalide dans .env.prod : '${BUILD_GATEWAY_BIND}'" >&2
  exit 2
fi

# `docker compose up` renomme temporairement l'ancien conteneur pendant un
# recreate (<hash>_<service>) avant de le supprimer -- rencontré en prod un
# conflit de nom transitoire sur cette étape (le renommage retente en
# interne mais peut renvoyer un exit non-nul le temps que ça se résolve,
# alors que l'état final est correct). 3 tentatives avant d'abandonner pour
# de vrai.
up_with_retry() {
  local attempt
  for attempt in 1 2 3; do
    if "${COMPOSE[@]}" up -d --wait "$@"; then
      return 0
    fi
    echo "up -d --wait $* : échec (tentative ${attempt}/3), nouvel essai dans 5s..."
    sleep 5
  done
  echo "up -d --wait $* : échec après 3 tentatives" >&2
  return 1
}

# Pull de TOUTES les images du portfolio tirées d'un registre (et plus
# seulement backend) : avec des images épinglées par digest, c'est ce qui
# applique réellement une montée de version de mysql, adminer... dès le
# déploiement qui la contient. Exclus volontairement :
#  - frontend : construit localement ci-dessous ;
#  - traefik et postfixadmin : partagés avec un autre projet du VPS, gérés
#    hors de ce pipeline (postfixadmin tourne en outre sur une image LOCALE
#    `postfixadmin:dbg`, absente de tout registre : un pull échouerait et
#    ferait échouer tout le déploiement — cf. README §13).
PULL_SERVICES=(database backend messenger-worker adminer)
echo "==> Images (${BACKEND_IMAGE_TAG}) : pull ${PULL_SERVICES[*]} + api-build-gateway"
"${COMPOSE[@]}" pull "${PULL_SERVICES[@]}"
"${COMPOSE[@]}" --profile build pull api-build-gateway

# Bind mounts écrits par www-data (UID/GID 82 dans l'image Alpine du
# backend, cf. Dockerfile) : logs applicatifs et sauvegardes DB. Docker les
# crée automatiquement au tout premier `up` s'ils n'existent pas, mais
# toujours owned par root -- jamais par www-data. Constaté en prod :
# sauvegardes en échec "Permission denied" (App\Service\DatabaseBackupService)
# et logs/backend jamais écrit (fail2ban lit un fichier qui n'existe pas,
# échec silencieux). `deploy` n'a pas de sudo (cf. README §11) pour un chown
# direct sur le host -- on passe par un conteneur éphémère (root par défaut)
# qui chown le bind mount depuis l'intérieur, même principe que
# l'appartenance au groupe docker déjà documentée là-bas. Idempotent, sans
# effet une fois la permission déjà correcte.
ensure_www_data_writable() {
  mkdir -p "$1"
  docker run --rm --entrypoint chown -v "$1:/target" "ghcr.io/horlynat/hmm-backend:${BACKEND_IMAGE_TAG}" -R 82:82 /target
}
ensure_www_data_writable /opt/hmm/infra/logs/backend
ensure_www_data_writable /opt/hmm/infra/backups

up_with_retry traefik database backend

echo "==> Migrations (toujours avant le worker -- cf. README §6)"
"${COMPOSE[@]}" exec -T -u www-data backend php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Worker Messenger (même image que backend, déjà pull ci-dessus)"
up_with_retry messenger-worker

# Le healthcheck Docker du backend ne teste que l'admin FrankenPHP
# (127.0.0.1:2019/config/, cf. docker-compose.prod.yml) -- il passe donc
# "Healthy" bien avant que Symfony/API Platform ne serve une vraie requête
# applicative. Or `next build` ci-dessous fait des fetch server-side vers ces
# endpoints pendant la génération statique et fait `throw` (aucune
# dégradation) si /home_contents ou /about_contents ne répondent pas dans les
# 8 s du timeout client (frontend/src/lib/api/client.ts). Sur un conteneur
# backend qui vient d'être recréé, la première requête sur ces endpoints
# (requête DB + sérialisation d'entités ~40 champs) peut dépasser ce budget.
# Comme `build --no-cache` rejoue `next build` à CHAQUE déploiement, cette
# fenêtre de quelques secondes de backend froid faisait échouer tout le
# déploiement (constaté en prod : run 33173366683, "Contenu de la page
# d'accueil indisponible" au prerender de /en). On sonde donc ici, via la
# même passerelle que le build (api-build-gateway, Host `hmm-backend` comme les
# RUN du build -- accepté par framework.trusted_hosts), jusqu'à ce que les
# endpoints critiques répondent réellement -- ce qui les laisse aussi chauds
# pour le build qui suit.
#
# La passerelle n'existe que le temps du build : supprimée juste après, et
# dans tous les cas à la sortie du script — échec compris, ET coupure de la
# session SSH (SIGHUP) ou interruption (SIGINT/SIGTERM).
#
# Les signaux ne font PAS appel directement à stop_build_gateway : ils
# terminent le script (code 128+n), et c'est le trap EXIT qui nettoie.
# `trap stop_build_gateway EXIT HUP INT TERM` serait faux : quand le signal
# arrive pendant une commande au premier plan (`docker compose build`, cas
# typique), bash exécute le handler à la fin de cette commande puis REPREND
# le script — le déploiement continuait sans session et finissait en code 0
# (testé). Idempotente : appelable plusieurs fois (appel explicite après le
# build + trap EXIT), n'agit qu'une fois, ne peut pas faire échouer le
# script sous `set -euo pipefail`.
GATEWAY_STOPPED=0
stop_build_gateway() {
  [[ "$GATEWAY_STOPPED" == 1 ]] && return 0
  GATEWAY_STOPPED=1
  "${COMPOSE[@]}" --profile build rm -sf api-build-gateway >/dev/null 2>&1 || true
}
trap stop_build_gateway EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM
echo "==> Passerelle de build (${BUILD_GATEWAY_BIND}:8000 -> hmm-backend:80)"
"${COMPOSE[@]}" --profile build up -d --no-deps api-build-gateway

echo "==> Attente API prête (endpoints qui font échouer next build s'ils traînent)"
wait_for_api() {
  local ep deadline
  # Seuls /home_contents et /about_contents font `throw` au prerender (page
  # d'accueil + "À propos") ; les autres fetch du build dégradent proprement
  # (liste vide). On ne bloque donc que sur ces deux-là.
  for ep in home_contents about_contents; do
    deadline=$((SECONDS + 120))
    until curl -fsS --max-time 10 -o /dev/null \
      -H 'Accept: application/ld+json' -H 'Host: hmm-backend' \
      "http://${BUILD_GATEWAY_BIND}:8000/api/${ep}"; do
      if ((SECONDS >= deadline)); then
        echo "API /${ep} toujours injoignable après 120 s -- build frontend annulé." >&2
        return 1
      fi
      sleep 3
    done
    echo "    /api/${ep} OK"
  done
}
wait_for_api

echo "==> Frontend : build local (backend joint via api-build-gateway pendant le build SSG/ISR, cf. Dockerfile)"
# --no-cache : constaté en prod, le cache de build Docker a repris tel quel
# les layers COPY . . et npm run build sur un déploiement où le code source
# avait pourtant changé (rsync avait bien livré le nouveau frontend/) --
# résultat : ancien code silencieusement resservi malgré un déploiement
# "réussi". Cause exacte non confirmée (probablement une interaction entre
# le remplacement atomique des fichiers par rsync et le cache de contexte de
# build BuildKit), mais le risque de servir du code obsolète sans erreur
# visible est trop coûteux pour un site en prod -- on paie le coût d'un
# rebuild complet à chaque déploiement plutôt que de rejouer ce genre de
# panne silencieuse.
"${COMPOSE[@]}" build --no-cache frontend
stop_build_gateway
up_with_retry frontend
# Frontend non-root : un secret illisible (groupe/droits du fichier sur
# l'hôte) n'empêche pas le démarrage mais désactive en silence la
# revalidation — on rend l'échec visible.
# Capture d'abord : `logs | grep -q` sous pipefail pouvait rater la ligne
# (SIGPIPE de l'écrivain -> pipeline en échec -> if faux), et un échec de
# `compose logs` lui-même fait ici échouer le script (set -e).
fe_logs="$("${COMPOSE[@]}" logs --no-log-prefix frontend 2>&1)"
if grep -qF '[entrypoint] AVERTISSEMENT' <<<"$fe_logs"; then
  echo "Frontend démarré SANS un de ses secrets (cf. logs frontend) -- vérifier HMM_SECRETS_GID et les droits de infra/secrets/." >&2
  exit 1
fi

echo "==> Reste de la stack (postfixadmin...) + nettoyage des orphelins"
# Rappel : relance TOUT service arrêté à la main (docker compose stop X) —
# pour garder un service arrêté, le retirer du compose, pas seulement le
# stopper (cf. README §6).
# Sans --wait ici, ce up recréait parfois backend/messenger-worker une 2e
# fois (Compose réconcilie tout le stack à ce stade) sans attendre leur
# healthcheck avant les curls de vérification finale juste après -- constaté
# en prod : backend encore "health: starting" au moment du curl, 404
# transitoire qui faisait échouer TOUT le déploiement alors que l'état final
# (quelques secondes plus tard) était sain. up_with_retry (--wait + retries
# sur le conflit de renommage transitoire, cf. plus haut) couvre ce cas.
up_with_retry --remove-orphans

echo "==> État final"
"${COMPOSE[@]}" ps

echo "==> Vérification santé publique"
curl -sf -o /dev/null -w "horlynat.com      : %{http_code}\n" https://horlynat.com
curl -sf -o /dev/null -w "api.horlynat.com  : %{http_code}\n" https://api.horlynat.com/api
# Le back-office ne doit PAS être servi par api. (routeur limité à
# /api, /uploads, /newsletter) : 404 attendu, tout autre code = régression.
code="$(curl -s -o /dev/null -w '%{http_code}' https://api.horlynat.com/login || true)"
if [[ "$code" != "404" ]]; then
  echo "ALERTE : https://api.horlynat.com/login renvoie ${code} (404 attendu) -- back-office exposé hors dark. ?" >&2
  exit 1
fi
echo "api.horlynat.com/login : 404 (attendu)"

# Nettoyage Docker -- seulement une fois le déploiement confirmé sain
# ci-dessus (jamais avant : un rollback manuel entre deux étapes doit garder
# les images/layers encore disponibles localement). Constaté en prod :
# 89,95 Go de cache de build accumulés en ~2 semaines (`docker system df`),
# alors que les vraies données (volumes uploads_data/mysql_data) pesaient
# quelques centaines de Mo. Purge intégrale (pas de filtre d'âge) et non
# `--filter until=...` : le `build --no-cache frontend` ci-dessus (nécessaire,
# cf. commentaire) écrit du cache neuf à CHAQUE déploiement sans jamais le
# relire -- même le cache créé il y a 5 minutes ne sert donc plus à rien dès
# cette ligne. Sans risque pour les volumes nommés : `image`/`builder prune`
# n'y touchent jamais, seul un `--volumes` explicite le ferait (absent ici).
echo "==> Nettoyage Docker (cache de build + images inutilisées)"
docker builder prune -af
docker image prune -af
