#!/bin/sh
# Convertit chaque secret Docker monté dans /run/secrets/<nom> en variable
# d'environnement <NOM> (majuscules) avant de lancer la commande du conteneur.
# Symfony lit ensuite ces variables normalement (getenv/$_SERVER), sans
# connaître Docker — aucun secret n'atterrit dans .env ni dans l'image.
set -eu

if [ -d /run/secrets ]; then
    for secret_file in /run/secrets/*; do
        [ -f "$secret_file" ] || continue
        name=$(basename "$secret_file" | tr '[:lower:]' '[:upper:]')
        value=$(cat "$secret_file")
        export "$name=$value"
    done
fi

exec "$@"
