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
# Refuse (code 1, message explicite, JAMAIS de valeur de .env.prod : cette
# sortie finit dans les logs GitHub Actions, publics sur ce dépôt) :
# - un échec de Compose (syntaxe, guillemet non fermé…) : seules la nature de
#   l'erreur et le numéro de ligne sont affichés ;
# - tout avertissement « variable non définie » : un `$` non échappé dans une
#   valeur la tronque et ferait afficher un fragment de la valeur par chaque
#   commande Compose suivante. Écrire `$$` pour un `$` littéral ;
# - Cloudflare Access PARTIEL, jugé sur ce que PHP lira : CF_ACCESS_HOSTS,
#   CF_ACCESS_TEAM_DOMAIN et CF_ACCESS_AUD tous vides ou tous renseignés ;
#   AUD au format Cloudflare (64 caractères hexadécimaux) ;
# - des caractères hors ASCII ou des blancs internes dans ces variables (PHP
#   et Python ne les normaliseraient pas pareil) ;
# - PUBLIC_API_HOSTS sans `hmm-backend`, et tout nom interne du réseau Docker
#   (backend, localhost…) accepté par SYMFONY_TRUSTED_HOSTS sans être limité
#   à /api : il servirait le back-office sans passer par Cloudflare ;
#   SYMFONY_TRUSTED_HOSTS vide (tout Host accepté) aussi.
#
# Limite connue (non détectable ici) : un `$` suivi du nom d'une variable
# DÉJÀ définie (`PASS=ab$HOME`) est remplacé en silence par Compose, sans
# avertissement. Pas de fuite dans les logs, mais une valeur altérée :
# toujours écrire `$$` pour un `$` littéral dans .env.prod.
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
# Repérage large (contre-audit B2, 9e passe) : le texte exact de compose-go
# peut changer d'une version à l'autre — tout avertissement qui parle d'une
# variable est refusé, pas seulement la formulation actuelle.
if grep -qiE 'variable is not set|level=warn[a-z]*.*variable' "$compose_err"; then
  echo "==> check-env-prod : variable non définie dans la configuration résolue — déploiement annulé." >&2
  echo "    Cause probable : un \$ non échappé dans une valeur de $ENV_FILE (écrire \$\$), ou une variable du compose absente." >&2
  echo "    Détail (sur le VPS uniquement, jamais dans un log partagé) :" >&2
  echo "    cd /opt/hmm/infra && docker compose --env-file .env.prod -f docker-compose.prod.yml config -q" >&2
  exit 1
fi

printf '%s' "$resolved" | python3 -I -c '
import json, re, sys

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
# signe Kelvin à la place d’un K…) est normalisé différemment par Python et
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
            errors.append("%s contient un blanc à l’intérieur d’une valeur : la valeur lue par PHP serait inutilisable." % name)
            break

# Format réel des AUD Cloudflare Access : 64 caractères hexadécimaux. Une
# valeur tronquée ou mal collée (« 0 », « abc »…) n est jamais émise par
# Cloudflare : elle bloquerait tout le back-office en 403 — refusée ici,
# au déploiement, avec un message clair (contre-audit B2, 10e passe).
for aud in effective["CF_ACCESS_AUD"]:
    if not re.fullmatch(r"[0-9a-fA-F]{64}", aud):
        errors.append("CF_ACCESS_AUD : « %d caractères » ne correspond pas au format Cloudflare (64 caractères hexadécimaux, Access -> Applications -> Overview)." % len(aud))
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

# Contre-audit B2 (11e passe, C1-C3) — invariant : TOUT hôte accepté par
# Symfony (SYMFONY_TRUSTED_HOSTS) doit être limité à /api (PUBLIC_API_HOSTS),
# protégé par Cloudflare (CF_ACCESS_HOSTS) ou être l hôte du back-office
# (BACKOFFICE_URL). Un voisin du réseau Docker choisit librement son en-tête
# Host : tout autre nom accepté servirait le back-office sans Cloudflare.
#  - Découpage IDENTIQUE à Symfony (Kernel::boot) et `$$` de Compose annulé ;
#    un élément VIDE est refusé : Symfony en fait un motif qui accepte tout
#    (une simple virgule en trop suffisait).
#  - Grammaire FERMÉE (`^…$`, lettres, chiffres, `-`, `\.`, groupes `(a|b)`) :
#    Python et PCRE la lisent forcément pareil, et chaque motif se développe
#    en une liste FINIE d hôtes, comparée à l invariant.
from urllib.parse import urlsplit

