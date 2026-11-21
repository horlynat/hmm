#!/bin/sh
# Convertit chaque secret Docker monté dans /run/secrets/<nom> en variable
# d'environnement <NOM> (majuscules) avant de lancer la commande du conteneur.
# Next.js lit ensuite ces variables normalement (process.env), sans
# connaître Docker — aucun secret n'atterrit dans l'image.
#
# L'image tourne désormais en utilisateur NON-root (USER nextjs, uid 1001,
# cf. Dockerfile). `docker compose` (hors Swarm) ignore silencieusement
# `mode`/`uid`/`gid` sur les secrets : les fichiers de /run/secrets gardent
# les permissions du fichier hôte (ubuntu:hmmsecrets 640 sur le VPS). La
# lecture repose sur `group_add: ["${HMM_SECRETS_GID:-5002}"]` posé sur le
# service `frontend` (docker-compose.prod.yml, lot infra) : nextjs obtient
# le groupe hôte `hmmsecrets` en groupe supplémentaire, aucun chgrp/chmod
# requis. Ce repli (avertissement, pas de crash) couvre malgré tout tout
# secret qui resterait illisible — groupe mal aligné, fichier monté avec
# d'autres droits : on le signale bruyamment dans les logs sans bloquer le
# démarrage (le site reste servi ; la fonctionnalité qui en dépend — ex.
# REVALIDATE_SECRET pour /api/revalidate — échoue fermée d'elle-même).
#
# Compatibilité : si le conteneur est lancé explicitement en root
# (`user: "0"`), le comportement historique est conservé — lecture en root
# puis bascule sur nextjs via su-exec, le process final ne tourne jamais en root.
set -eu

if [ -d /run/secrets ]; then
    for secret_file in /run/secrets/*; do
        [ -f "$secret_file" ] || continue
        name=$(basename "$secret_file" | tr '[:lower:]' '[:upper:]' | tr '-' '_')
        case "$name" in
            *[!A-Z0-9_]*|[0-9]*|'') echo "[entrypoint] nom de secret ignoré : $(basename "$secret_file")" >&2; continue ;;
        esac
        if [ ! -r "$secret_file" ]; then
            echo "[entrypoint] AVERTISSEMENT : secret '$name' illisible par l'uid $(id -u) (gid $(id -g) + groupes supplémentaires $(id -G)) — non chargé. Vérifier HMM_SECRETS_GID / le groupe propriétaire du fichier sur l'hôte." >&2
            continue
        fi
        value=$(cat "$secret_file")
        export "$name=$value"
    done
fi

if [ "$(id -u)" = "0" ]; then
    exec su-exec nextjs "$@"
fi

exec "$@"
