#!/usr/bin/env bash
#
# Cohérence des variables de sécurité de .env.prod, vérifiée AVANT tout appel
# Compose par deploy-remote.sh (contre-audit B2, 3e passe). Usage :
#
#   bash infra/scripts/check-env-prod.sh [chemin/vers/.env.prod]
#
# Refuse (code 1, message explicite) :
# - une configuration Cloudflare Access PARTIELLE : CF_ACCESS_HOSTS,
#   CF_ACCESS_TEAM_DOMAIN et CF_ACCESS_AUD doivent être tous vides ou tous
#   renseignés. CF_ACCESS_HOSTS seul refuse tout le back-office en 403
#   (fail-closed) ; équipe/AUD sans hôte n'active rien en silence ;
# - un PUBLIC_API_HOSTS surchargé qui oublie `hmm-backend` : le nom interne,
#   joignable sans passer par Cloudflare, servirait alors tout le back-office.
#
# Une variable absente de .env.prod prend la valeur par défaut du compose
# (CF_ACCESS_* vides, PUBLIC_API_HOSTS incluant hmm-backend) : rien à signaler.
set -euo pipefail

ENV_FILE="${1:-.env.prod}"
[[ -f "$ENV_FILE" ]] || { echo "check-env-prod : $ENV_FILE introuvable." >&2; exit 1; }

# Dernière affectation de la variable (comme docker compose), guillemets et
# espaces de bord retirés. Statut 1 si la variable n'est pas définie.
value_of() {
  local line
  line="$(grep -E "^[[:space:]]*(export[[:space:]]+)?$1=" "$ENV_FILE" | tail -n1)" || return 1
  line="${line#*=}"
  line="${line%%[[:space:]]#*}"
  line="$(printf '%s' "$line" | sed -E "s/^[[:space:]]*['\"]?//; s/['\"]?[[:space:]]*$//")"
  printf '%s' "$line"
}

errors=()

set_count=0
for name in CF_ACCESS_HOSTS CF_ACCESS_TEAM_DOMAIN CF_ACCESS_AUD; do
  v="$(value_of "$name" || true)"
  [[ -n "$v" ]] && set_count=$((set_count + 1))
done
if ((set_count != 0 && set_count != 3)); then
  errors+=("Cloudflare Access partiel : CF_ACCESS_HOSTS, CF_ACCESS_TEAM_DOMAIN et CF_ACCESS_AUD doivent être tous vides ou tous renseignés ($set_count/3 renseignés).")
fi

if hosts="$(value_of PUBLIC_API_HOSTS)"; then
  normalized=",$(printf '%s' "$hosts" | tr 'A-Z' 'a-z' | tr -d '[:space:]'),"
  if [[ "$normalized" != *",hmm-backend,"* ]]; then
    errors+=("PUBLIC_API_HOSTS est surchargé sans « hmm-backend » : ce nom interne servirait tout le back-office sans passer par Cloudflare. Valeur attendue (ou retirer la ligne) : api.horlynat.com,hmm-backend,backend")
  fi
fi

if ((${#errors[@]} > 0)); then
  echo "==> .env.prod incohérent, déploiement annulé avant toute action :" >&2
  printf '    - %s\n' "${errors[@]}" >&2
  exit 1
fi
