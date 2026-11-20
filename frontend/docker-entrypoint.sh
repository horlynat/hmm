#!/bin/sh
# Convertit chaque secret Docker monté dans /run/secrets/<nom> en variable
# d'environnement <NOM> (majuscules) avant de lancer la commande du conteneur.
# Next.js lit ensuite ces variables normalement (process.env), sans
# connaître Docker — aucun secret n'atterrit dans l'image.
#
# L'image tourne désormais en utilisateur NON-root (USER nextjs, uid 1001,
# cf. Dockerfile). `docker compose` (hors Swarm) ignore silencieusement
# `mode`/`uid`/`gid` sur les secrets : les fichiers de /run/secrets gardent
# les permissions du fichier hôte. Un secret illisible par l'uid 1001 n'est
# donc PAS chargé : on le signale bruyamment dans les logs sans bloquer le
# démarrage (le site reste servi ; la fonctionnalité qui en dépend — ex.
# REVALIDATE_SECRET pour /api/revalidate — échoue fermée d'elle-même).
# Correctif côté hôte : chgrp 1001 <fichier> && chmod 0440 <fichier>.
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
            echo "[entrypoint] AVERTISSEMENT : secret '$name' illisible par l'uid $(id -u) — non chargé (sur l'hôte : chgrp 1001 + chmod 0440 sur le fichier)." >&2
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
