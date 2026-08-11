#!/usr/bin/env bash
#
# Installation Docker Engine (Ubuntu 24.04 LTS) + verrouillage du piège
# UFW/Docker (guide hardening portfolio §1, note "si Docker : ne jamais
# exposer le socket sur le réseau"). À lancer après 01-base-hardening.sh.

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Ce script doit être lancé en root (sudo)." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() { echo -e "\n\033[1;32m==>\033[0m $1"; }

log "1. Dépôt officiel Docker"
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

. /etc/os-release
cat > /etc/apt/sources.list.d/docker.list <<EOF
deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable
EOF

log "2. Installation Docker Engine + Compose plugin"
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

systemctl enable --now docker

log "3. Correctif UFW/Docker (DOCKER-USER)"
echo "Docker manipule iptables directement (chaîne DOCKER-USER) et peut"
echo "contourner les règles UFW pour les ports publiés par des conteneurs."
echo "Installation d'un service qui réapplique la restriction Cloudflare"
echo "dans DOCKER-USER à chaque démarrage (cf. docker-user-rules.sh)."

install -m 0755 "$SCRIPT_DIR/docker-user-rules.sh" /usr/local/sbin/docker-user-rules.sh
# cloudflare-ips.txt est référencé en chemin relatif ($SCRIPT_DIR/../) par
# docker-user-rules.sh — on le copie à côté pour que ça marche aussi une
# fois le script installé dans /usr/local/sbin (hors du repo).
install -m 0644 "$SCRIPT_DIR/../cloudflare-ips.txt" /usr/local/sbin/cloudflare-ips.txt
sed -i 's#\$SCRIPT_DIR/../cloudflare-ips.txt#/usr/local/sbin/cloudflare-ips.txt#' /usr/local/sbin/docker-user-rules.sh

cat > /etc/systemd/system/docker-user-rules.service <<'EOF'
[Unit]
Description=Restreint DOCKER-USER aux IP Cloudflare (voir docker-user-rules.sh)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/local/sbin/docker-user-rules.sh

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now docker-user-rules.service

log "4. Vérification"
docker --version
docker compose version
systemctl is-active docker-user-rules.service

echo
echo "Docker installé. Rappels avant 'docker compose up' :"
echo "  [ ] Le socket Docker n'est jamais exposé sur le réseau (par défaut, OK)"
echo "  [ ] deploy est dans le groupe docker (posé par 01-base-hardening.sh) —"
echo "      redémarrer la session SSH de deploy pour que ça prenne effet"
echo "  [ ] Scanner les images avant prod si tu veux aller plus loin : Trivy"
echo "      (guide hardening portfolio §2) — 'trivy image <image>', pas scripté"
echo "      ici (outil ponctuel, pas un service à installer)."
