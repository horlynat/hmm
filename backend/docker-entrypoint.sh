#!/bin/sh
# Convertit chaque secret Docker monté dans /run/secrets/<nom> en variable
# d'environnement <NOM> (majuscules) avant de lancer la commande du conteneur.
# Symfony lit ensuite ces variables normalement (getenv/$_SERVER), sans
# connaître Docker — aucun secret n'atterrit dans .env ni dans l'image.
#
# Tourne en root (cf. Dockerfile : pas de USER avant l'ENTRYPOINT) car
# `docker compose` (hors Swarm) ignore silencieusement `mode`/`uid`/`gid` sur
# les secrets — ce sont des options Swarm uniquement (testé : le champ est
# accepté sans erreur mais n'a aucun effet). Les fichiers dans /run/secrets
# gardent donc les permissions du fichier hôte (souvent 600, propriétaire
# différent de www-data), illisibles autrement. Root lit ces fichiers sans
# contrainte de permission, puis `su-exec` bascule sur www-data avant
# d'exécuter la vraie commande — le process final ne tourne jamais en root.
set -eu

if [ -d /run/secrets ]; then
    for secret_file in /run/secrets/*; do
        [ -f "$secret_file" ] || continue
        name=$(basename "$secret_file" | tr '[:lower:]' '[:upper:]')
        value=$(cat "$secret_file")
        export "$name=$value"
    done
fi

exec su-exec www-data "$@"
