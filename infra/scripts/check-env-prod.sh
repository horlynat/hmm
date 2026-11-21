#!/usr/bin/env bash
#
# Cohérence des variables de sécurité EFFECTIVES du service backend, vérifiée
# AVANT tout `up` par deploy-remote.sh (contre-audit B2). Usage :
#
#   bash infra/scripts/check-env-prod.sh [.env.prod] [docker-compose.prod.yml]
#
# Les valeurs sont celles que Docker Compose résout lui-même (`config`) : même
# lecture de .env.prod que le vrai déploiement (espaces autour du `=`, syntaxe
# `X: y`, guillemets, `${VAR}`, défauts `${X:-…}`, variables du shell…) —
# relire .env.prod à la main laissait passer des formats que Compose accepte
# (contre-audit B2, 5e passe, C3).
#
# Refuse (code 1, message explicite) :
# - Cloudflare Access PARTIEL : CF_ACCESS_HOSTS, CF_ACCESS_TEAM_DOMAIN et
#   CF_ACCESS_AUD doivent être tous vides ou tous renseignés. CF_ACCESS_HOSTS
#   seul refuse tout le back-office en 403 (fail-closed) ; équipe/AUD sans
#   hôte n'active rien, en silence ;
# - PUBLIC_API_HOSTS sans `hmm-backend` : ce nom interne, joignable sans
#   passer par Cloudflare, servirait alors tout le back-office.
# Un échec de Compose lui-même (fichier illisible, syntaxe) refuse aussi.
set -euo pipefail

ENV_FILE="${1:-.env.prod}"
COMPOSE_FILE="${2:-docker-compose.prod.yml}"
[[ -f "$ENV_FILE" ]] || { echo "check-env-prod : $ENV_FILE introuvable." >&2; exit 1; }
[[ -f "$COMPOSE_FILE" ]] || { echo "check-env-prod : $COMPOSE_FILE introuvable." >&2; exit 1; }

compose_err="$(mktemp)"
trap 'rm -f "$compose_err"' EXIT
if ! resolved="$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --format json 2>"$compose_err")"; then
  echo "==> check-env-prod : docker compose ne peut pas résoudre la configuration ($ENV_FILE) — déploiement annulé." >&2
  # JAMAIS le texte brut de Compose (contre-audit B2, 7e passe) : ses
  # messages citent la valeur fautive (« unterminated quoted value "<mot de
  # passe> », « Invalid template: "…${…" »), et cette sortie finit dans les
  # logs GitHub Actions — PUBLICS sur ce dépôt. Seuls la nature de l'erreur
  # et le numéro de ligne sont repris — pas même un nom de variable : dans
  # `${X:?…}`, ce « nom » vient du texte de la valeur elle-même.
  cause="$(grep -oE 'line [0-9]+: (unterminated quoted value|key cannot contain a space|unexpected character)|Invalid template|required variable' "$compose_err" | head -n1 || true)"
  echo "    Cause : ${cause:-erreur de syntaxe ou de configuration}." >&2
  echo "    Détail (sur le VPS uniquement, jamais dans un log partagé) :" >&2
  echo "    cd /opt/hmm/infra && docker compose --env-file .env.prod -f docker-compose.prod.yml config -q" >&2
  exit 1
fi

# Contre-audit B2 (8e passe, F1) : un `$` non échappé dans une valeur
# (`A=s3cr$etPart`) ne fait pas échouer Compose, mais TRONQUE la valeur et
# fait afficher à chaque commande Compose suivante (pull, up, build…)
# « The "etPart" variable is not set » — un fragment de la valeur dans les
# logs publics. On refuse donc ici, sans citer aucun nom (il peut provenir de
# la valeur elle-même).
if grep -q 'variable is not set' "$compose_err"; then
  echo "==> check-env-prod : variable non définie dans la configuration résolue — déploiement annulé." >&2
  echo "    Cause probable : un \$ non échappé dans une valeur de $ENV_FILE (écrire \$\$), ou une variable du compose absente." >&2
  echo "    Détail (sur le VPS uniquement, jamais dans un log partagé) :" >&2
  echo "    cd /opt/hmm/infra && docker compose --env-file .env.prod -f docker-compose.prod.yml config -q" >&2
  exit 1
fi

printf '%s' "$resolved" | python3 -I -c '
import json, sys