def split_like_symfony(raw):
    return re.split(r"\s*,\s*(?![^{]*\})", raw)

GRAMMAR = re.compile(r"\^(?:[a-z0-9-]|\\\.|[()|])+\$", re.IGNORECASE)

def expand(body, pos=0, depth=0):
    # Développe body[pos:] jusqu à `)` ou fin ; renvoie (liste, position).
    if depth > 5:
        raise ValueError("imbrication")
    alternatives, current = [], [""]
    while pos < len(body):
        c = body[pos]
        if c == "\\":
            current = [x + "." for x in current]
            pos += 2
        elif c == "(":
            sub, pos = expand(body, pos + 1, depth + 1)
            current = [x + y for x in current for y in sub]
            pos += 1  # `)`
        elif c == ")":
            break
        elif c == "|":
            alternatives += current
            current = [""]
            pos += 1
        else:
            current = [x + c.lower() for x in current]
            pos += 1
        if len(current) + len(alternatives) > 500:
            raise ValueError("trop de variantes")
    return alternatives + current, pos

raw_trusted = value("SYMFONY_TRUSTED_HOSTS").replace("$$", "$")
trusted = [t.strip(ASCII_WS) for t in split_like_symfony(raw_trusted)] if raw_trusted else []
if not trusted:
    errors.append(
        "SYMFONY_TRUSTED_HOSTS effectif vide : Symfony accepterait n’importe quel Host, y compris les noms "
        "internes du réseau Docker, qui serviraient le back-office sans Cloudflare."
    )
elif any(t == "" for t in trusted):
    errors.append(
        "SYMFONY_TRUSTED_HOSTS contient un élément vide (virgule en trop) : Symfony en ferait un motif qui "
        "accepte n’importe quel Host."
    )
else:
    accepted = set()
    for t in trusted:
        if not GRAMMAR.fullmatch(t) or t.count("(") != t.count(")"):
            errors.append(
                "SYMFONY_TRUSTED_HOSTS : motif hors de la forme vérifiable (^nom$, lettres, chiffres, -, \\., "
                "groupes (a|b)). Quantificateurs, classes [ ] et jokers sont refusés : impossible de prouver "
                "quels hôtes ils acceptent."
            )
            accepted = None
            break
        try:
            names, _ = expand(t[1:-1])
        except (ValueError, IndexError):
            errors.append("SYMFONY_TRUSTED_HOSTS : motif trop complexe pour être vérifié.")
            accepted = None
            break
        accepted.update(n.rstrip(".") for n in names)
    if accepted is not None:
        cf_hosts = set(effective["CF_ACCESS_HOSTS"]) if len(cf) == 3 else set()
        bo_host = (urlsplit(value("BACKOFFICE_URL")).hostname or "").lower().rstrip(".")
        allowed = set(hosts) | cf_hosts | ({bo_host} if bo_host else set())
        exposed = sorted(h for h in accepted if h not in allowed)
        if exposed:
            errors.append(
                "Hôte(s) accepté(s) par SYMFONY_TRUSTED_HOSTS sans être limité(s) à /api (PUBLIC_API_HOSTS) ni "
                "protégé(s) par Cloudflare (CF_ACCESS_HOSTS) ni être le back-office (BACKOFFICE_URL) : %s — "
                "ils serviraient tout le back-office sans passer par Cloudflare." % ", ".join(exposed)
            )
        if bo_host and bo_host not in cf_hosts:
            print(
                "    Avertissement : le back-office (%s) n’est pas protégé par Cloudflare Access côté application "
                "(CF_ACCESS_* vides) — un conteneur du réseau Docker partagé peut l’atteindre directement "
                "(compte, mot de passe et 2FA restent exigés)." % bo_host,
                file=sys.stderr,
            )

if errors:
    print("==> Variables de sécurité incohérentes, déploiement annulé avant toute action :", file=sys.stderr)
    for e in errors:
        print("    - " + e, file=sys.stderr)
    sys.exit(1)
'
