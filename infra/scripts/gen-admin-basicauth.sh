#!/usr/bin/env bash
#
# Génère le secret `infra/secrets/admin_basicauth` : fichier htpasswd (bcrypt)
# qui protège Adminer (db.horlynat.com) à l'origine, en 2ᵉ barrière devant
# Cloudflare Access (cf. README §10.5 + traefik/dynamic.yml, middleware
# admin-basicauth@file).
#
# À exécuter UNE FOIS sur le VPS, dans /opt/hmm, avant le premier déploiement
# de cette fonctionnalité — sinon `deploy-remote.sh` bloque (garde-fou
# secrets) et db.horlynat.com reste injoignable (fail-closed voulu).
#
# Usage :
#   ./infra/scripts/gen-admin-basicauth.sh [utilisateur]   (défaut : admin)
#
# Le mot de passe est tiré au hasard (24 caractères), écrit nulle part
# ailleurs que dans le fichier htpasswd (haché) — il s'affiche UNE SEULE FOIS
# ci-dessous, à noter dans ton gestionnaire de mots de passe.

set -euo pipefail

USER_NAME="${1:-admin}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SECRET_FILE="$SCRIPT_DIR/../secrets/admin_basicauth"

if [[ -s "$SECRET_FILE" ]]; then
  echo "⚠  $SECRET_FILE existe déjà et n'est pas vide — rien de fait." >&2
  echo "   Le supprimer d'abord si tu veux régénérer le mot de passe." >&2
  exit 1
fi

# Mot de passe aléatoire : 24 hex (96 bits) via openssl si dispo, sinon
# /dev/urandom. Hex volontairement — pas de caractère à échapper dans un
# shell, un .htpasswd ou un gestionnaire de mots de passe.
if command -v openssl >/dev/null 2>&1; then
  PASSWORD="$(openssl rand -hex 12)"
else
  PASSWORD="$(od -An -tx1 -N12 /dev/urandom | tr -d ' \n')"
fi

mkdir -p "$(dirname "$SECRET_FILE")"

if command -v htpasswd >/dev/null 2>&1; then
  htpasswd -nbB "$USER_NAME" "$PASSWORD" > "$SECRET_FILE"
elif command -v openssl >/dev/null 2>&1; then
  # openssl passwd -apr1 (MD5-crypt Apache) — accepté par Traefik basicAuth,
  # moins solide que bcrypt : installer apache2-utils si possible.
  echo "ℹ  htpasswd absent, repli sur openssl (hash apr1). Pour du bcrypt :" >&2
  echo "   sudo apt install -y apache2-utils && relance ce script." >&2
  printf '%s:%s\n' "$USER_NAME" "$(openssl passwd -apr1 "$PASSWORD")" > "$SECRET_FILE"
else
  echo "✗ Ni htpasswd ni openssl disponibles — installe apache2-utils." >&2
  exit 1
fi

chmod 600 "$SECRET_FILE"

echo
echo "✓ $SECRET_FILE généré."
echo
echo "  Utilisateur : $USER_NAME"
echo "  Mot de passe : $PASSWORD"
echo
echo "  → À NOTER MAINTENANT (gestionnaire de mots de passe). Il ne sera pas réaffiché."
echo "  → Il te sera demandé par le navigateur en arrivant sur https://db.horlynat.com"
echo "    (avant Cloudflare Access selon l'ordre, puis l'écran de connexion MySQL d'Adminer)."