env = (json.load(sys.stdin).get("services", {}).get("backend", {}) or {}).get("environment") or {}
if isinstance(env, list):  # forme « KEY=VAL » selon les versions de Compose
    env = dict(item.split("=", 1) if "=" in item else (item, "") for item in env)

# EXACTEMENT les blancs retirés par trim() côté PHP : " \t\n\r\0\x0B".
# PAS \x0c (saut de page) : PHP le garde, Python strip() le retirait — le
# contrôle passait alors que PHP ne reconnaissait plus hmm-backend
# (contre-audit B2, 7e passe). \0 est impossible dans une variable d env.
ASCII_WS = " \t\n\r\x0b"
ALLOWED = set("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,-_" + ASCII_WS)

def value(name):
    v = env.get(name)
    # Blancs ASCII seulement, comme trim()/strtolower() côté PHP
    # (PublicApiHostRestrictionSubscriber::normalizeHost) : Python strip()
    # retirerait aussi une espace insécable que PHP garderait.
    return "" if v is None else str(v).strip(ASCII_WS)

errors = []
# Contre-audit B2 (6e passe) : un caractère hors ASCII (espace insécable,
# signe Kelvin à la place d un K…) est normalisé différemment par Python et
# par PHP — le contrôle passait alors que PHP ne reconnaissait plus
# hmm-backend. On refuse donc tout caractère hors [A-Za-z0-9.,-_] et blancs.
def checked(name):
    v = value(name)
    if name == "CF_ACCESS_TEAM_DOMAIN":
        # Même tolérance que CloudflareAccessVerifier::__construct() :
        # schéma https?:// et barre finale retirés.
        low = v.lower()
        for prefix in ("https://", "http://"):
            if low.startswith(prefix):
                v = v[len(prefix):]
                break
        v = v.rstrip("/")
    return v

for name in ("CF_ACCESS_HOSTS", "CF_ACCESS_TEAM_DOMAIN", "CF_ACCESS_AUD", "PUBLIC_API_HOSTS"):
    bad = sorted({c for c in checked(name) if c not in ALLOWED})
    if bad:
        errors.append("%s contient des caractères non autorisés (%s) : seuls lettres ASCII, chiffres, « . , - _ » sont acceptés." % (name, " ".join("U+%04X" % ord(c) for c in bad)))

# Contre-audit B2 (8e passe, F2) : « renseigné » se juge sur ce que PHP LIRA,
# pas sur la valeur brute — `CF_ACCESS_HOSTS=,` donne une liste vide côté
# PHP (PublicApiHostRestrictionSubscriber::parseHostList) : Cloudflare
# Access désactivé en silence alors que le contrôle comptait 3/3.
def items(name):
    parts = [p.strip(ASCII_WS) for p in value(name).split(",")]
    return [p for p in parts if p]

effective = {
    "CF_ACCESS_HOSTS": [h.lower().rstrip(".") for h in items("CF_ACCESS_HOSTS") if h.rstrip(".")],
    "CF_ACCESS_TEAM_DOMAIN": [checked("CF_ACCESS_TEAM_DOMAIN")] if checked("CF_ACCESS_TEAM_DOMAIN") else [],
    "CF_ACCESS_AUD": items("CF_ACCESS_AUD"),
}
for name, vals in effective.items():
    for v in vals:
        if any(c in ASCII_WS for c in v):
            errors.append("%s contient un blanc à l intérieur d une valeur : la valeur lue par PHP serait inutilisable." % name)
            break

cf = [n for n, vals in effective.items() if vals]
if len(cf) not in (0, 3):
    errors.append(
        "Cloudflare Access partiel : CF_ACCESS_HOSTS, CF_ACCESS_TEAM_DOMAIN et CF_ACCESS_AUD "
        "doivent être tous vides ou tous renseignés (%d/3 renseignés : %s)." % (len(cf), ", ".join(cf))
    )

hosts = [h.strip(ASCII_WS).lower().rstrip(".") for h in value("PUBLIC_API_HOSTS").split(",")]
if "hmm-backend" not in hosts:
    errors.append(
        "PUBLIC_API_HOSTS effectif sans « hmm-backend » : ce nom interne servirait tout le back-office "
        "sans passer par Cloudflare. Valeur attendue (ou retirer la ligne de .env.prod) : "
        "api.horlynat.com,hmm-backend,backend"
    )

if errors:
    print("==> Variables de sécurité incohérentes, déploiement annulé avant toute action :", file=sys.stderr)
    for e in errors:
        print("    - " + e, file=sys.stderr)
    sys.exit(1)
'
