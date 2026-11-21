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
  # Les avertissements (variables non définies…) ne sont montrés qu'en cas
  # d'échec, avec la cause réelle.
  tail -n 5 "$compose_err" | sed 's/^/    /' >&2
  exit 1
fi

printf '%s' "$resolved" | python3 -I -c '
import json, sys

env = (json.load(sys.stdin).get("services", {}).get("backend", {}) or {}).get("environment") or {}
if isinstance(env, list):  # forme « KEY=VAL » selon les versions de Compose
    env = dict(item.split("=", 1) if "=" in item else (item, "") for item in env)

ASCII_WS = " \t\r\n\x0b\x0c"
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
for name in ("CF_ACCESS_HOSTS", "CF_ACCESS_TEAM_DOMAIN", "CF_ACCESS_AUD", "PUBLIC_API_HOSTS"):
    bad = sorted({c for c in value(name) if c not in ALLOWED})
    if bad:
        errors.append("%s contient des caractères non autorisés (%s) : seuls lettres ASCII, chiffres, « . , - _ » sont acceptés." % (name, " ".join("U+%04X" % ord(c) for c in bad)))

cf = [n for n in ("CF_ACCESS_HOSTS", "CF_ACCESS_TEAM_DOMAIN", "CF_ACCESS_AUD") if value(n)]
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
