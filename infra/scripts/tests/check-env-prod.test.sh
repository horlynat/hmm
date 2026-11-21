#!/usr/bin/env bash
# Banc d'attaque de infra/scripts/check-env-prod.sh (aucun accès VPS).
# Usage : bash infra/scripts/tests/check-env-prod.test.sh
set -uo pipefail
CHECK="$(cd "$(dirname "$0")/.." && pwd)/check-env-prod.sh"
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
fail=0

t() { local exp=$1 label=$2 content=$3 rc
  printf '%s\n' "$content" > "$T/env"
  bash "$CHECK" "$T/env" >/dev/null 2>&1; rc=$?
  if { [[ $exp == OK ]] && ((rc == 0)); } || { [[ $exp == KO ]] && ((rc != 0)); }; then
    echo "PASS $exp $label"
  else echo "FAIL $exp rc=$rc $label"; fail=1; fi; }

# Cas sains
t OK "rien de défini (défauts du compose)" "APP_ENV=prod"
t OK "Cloudflare complet" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=abc'
t OK "Cloudflare complet, guillemets + export" $'export CF_ACCESS_HOSTS="dark.horlynat.com"\nCF_ACCESS_TEAM_DOMAIN=\'x.cloudflareaccess.com\'\nCF_ACCESS_AUD=abc # app'
t OK "Cloudflare explicitement vide" $'CF_ACCESS_HOSTS=\nCF_ACCESS_TEAM_DOMAIN=""\nCF_ACCESS_AUD='
t OK "PUBLIC_API_HOSTS correct" "PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend,backend"
t OK "PUBLIC_API_HOSTS majuscules/espaces" "PUBLIC_API_HOSTS=api.horlynat.com, HMM-BACKEND ,backend"
t OK "commentaire ignoré" $'# CF_ACCESS_HOSTS=dark.horlynat.com\nAPP_ENV=prod'

# Attaques / erreurs de configuration
t KO "hôte Cloudflare seul" "CF_ACCESS_HOSTS=dark.horlynat.com"
t KO "équipe + AUD sans hôte" $'CF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=abc'
t KO "2/3 avec guillemets" $'CF_ACCESS_HOSTS="dark.horlynat.com"\nCF_ACCESS_TEAM_DOMAIN="x.cloudflareaccess.com"\nCF_ACCESS_AUD=""'
t KO "dernière affectation vide l'AUD" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=abc\nCF_ACCESS_AUD='
t KO "PUBLIC_API_HOSTS sans hmm-backend" "PUBLIC_API_HOSTS=api.horlynat.com"
t KO "PUBLIC_API_HOSTS nom piège" "PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend-evil"
t KO "PUBLIC_API_HOSTS vide" "PUBLIC_API_HOSTS="
t KO "dernière affectation retire hmm-backend" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend\nPUBLIC_API_HOSTS=api.horlynat.com'

bash "$CHECK" "$T/absent" >/dev/null 2>&1 && { echo "FAIL fichier absent accepté"; fail=1; } || echo "PASS KO fichier absent"

exit $fail
