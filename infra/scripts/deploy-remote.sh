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
"${COMPOSE[@]}" build frontend
up_with_retry frontend

echo "==> Reste de la stack (postfixadmin...) + nettoyage des orphelins"
"${COMPOSE[@]}" up -d --remove-orphans

echo "==> État final"
"${COMPOSE[@]}" ps

echo "==> Vérification santé publique"
curl -sf -o /dev/null -w "horlynat.com      : %{http_code}\n" https://horlynat.com
curl -sf -o /dev/null -w "api.horlynat.com  : %{http_code}\n" https://api.horlynat.com/api
