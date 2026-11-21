#!/usr/bin/env bash
# Banc d'attaque de infra/scripts/check-env-prod.sh (aucun accès VPS).
# Usage : bash infra/scripts/tests/check-env-prod.test.sh
set -uo pipefail
CHECK="$(cd "$(dirname "$0")/.." && pwd)/check-env-prod.sh"
COMPOSE="$(cd "$(dirname "$0")/../.." && pwd)/docker-compose.prod.yml"
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
fail=0

t() { local exp=$1 label=$2 content=$3 rc
  printf '%s\n' "$content" > "$T/env"
  env -u CF_ACCESS_HOSTS -u CF_ACCESS_TEAM_DOMAIN -u CF_ACCESS_AUD -u PUBLIC_API_HOSTS \
    bash "$CHECK" "$T/env" "$COMPOSE" >/dev/null 2>&1; rc=$?
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
# `${PUBLIC_API_HOSTS:-défaut}` : une valeur VIDE prend le défaut (sûr)
t OK "PUBLIC_API_HOSTS vide = défaut du compose" "PUBLIC_API_HOSTS="
t OK "PUBLIC_API_HOSTS vide entre guillemets" 'PUBLIC_API_HOSTS=""'
t OK "PUBLIC_API_HOSTS avec point final" "PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend.,backend"

# Formats acceptés par Compose que l'ancienne lecture manuelle ratait (C3)
t KO "espaces autour du = (PUBLIC_API_HOSTS)" "PUBLIC_API_HOSTS = api.horlynat.com"
t KO "syntaxe deux-points (PUBLIC_API_HOSTS)" "PUBLIC_API_HOSTS: api.horlynat.com"
t KO "espaces autour du = (CF hôte seul)" "CF_ACCESS_HOSTS = dark.horlynat.com"
t KO "équipe + AUD en « X = y », sans hôte" $'CF_ACCESS_TEAM_DOMAIN = x.cloudflareaccess.com\nCF_ACCESS_AUD = abc'
t KO "AUD = \${NOPE} (vide une fois résolu)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=${NOPE}'
t OK "Cloudflare complet en « X = y »" $'CF_ACCESS_HOSTS = dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN = x.cloudflareaccess.com\nCF_ACCESS_AUD = abc'
# Caractères non ASCII normalisés différemment par Python et PHP (6e passe)
t KO "espace insécable après hmm-backend" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend\u00a0,backend'
t KO "signe Kelvin à la place d'un K" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-bac\u212aend,backend'
t KO "caractère non ASCII dans l'AUD" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=ab\u00e9'
t KO "saut de page (\\f) après hmm-backend" $'PUBLIC_API_HOSTS="api.horlynat.com,hmm-backend\\f,backend"'
t OK "tabulation verticale (\\v) retirée des deux côtés" $'PUBLIC_API_HOSTS="api.horlynat.com,hmm-backend\\v,backend"'
t OK "équipe Cloudflare avec https:// et / final (toléré par PHP)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=https://x.cloudflareaccess.com/\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t KO "dernière affectation retire hmm-backend" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend\nPUBLIC_API_HOSTS=api.horlynat.com'

bash "$CHECK" "$T/absent" "$COMPOSE" >/dev/null 2>&1 && { echo "FAIL fichier absent accepté"; fail=1; } || echo "PASS KO fichier absent"
printf 'A=1\n' > "$T/env"
bash "$CHECK" "$T/env" "$T/absent.yml" >/dev/null 2>&1 && { echo "FAIL compose absent accepté"; fail=1; } || echo "PASS KO compose absent"
# Variable du shell : Compose la fait passer AVANT .env.prod — le contrôle la voit aussi.
printf 'APP_ENV=prod\n' > "$T/env"
CF_ACCESS_HOSTS=dark.horlynat.com bash "$CHECK" "$T/env" "$COMPOSE" >/dev/null 2>&1 \
  && { echo "FAIL variable du shell ignorée"; fail=1; } || echo "PASS KO Cloudflare partiel via l'environnement du shell"

# Aucune valeur de .env.prod ne doit apparaître dans la sortie d'erreur
# (elle finit dans les logs GitHub Actions, publics) — contre-audit B2.
for leak in 'DB_PASS="S3cr3tLeak' 'API_KEY=abc${S3cr3tLeak' 'MAILER=x${S3cr3tLeak:?S3cr3tLeak}'; do
  printf '%s\n' "$leak" > "$T/env"
  out="$(bash "$CHECK" "$T/env" "$COMPOSE" 2>&1)" && { echo "FAIL fichier invalide accepté : $leak"; fail=1; continue; }
  if grep -q 'S3cr3tLeak' <<<"$out"; then echo "FAIL valeur secrète affichée : $leak"; fail=1; else echo "PASS KO erreur sans valeur secrète ($leak)"; fi
done

exit $fail
