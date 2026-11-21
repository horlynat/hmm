#!/usr/bin/env bash
# Banc d'attaque de infra/scripts/check-env-prod.sh (aucun accès VPS).
# Usage : bash infra/scripts/tests/check-env-prod.test.sh
set -uo pipefail
CHECK="$(cd "$(dirname "$0")/.." && pwd)/check-env-prod.sh"
COMPOSE="$(cd "$(dirname "$0")/../.." && pwd)/docker-compose.prod.yml"
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
fail=0

# Variables que le compose de prod exige toujours (comme un vrai .env.prod) :
# sans elles, Compose signale « variable is not set » et le contrôle refuse
# (à juste titre, cf. 8e passe F1) — chaque cas part donc de cette base.
BASE=$'APP_DEFAULT_SENDER=no-reply@example.test\nNEXT_PUBLIC_SITE_URL=https://example.test\nNEXT_PUBLIC_MEDIA_URL=https://api.example.test'

t() { local exp=$1 label=$2 content=$3 rc
  printf '%s\n%s\n' "$BASE" "$content" > "$T/env"
  env -u CF_ACCESS_HOSTS -u CF_ACCESS_TEAM_DOMAIN -u CF_ACCESS_AUD -u PUBLIC_API_HOSTS \
    bash "$CHECK" "$T/env" "$COMPOSE" >/dev/null 2>&1; rc=$?
  if { [[ $exp == OK ]] && ((rc == 0)); } || { [[ $exp == KO ]] && ((rc != 0)); }; then
    echo "PASS $exp $label"
  else echo "FAIL $exp rc=$rc $label"; fail=1; fi; }

# Cas sains
t OK "rien de défini (défauts du compose)" "APP_ENV=prod"
t OK "Cloudflare complet" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t OK "Cloudflare complet, guillemets + export" $'export CF_ACCESS_HOSTS="dark.horlynat.com"\nCF_ACCESS_TEAM_DOMAIN=\'x.cloudflareaccess.com\'\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef # app'
t OK "Cloudflare explicitement vide" $'CF_ACCESS_HOSTS=\nCF_ACCESS_TEAM_DOMAIN=""\nCF_ACCESS_AUD='
t OK "PUBLIC_API_HOSTS correct" "PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend,backend"
t OK "PUBLIC_API_HOSTS majuscules/espaces" "PUBLIC_API_HOSTS=api.horlynat.com, HMM-BACKEND ,backend"
t OK "commentaire ignoré" $'# CF_ACCESS_HOSTS=dark.horlynat.com\nAPP_ENV=prod'

# Attaques / erreurs de configuration
t KO "hôte Cloudflare seul" "CF_ACCESS_HOSTS=dark.horlynat.com"
t KO "équipe + AUD sans hôte" $'CF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t KO "2/3 avec guillemets" $'CF_ACCESS_HOSTS="dark.horlynat.com"\nCF_ACCESS_TEAM_DOMAIN="x.cloudflareaccess.com"\nCF_ACCESS_AUD=""'
t KO "dernière affectation vide l'AUD" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef\nCF_ACCESS_AUD='
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
t KO "équipe + AUD en « X = y », sans hôte" $'CF_ACCESS_TEAM_DOMAIN = x.cloudflareaccess.com\nCF_ACCESS_AUD = 0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t KO "AUD = \${NOPE} (vide une fois résolu)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=${NOPE}'
t OK "Cloudflare complet en « X = y »" $'CF_ACCESS_HOSTS = dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN = x.cloudflareaccess.com\nCF_ACCESS_AUD = 0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
# Caractères non ASCII normalisés différemment par Python et PHP (6e passe)
t KO "espace insécable après hmm-backend" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend\u00a0,backend'
t KO "signe Kelvin à la place d'un K" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-bac\u212aend,backend'
t KO "caractère non ASCII dans l'AUD" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=ab\u00e9'
t KO "saut de page (\\f) après hmm-backend" $'PUBLIC_API_HOSTS="api.horlynat.com,hmm-backend\\f,backend"'
t OK "tabulation verticale (\\v) retirée des deux côtés" $'PUBLIC_API_HOSTS="api.horlynat.com,hmm-backend\\v,backend"'
t OK "équipe Cloudflare avec https:// et / final (toléré par PHP)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=https://x.cloudflareaccess.com/\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
# 8e passe — F2 : valeurs dégénérées jugées comme PHP les lit
t KO "hôte Cloudflare réduit à une virgule (= vide pour PHP)" $'CF_ACCESS_HOSTS=,\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t KO "équipe réduite à https:// (= vide pour PHP)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=https://\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t KO "AUD réduit à des virgules (= vide pour PHP)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=" , "'
t KO "blanc interne dans l'équipe" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN="t.com /"\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
t OK "plusieurs AUD séparés par des virgules" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef, fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'
t KO "audience « 0 » (format Cloudflare invalide)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0'
# 10e passe — noms internes acceptés par Symfony mais pas limités à /api
t KO "backend accepté par les hôtes de confiance, absent de PUBLIC_API_HOSTS" $'SYMFONY_TRUSTED_HOSTS=^(api|dark)\\.horlynat\\.com$$,^hmm-backend$$,^backend$$\nPUBLIC_API_HOSTS=api.horlynat.com,hmm-backend'
t OK "backend accepté ET limité à /api" $'SYMFONY_TRUSTED_HOSTS=^(api|dark)\\.horlynat\\.com$$,^hmm-backend$$,^backend$$\nPUBLIC_API_HOSTS=api.horlynat.com,hmm-backend,backend'
t KO "localhost accepté (motif trop large)" $'SYMFONY_TRUSTED_HOSTS=^(api|dark)\\.horlynat\\.com$$,^hmm-backend$$,^localhost$$'
t KO "motif qui accepte tout" $'SYMFONY_TRUSTED_HOSTS=.*'
t KO "casse : ^BACKEND$ accepte backend (Symfony ignore la casse)" $'SYMFONY_TRUSTED_HOSTS=^hmm-backend$$,^BACKEND$$\nPUBLIC_API_HOSTS=api.horlynat.com,hmm-backend'
t KO "motif illisible" $'SYMFONY_TRUSTED_HOSTS=^(api$$'
t KO "AUD tronqué (63 caractères)" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcde'
t KO "AUD non hexadécimal" $'CF_ACCESS_HOSTS=dark.horlynat.com\nCF_ACCESS_TEAM_DOMAIN=x.cloudflareaccess.com\nCF_ACCESS_AUD=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdez'
t KO "dernière affectation retire hmm-backend" $'PUBLIC_API_HOSTS=api.horlynat.com,hmm-backend\nPUBLIC_API_HOSTS=api.horlynat.com'

