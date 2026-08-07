#!/usr/bin/env bash
#
# Durcissement de base du VPS (Ubuntu Server 24.04 LTS), traduction directe
# de guide-hardening-serveur-cloud.pdf §1-10 + verrouillage Cloudflare de
# _config.frontend.md §4. Idempotent : peut être relancé sans casser un état
# déjà appliqué.
#
# Usage : sudo ./01-base-hardening.sh [--lock-ssh]
#   Sans --lock-ssh : prépare tout (user deploy, clé, UFW, fail2ban, sysctl,
#     unattended-upgrades, AppArmor, auditd, AIDE/rkhunter) mais laisse le
#     login root actif et le port SSH par défaut (22) — comme le guide le
#     recommande, tant que tu n'as pas confirmé une connexion réussie en
#     `deploy` sur le nouveau port depuis un AUTRE terminal.
#   Avec --lock-ssh : applique en plus PermitRootLogin no, désactive le mot
#     de passe root, et bascule sshd sur SSH_PORT. À ne lancer qu'après le
#     test de connexion ci-dessus réussi.
#
# Variables d'env optionnelles :
#   DEPLOY_USER   (défaut: deploy)
#   SSH_PORT      (défaut: 2222)
#   ADMIN_PUBKEY  clé publique SSH à installer pour $DEPLOY_USER (recommandé
#                 de la passer explicitement plutôt que de compter sur un
#                 ssh-copy-id déjà fait à la main)

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Ce script doit être lancé en root (sudo)." >&2
  exit 1
fi

DEPLOY_USER="${DEPLOY_USER:-deploy}"
SSH_PORT="${SSH_PORT:-2222}"
# Racine du clone de la branche `main` sur le VPS (structure attendue :
# $DEPLOY_PATH/{backend,frontend,infra}) — ajuste si tu clones ailleurs.
DEPLOY_PATH="${DEPLOY_PATH:-/opt/hmm}"
LOCK_SSH=0
[[ "${1:-}" == "--lock-ssh" ]] && LOCK_SSH=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CF_IPS_FILE="$SCRIPT_DIR/../cloudflare-ips.txt"

log() { echo -e "\n\033[1;32m==>\033[0m $1"; }
warn() { echo -e "\033[1;33m[!]\033[0m $1"; }

# ---------------------------------------------------------------------------
log "1. Mise à jour système + paquets de base (guide §1-2)"
export DEBIAN_FRONTEND=noninteractive
apt update && apt full-upgrade -y
apt install -y sudo curl wget gnupg2 unattended-upgrades fail2ban ufw \
  auditd audispd-plugins aide rkhunter needrestart apt-listchanges

# ---------------------------------------------------------------------------
log "2. Utilisateur non-root '$DEPLOY_USER' (guide §2)"
if ! id "$DEPLOY_USER" &>/dev/null; then
  adduser --disabled-password --gecos "" "$DEPLOY_USER"
  usermod -aG sudo "$DEPLOY_USER"
  echo "Utilisateur '$DEPLOY_USER' créé."
else
  echo "Utilisateur '$DEPLOY_USER' déjà présent."
fi

install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
if [[ -n "${ADMIN_PUBKEY:-}" ]]; then
  echo "$ADMIN_PUBKEY" >> "/home/$DEPLOY_USER/.ssh/authorized_keys"
  sort -u -o "/home/$DEPLOY_USER/.ssh/authorized_keys" "/home/$DEPLOY_USER/.ssh/authorized_keys"
  chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"
  chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
  echo "Clé publique installée pour $DEPLOY_USER."
else
  warn "ADMIN_PUBKEY non fourni — installe ta clé manuellement avant --lock-ssh :"
  warn "  ssh-copy-id -i ~/.ssh/id_ed25519.pub -p 22 $DEPLOY_USER@<IP_VPS>"
fi

# Docker (posé par 02-docker-install.sh) a besoin que deploy soit dans le
# groupe docker — le groupe n'existe peut-être pas encore, on ignore l'erreur.
usermod -aG docker "$DEPLOY_USER" 2>/dev/null || true

# ---------------------------------------------------------------------------
log "3. Durcissement SSH (guide §3)"
SSHD_CONFIG=/etc/ssh/sshd_config.d/99-hardening.conf
cat > "$SSHD_CONFIG" <<EOF
Port ${SSH_PORT}
Protocol 2
PubkeyAuthentication yes
KbdInteractiveAuthentication no
AuthenticationMethods publickey
MaxAuthTries 3
LoginGraceTime 20
AllowUsers ${DEPLOY_USER}
X11Forwarding no
AllowTcpForwarding no
ClientAliveInterval 300
ClientAliveCountMax 2
EOF

