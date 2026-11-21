#!/bin/sh
# HEALTHCHECK du conteneur backend : interroge une route Symfony servie par
# le worker PHP (/health/live), pas l'API admin Caddy — celle-ci répond dès
# que Caddy écoute, même si PHP n'est pas prêt (cache froid, worker planté).
#
# Le port d'écoute suit SERVER_NAME (même variable que le Caddyfile) :
# ":80" (défaut de l'image), "http://:8080", ":18093", "hote:80"…
# Seul le premier site est pris en compte ; les sites https:// ne sont pas
# gérés (TLS terminé par Traefik en amont, cf. Caddyfile).
set -eu

addr="${SERVER_NAME:-:80}"
addr="${addr%%[ ,]*}"
addr="${addr#http://}"
case "$addr" in
    *:*) host="${addr%:*}" port="${addr##*:}" ;;
    *) host="$addr" port=80 ;;
esac
case "$port" in
    '' | *[!0-9]*) port=80 ;;
esac

if [ -n "$host" ]; then
    exec wget -q -T 4 -O /dev/null --header "Host: ${host}" "http://127.0.0.1:${port}/health/live"
fi
exec wget -q -T 4 -O /dev/null "http://127.0.0.1:${port}/health/live"
