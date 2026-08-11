#!/usr/bin/env bash
#
# Comble le piège classique UFW + Docker : Docker écrit ses propres règles
# iptables (DNAT vers les ports publiés) dans la chaîne FORWARD via
# DOCKER-USER, un chemin qu'UFW ne filtre PAS par défaut. Résultat sans ce
# script : même avec `ufw allow 443 from <cloudflare>` uniquement, n'importe
# quelle IP peut quand même atteindre un port publié par un conteneur.
#
# Ce script réapplique dans DOCKER-USER la même restriction que celle posée
# dans UFW (80/443 -> IP Cloudflare uniquement, cf. cloudflare-ips.txt),
# idempotent (flush + re-création à chaque exécution). Appelé au boot par
# le service systemd docker-user-rules.service (cf. 02-docker-install.sh).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CF_IPS_FILE="$SCRIPT_DIR/../cloudflare-ips.txt"

for cmd in iptables ip6tables; do
  $cmd -N DOCKER-USER 2>/dev/null || true
  $cmd -F DOCKER-USER

  # Connexions déjà établies : toujours autorisées.
  $cmd -A DOCKER-USER -m state --state RELATED,ESTABLISHED -j RETURN
  $cmd -A DOCKER-USER -i lo -j RETURN
done

# Réseaux privés IPv4 (dont les réseaux Docker internes edge-net/data-net).
iptables -A DOCKER-USER -s 10.0.0.0/8 -j RETURN
iptables -A DOCKER-USER -s 172.16.0.0/12 -j RETURN
iptables -A DOCKER-USER -s 192.168.0.0/16 -j RETURN

# Plages Cloudflare — seul chemin public légitime vers Traefik (80/443).
# IPv4 -> iptables, IPv6 (contient ':') -> ip6tables.
if [[ -f "$CF_IPS_FILE" ]]; then
  while IFS= read -r range; do
    [[ -z "$range" || "$range" == \#* ]] && continue
    if [[ "$range" == *:* ]]; then
      ip6tables -A DOCKER-USER -s "$range" -j RETURN
    else
      iptables -A DOCKER-USER -s "$range" -j RETURN
    fi
  done < "$CF_IPS_FILE"
fi

# Tout le reste : rejeté avant même d'atteindre le conteneur.
iptables -A DOCKER-USER -j DROP
ip6tables -A DOCKER-USER -j DROP

echo "DOCKER-USER (v4) : $(iptables -L DOCKER-USER | wc -l) règles."
echo "DOCKER-USER (v6) : $(ip6tables -L DOCKER-USER | wc -l) règles."