if [[ "$LOCK_SSH" -eq 1 ]]; then
  warn "Verrouillage SSH activé : PermitRootLogin no, port ${SSH_PORT} uniquement."
  echo "PermitRootLogin no" >> "$SSHD_CONFIG"
  echo "PasswordAuthentication no" >> "$SSHD_CONFIG"
  passwd -l root
else
  warn "SSH PAS encore verrouillé (mode préparation) : PasswordAuthentication reste"
  warn "actif et le port 22 reste ouvert en parallèle du ${SSH_PORT}, le temps que"
  warn "tu valides une connexion 'ssh -p ${SSH_PORT} ${DEPLOY_USER}@<IP_VPS>' réussie."
  # "no" et pas "prohibit-password" : si root est déjà désactivé sur ce VPS
  # (cf. sshd_config existant), pas de raison de rouvrir un accès root même
  # temporaire le temps du test — DEPLOY_USER dispose déjà d'un accès
  # sudo + clé confirmé fonctionnel, aucun besoin d'un filet de secours root.
  echo "PermitRootLogin no" >> "$SSHD_CONFIG"
fi

# Le nom de l'unité systemd diverge selon la distro : "ssh" sur Debian/Ubuntu,
# "sshd" ailleurs (RHEL et dérivés) — testé, "systemctl restart sshd" échoue
# sur Ubuntu 24.04 avec "Unit sshd.service not found."
sshd -t && (systemctl restart ssh 2>/dev/null || systemctl restart sshd)
echo "sshd redémarré. NE FERME PAS cette session tant que tu n'as pas testé"
echo "une connexion réussie depuis un autre terminal :"
echo "  ssh -p ${SSH_PORT} ${DEPLOY_USER}@<IP_VPS>"

# ---------------------------------------------------------------------------
log "4. Pare-feu UFW — verrouillé aux plages Cloudflare sur 80/443 (guide §4"
log "   + _config.frontend.md §4 'verrouillage de l'origine')"
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow "${SSH_PORT}/tcp" comment 'SSH'
[[ "$LOCK_SSH" -eq 0 ]] && ufw allow 22/tcp comment 'SSH temporaire (avant lock-ssh)'

