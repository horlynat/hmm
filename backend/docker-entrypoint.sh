#!/bin/sh
# Compile chaque secret Docker monté dans /run/secrets/<nom> vers .env.local
# (format Dotenv standard, PAS .env.local.php : ce dernier remplace toute la
# cascade Dotenv au lieu de s'y superposer — testé, ça faisait disparaître
# les valeurs par défaut utiles du .env committé comme MESSENGER_TRANSPORT_DSN).
# Symfony charge .env puis .env.local par-dessus à chaque boot (web ET
# bin/console) ; les vrais env vars système restent prioritaires sur les
# deux. Aucun secret n'atterrit dans .env ni dans l'image.
#
# Un fichier (pas un simple `export` shell) est nécessaire : `docker compose
# exec backend php bin/console ...` (migrations, app:seed-content, cf.
# infra/README.md) démarre un tout NOUVEAU process qui n'hérite pas des
# variables exportées par ce script pour SON process à lui — testé, ça
# cassait les migrations en pratique. Un fichier survit à cette limite,
# quel que soit le process qui boot Symfony ensuite.
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
    php -r '
        $out = "";
        foreach (glob("/run/secrets/*") as $file) {
            if (!is_file($file)) continue;
            $name = strtoupper(basename($file));
            // Double quotes Dotenv : supporte les valeurs multi-lignes
            // telles quelles (clés JWT au format PEM).
            $escaped = str_replace(["\\", "\""], ["\\\\", "\\\""], file_get_contents($file));
            $out .= $name . "=\"" . $escaped . "\"\n";
        }
        file_put_contents("/app/.env.local", $out);
    '
    chown www-data:www-data /app/.env.local
fi

exec su-exec www-data "$@"
