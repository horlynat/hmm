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
    # root:www-data 0640 : lisible par l'application, jamais modifiable par
    # elle (le code applicatif est root:root en lecture seule, cf. Dockerfile).
    chown root:www-data /app/.env.local
    chmod 0640 /app/.env.local
fi

# Préchauffage du cache Symfony AVANT de servir (en www-data, propriétaire de
# var/) : sans lui, la première requête d'un conteneur neuf compilait le
# conteneur de services, les templates, les proxies Doctrine… dans le worker
# PHP et finissait en 500 après ~30 s (max_execution_time), alors que le
# healthcheck — qui interrogeait l'API admin Caddy — annonçait déjà le
# conteneur sain. Fait ici et non au build : il doit voir l'environnement
# réel (secrets ci-dessus, variables compose). Un échec fait échouer le
# démarrage (redémarrage par Docker) plutôt que de servir des 500.
# SKIP_CACHE_WARMUP=1 pour une commande ponctuelle qui n'en a pas besoin.
if [ "${SKIP_CACHE_WARMUP:-0}" != "1" ]; then
    su-exec www-data php /app/bin/console cache:warmup --no-interaction
fi

exec su-exec www-data "$@"