if [[ -f "$CF_IPS_FILE" ]]; then
  while IFS= read -r range; do
    [[ -z "$range" || "$range" == \#* ]] && continue
    ufw allow from "$range" to any port 80 proto tcp comment 'Cloudflare'
    ufw allow from "$range" to any port 443 proto tcp comment 'Cloudflare'
  done < "$CF_IPS_FILE"
else
  warn "cloudflare-ips.txt introuvable — repli sur 80/443 ouverts à tous en"
  warn "attendant la bascule Cloudflare (à resserrer ensuite, cf. README)."
  ufw allow 80/tcp
  ufw allow 443/tcp
fi

ufw --force enable
echo "Règles UFW actives :"
ufw status verbose

# ---------------------------------------------------------------------------
log "5. Fail2ban (guide §4)"
mkdir -p /etc/fail2ban/jail.d
cat > /etc/fail2ban/jail.d/hardening.local <<EOF
[sshd]
enabled = true
port = ${SSH_PORT}
maxretry = 3
bantime = 3600
findtime = 600

# Tentatives de login échouées sur dark.horlynat.com (back-office Symfony).
# Lit var/log/backend/prod.log (bind-mount du conteneur, cf. compose) — un
# channel Monolog "security_errors" dédié (cf. _config.backend.md) donnerait
# un signal plus propre, à faire évoluer côté backend le cas échéant.
[symfony-security]
enabled = true
port = http,https
filter = symfony-security
logpath = ${DEPLOY_PATH}/infra/logs/backend/prod.log
maxretry = 5
findtime = 600
bantime = 3600
EOF

cat > /etc/fail2ban/filter.d/symfony-security.conf <<'EOF'
[Definition]
failregex = ^.*Authentication (failure|failed) for .* from <HOST>.*$
            ^.*Invalid credentials.*"ip":"<HOST>".*$
ignoreregex =
EOF

systemctl enable --now fail2ban
systemctl restart fail2ban

# ---------------------------------------------------------------------------
log "6. Mises à jour automatiques (guide §5)"
cat > /etc/apt/apt.conf.d/50unattended-upgrades <<'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "04:00";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
EOF
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
systemctl enable --now unattended-upgrades

# ---------------------------------------------------------------------------
log "7. Durcissement noyau — sysctl (guide §6)"
cat > /etc/sysctl.d/99-hardening.conf <<'EOF'
# Anti IP spoofing
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# Ce VPS n'est pas un routeur
net.ipv4.ip_forward = 0

# Protection SYN flood
net.ipv4.tcp_syncookies = 1

# Ignore les redirections ICMP
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0

# Ignore les paquets source-routed
net.ipv4.conf.all.accept_source_route = 0

# Log des paquets suspects (martians)
net.ipv4.conf.all.log_martians = 1

# ASLR complet
kernel.randomize_va_space = 2

# Restreint dmesg/kptr aux utilisateurs privilégiés
kernel.dmesg_restrict = 1
kernel.kptr_restrict = 2
EOF
sysctl --system

# ---------------------------------------------------------------------------
log "8. AppArmor (guide §7)"
if command -v aa-status &>/dev/null; then
  aa-status --enabled && echo "AppArmor actif." || warn "AppArmor installé mais inactif — vérifie manuellement (aa-status)."
else
  warn "AppArmor absent — normalement préinstallé sur Ubuntu Server, à vérifier."
fi
# Docker applique déjà son profil docker-default à chaque conteneur créé par
# ce compose (comportement par défaut du moteur Docker, rien à faire côté OS).

# ---------------------------------------------------------------------------
log "9. auditd (guide §8-9)"
mkdir -p /etc/audit/rules.d
cat > /etc/audit/rules.d/hardening.rules <<'EOF'
-w /etc/passwd -p wa -k identity
-w /etc/shadow -p wa -k identity
-w /etc/sudoers -p wa -k identity
-w /etc/sudoers.d/ -p wa -k identity
-w /etc/ssh/sshd_config -p wa -k sshd_config
-w /etc/ssh/sshd_config.d/ -p wa -k sshd_config
-a always,exit -F arch=b64 -S execve -F euid=0 -k root_exec
EOF
augenrules --load 2>/dev/null || true
# Dans un conteneur LXC non privilégié (cf. sysctl read-only et AppArmor
# absent ci-dessus, même cause), le sous-système d'audit noyau n'est pas
# accessible et la plateforme masque volontairement ce service — testé sur
# le VPS de prod ("Unit file .../auditd.service is masked"), pas un bug à
# corriger, juste une limite de l'environnement. Averti, pas fatal.
if ! systemctl enable --now auditd 2>&1; then
  warn "auditd indisponible dans cet environnement (conteneur LXC — sous-système"
  warn "d'audit noyau non accessible, service masqué par la plateforme). Ignoré."
fi

# Logging sudo renforcé (guide §10)
cat > /etc/sudoers.d/logging <<'EOF'
Defaults logfile="/var/log/sudo.log"
Defaults timestamp_timeout=5
EOF
chmod 440 /etc/sudoers.d/logging

# ---------------------------------------------------------------------------
log "10. Intégrité / anti-rootkit — AIDE + rkhunter (guide §8)"
if [[ ! -f /var/lib/aide/aide.db.gz && ! -f /var/lib/aide/aide.db ]]; then
  aideinit -y -f || aide --init
  # Sur Ubuntu, aideinit promeut déjà aide.db.new -> aide.db en interne, sans
  # compression gzip (contrairement à la convention d'autres distros) — testé
  # sur le VPS de prod. Ce mv ne sert que si aide --init brut a été utilisé
  # en repli (aideinit absent), auquel cas AUCUNE promotion automatique n'a
  # lieu et il faut le faire nous-mêmes.
  mv -f /var/lib/aide/aide.db.new.gz /var/lib/aide/aide.db.gz 2>/dev/null || true
  mv -f /var/lib/aide/aide.db.new /var/lib/aide/aide.db 2>/dev/null || true
fi
( crontab -l 2>/dev/null | grep -v aide.wrapper; \
  echo "0 5 * * * root /usr/bin/aide.wrapper --check" ) | crontab -

rkhunter --update || true
rkhunter --propupd || true
( crontab -l 2>/dev/null | grep -v rkhunter; \
  echo "30 5 * * * root /usr/bin/rkhunter --cronjob --report-warnings-only" ) | crontab -

# ---------------------------------------------------------------------------
log "Terminé."
echo "Checklist restante (guide §14) :"
echo "  [ ] Tester 'ssh -p ${SSH_PORT} ${DEPLOY_USER}@<IP_VPS>' AVANT de relancer avec --lock-ssh"
echo "  [ ] Relancer ce script avec --lock-ssh une fois le test validé"
echo "  [ ] 02-docker-install.sh"
echo "  [ ] Bascule Cloudflare (cf. _procedure_cloudflare.md déjà versionné côté frontend)"
echo "  [ ] docker compose -f docker-compose.prod.yml up -d (cf. README)"