bash "$CHECK" "$T/absent" "$COMPOSE" >/dev/null 2>&1 && { echo "FAIL fichier absent accepté"; fail=1; } || echo "PASS KO fichier absent"
printf 'A=1\n' > "$T/env"
bash "$CHECK" "$T/env" "$T/absent.yml" >/dev/null 2>&1 && { echo "FAIL compose absent accepté"; fail=1; } || echo "PASS KO compose absent"
# Variable du shell : Compose la fait passer AVANT .env.prod — le contrôle la voit aussi.
printf '%s\nAPP_ENV=prod\n' "$BASE" > "$T/env"
CF_ACCESS_HOSTS=dark.horlynat.com bash "$CHECK" "$T/env" "$COMPOSE" >/dev/null 2>&1 \
  && { echo "FAIL variable du shell ignorée"; fail=1; } || echo "PASS KO Cloudflare partiel via l'environnement du shell"

# 8e passe — F1 : `$` non échappé = valeur tronquée + fragment affiché par
# chaque commande Compose suivante ; refusé sans citer le fragment.
printf '%s\nA=s3cr$etPartXYZ\n' "$BASE" > "$T/env"
out="$(bash "$CHECK" "$T/env" "$COMPOSE" 2>&1)" && { echo "FAIL \$ non échappé accepté"; fail=1; } \
  || { grep -q 'etPartXYZ' <<<"$out" && { echo "FAIL fragment de valeur affiché"; fail=1; } || echo "PASS KO \$ non échappé refusé, sans fragment"; }

printf 'APP_ENV=prod\n' > "$T/env"
bash "$CHECK" "$T/env" "$COMPOSE" >/dev/null 2>&1 && { echo "FAIL variable exigée absente acceptée"; fail=1; } || echo "PASS KO variable exigée par le compose absente"

# 9e passe : une future version de Compose formulant autrement son
# avertissement doit aussi être refusée (faux `docker` en tête du PATH).
mkdir -p "$T/bin"
cat > "$T/bin/docker" <<'SHIM'
#!/usr/bin/env bash
echo 'time="2030-01-01" level=warning msg="Unresolved variable reference s3cr in value"' >&2
echo '{"services":{"backend":{"environment":{"PUBLIC_API_HOSTS":"api.horlynat.com,hmm-backend,backend","SYMFONY_TRUSTED_HOSTS":"^(api|dark)\\.horlynat\\.com$,^hmm-backend$"}}}}'
SHIM
chmod +x "$T/bin/docker"
printf '%s\n' "$BASE" > "$T/env"
out="$(PATH="$T/bin:$PATH" bash "$CHECK" "$T/env" "$COMPOSE" 2>&1)" && { echo "FAIL avertissement reformulé accepté"; fail=1; } \
  || { grep -q 's3cr' <<<"$out" && { echo "FAIL fragment affiché (reformulé)"; fail=1; } || echo "PASS KO avertissement « variable » reformulé, refusé sans fragment"; }
cat > "$T/bin/docker" <<'SHIM'
#!/usr/bin/env bash
echo 'level=warning msg="docker-compose version attribute is obsolete"' >&2
echo '{"services":{"backend":{"environment":{"PUBLIC_API_HOSTS":"api.horlynat.com,hmm-backend,backend","SYMFONY_TRUSTED_HOSTS":"^(api|dark)\\.horlynat\\.com$,^hmm-backend$"}}}}'
SHIM
PATH="$T/bin:$PATH" bash "$CHECK" "$T/env" "$COMPOSE" >/dev/null 2>&1 && echo "PASS OK avertissement sans rapport avec les variables" || { echo "FAIL avertissement anodin refusé"; fail=1; }

# Aucune valeur de .env.prod ne doit apparaître dans la sortie d'erreur
# (elle finit dans les logs GitHub Actions, publics) — contre-audit B2.
for leak in 'DB_PASS="S3cr3tLeak' 'API_KEY=abc${S3cr3tLeak' 'MAILER=x${S3cr3tLeak:?S3cr3tLeak}'; do
  printf '%s\n%s\n' "$BASE" "$leak" > "$T/env"
  out="$(bash "$CHECK" "$T/env" "$COMPOSE" 2>&1)" && { echo "FAIL fichier invalide accepté : $leak"; fail=1; continue; }
  if grep -q 'S3cr3tLeak' <<<"$out"; then echo "FAIL valeur secrète affichée : $leak"; fail=1; else echo "PASS KO erreur sans valeur secrète ($leak)"; fi
done

exit $fail
