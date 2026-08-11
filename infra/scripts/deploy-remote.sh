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
# BACKEND_IMAGE_TAG : tag de l'image ghcr.io/horlynat/hmm-backend à déployer
# (sha du commit, ou tag antérieur pour un rollback manuel). Défaut "latest"
# pour un lancement manuel direct sur le VPS.
set -euo pipefail

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

export BACKEND_IMAGE_TAG="${BACKEND_IMAGE_TAG:-latest}"

COMPOSE=(docker compose --env-file .env.prod -f docker-compose.prod.yml)

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

echo "==> Backend (${BACKEND_IMAGE_TAG}) : pull + traefik/database à jour"
"${COMPOSE[@]}" pull backend
up_with_retry traefik database backend

echo "==> Migrations (toujours avant le worker -- cf. README §6)"
"${COMPOSE[@]}" exec -T -u www-data backend php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Worker Messenger (même image que backend, déjà pull ci-dessus)"
up_with_retry messenger-worker

echo "==> Frontend : build local (a besoin de backend joignable en 127.0.0.1:8000 pendant le build SSG/ISR, cf. Dockerfile)"
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
up_with_retry frontend

echo "==> Reste de la stack (postfixadmin...) + nettoyage des orphelins"
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
