# Procédure — Bascule Cloudflare (horlynat.com / api. / dark.)

Checklist d'exécution pour l'intégration Cloudflare décrite dans `_config.frontend.md`.
À suivre dans l'ordre : chaque phase dépend de la précédente. Coche au fur et à mesure.
`<IP_VPS>` = IP publique du serveur. `<EMAIL_ADMIN>` = ton email (accès Access/Zero Trust).

---

## Phase 0 — Inventaire avant de toucher à quoi que ce soit

- [ ] Noter les enregistrements DNS actuels (registrar ou DNS actuel) pour `horlynat.com`,
      `www`, `api`, `dark` — copier/coller le résultat quelque part en dehors du repo :
      ```bash
      dig +short horlynat.com
      dig +short www.horlynat.com
      dig +short api.horlynat.com
      dig +short dark.horlynat.com
      ```
- [ ] Noter le TTL actuel de ces enregistrements. S'il est élevé (> 1h), le baisser à 300s
      chez le registrar/DNS actuel **48h avant** la bascule, pour que le changement de
      nameservers propage vite le jour J.
- [ ] Vérifier que les certificats Let's Encrypt actuels fonctionnent et noter leur date
      d'expiration (`certbot certificates`) — on les garde en fallback, on ne les supprime
      pas tant que Cloudflare n'est pas validé.
- [ ] Vérifier l'accès SSH au VPS **par IP** (pas par nom de domaine) et confirmer que la
      clé/le mot de passe fonctionnent — indispensable avant de toucher au pare-feu plus tard.
- [ ] Confirmer qu'on a bien accès au compte chez le registrar du domaine (pour changer les
      nameservers en Phase 2).

## Phase 1 — Compte Cloudflare & préparation (zone encore "pending", sans risque)

- [ ] Créer/ouvrir le compte Cloudflare, ajouter le site `horlynat.com` (plan **Free**).
- [ ] Laisser Cloudflare scanner les enregistrements DNS existants, puis **vérifier
      manuellement** qu'il a bien détecté (ou ajouter à la main) :
      - `A @ -> <IP_VPS>`
      - `A www -> <IP_VPS>`
      - `A api -> <IP_VPS>`
      - `A dark -> <IP_VPS>`
      - tout enregistrement MX / TXT (SPF/DKIM) existant pour les emails — **ne pas les
        perdre**, sinon les emails du site (Symfony Mailer, notifications) risquent de
        tomber en spam ou de ne plus partir.
- [ ] Laisser les 4 enregistrements en **DNS only (nuage gris)** pour l'instant — on ne
      passe rien en proxied avant la Phase 3.
- [ ] Noter les deux nameservers fournis par Cloudflare (ex. `xxx.ns.cloudflare.com`).
- [ ] SSL/TLS → Overview : préparer le mode sur **Full (Strict)** mais ne pas valider tant
      que le certificat Origin (étape suivante) n'est pas installé sur le VPS.
- [ ] SSL/TLS → Origin Server → **Create Certificate** : générer un certificat couvrant
      `horlynat.com` + `*.horlynat.com`, clé privée + certificat. Copier les deux sur le VPS
      (ex. `/etc/ssl/cloudflare/origin.pem` et `/etc/ssl/cloudflare/origin.key`,
      `chmod 600` sur la clé).
- [ ] Préparer (sans encore activer) le bloc Nginx `real_ip` — voir Annexe A — dans un
      fichier séparé inclus par les 3 vhosts (`/etc/nginx/snippets/cloudflare-realip.conf`).
- [ ] Préparer (sans encore recharger Nginx) les 3 vhosts pour pointer sur le certificat
      Origin Cloudflare au lieu de Let's Encrypt, en gardant Let's Encrypt en commentaire
      pour rollback rapide.

## Phase 2 — Bascule des nameservers

- [ ] Chez le registrar, remplacer les nameservers actuels par ceux de Cloudflare.
- [ ] Attendre l'activation de la zone (email Cloudflare "Great news!" + statut "Active"
      dans le dashboard). Peut prendre de quelques minutes à 24h.
- [ ] Vérifier la propagation :
      ```bash
      dig NS horlynat.com +short
      ```
- [ ] **Ne rien faire d'autre tant que la zone n'est pas "Active"** — les réglages SSL/Access
      ne prennent pleinement effet qu'une fois la zone active.

## Phase 3 — Mise en proxy, un sous-domaine à la fois (le moins risqué d'abord)

Ordre volontaire : `horlynat.com` (public, faible enjeu) sert à valider toute la chaîne
technique (cert, real_ip, headers) avant de toucher aux sous-domaines sensibles.

### 3.1 — `horlynat.com` / `www`

- [ ] Installer le bloc `real_ip` (Annexe A) + le certificat Origin sur le vhost du
      frontend, recharger Nginx (`nginx -t && systemctl reload nginx`).
- [ ] Passer `A @` et `A www` en **Proxied (nuage orange)** dans Cloudflare.
- [ ] Tester :
      ```bash
      curl -I https://horlynat.com
      curl -I https://www.horlynat.com
      ```
      → doit répondre 200, header `server: cloudflare` présent, pas d'erreur 521/526
      (521/526 = Nginx ne parle pas au certificat Origin correctement, à corriger avant de
      continuer).
- [ ] Activer SSL/TLS **Full (Strict)** dans Cloudflare (maintenant que le cert Origin est
      en place partout où c'est proxied).
- [ ] Activer "Always Use HTTPS".
- [ ] Vérifier l'IP réelle : consulter les logs Nginx (`access_log`) et confirmer qu'ils
      affichent bien l'IP du visiteur (pas une IP Cloudflare `173.245.x.x` etc.).

### 3.2 — `api.horlynat.com`

- [ ] Même traitement (real_ip + cert Origin sur le vhost API), recharger Nginx.
- [ ] Passer `A api` en **Proxied**.
- [ ] Tester :
      ```bash
      curl -I https://api.horlynat.com/api
      ```
- [ ] Vérifier dans les logs/Doctrine que les appels API affichent la vraie IP (important
      si un jour du rate limiting applicatif ou du logging par IP est ajouté côté API).
- [ ] Ajouter la **Cache Rule "Bypass Cache"** sur `api.horlynat.com` (aucune réponse API ne
      doit être mise en cache à l'edge).

### 3.3 — `dark.horlynat.com` (espace VIP — le plus sensible)

⚠️ Ne passer ce sous-domaine en proxied qu'une fois l'Access policy (étape suivante) prête
à être activée dans la foulée, pour minimiser la fenêtre où il serait exposé sans la couche
Access.

- [ ] **Avant** de proxifier `dark` : créer l'application Cloudflare Access (voir Phase 4.3)
      et la laisser prête (mais elle ne s'applique que si le trafic passe par Cloudflare,
      donc pas encore active tant que `dark` est en DNS only).
- [ ] real_ip + cert Origin sur le vhost `dark`, recharger Nginx.
- [ ] Passer `A dark` en **Proxied**.
- [ ] **Immédiatement** vérifier que `https://dark.horlynat.com` demande bien le code
      Cloudflare Access avant d'afficher quoi que ce soit :
      ```bash
      curl -I https://dark.horlynat.com
      ```
      → doit rediriger vers la page de login Cloudflare Access, pas vers Symfony
      directement.
- [ ] Se connecter réellement (avec `<EMAIL_ADMIN>`) pour vérifier le flux complet :
      Cloudflare Access (code reçu par email) → puis page de login Symfony habituelle.
- [ ] Ajouter la **Cache Rule "Bypass Cache"** sur `dark.horlynat.com`.

## Phase 4 — Durcissement

### 4.1 — Verrouillage du pare-feu VPS (à faire en dernier, après validation complète de 3.1–3.3)

⚠️ Risque de se couper l'accès si fait trop tôt ou avec une mauvaise liste d'IP. Garder une
session SSH ouverte en parallèle pendant ce test, ne jamais fermer le terminal avant d'avoir
validé.

- [ ] Récupérer la liste d'IP Cloudflare à jour :
      ```bash
      curl -s https://www.cloudflare.com/ips-v4
      curl -s https://www.cloudflare.com/ips-v6
      ```
- [ ] Mettre en place le script de mise à jour automatique (Annexe B) + le cron associé,
      pour ne jamais avoir une liste d'IP obsolète qui bloquerait du trafic légitime.
- [ ] Appliquer les règles ufw (Annexe B) : autoriser 80/443 uniquement depuis ces plages,
      **garder SSH ouvert séparément** (règle par IP fixe ou clé, indépendante).
- [ ] Test immédiat, depuis un réseau différent (4G, pas le wifi habituel) :
      ```bash
      curl -I http://<IP_VPS>          # doit timeout / être refusé
      curl -I https://horlynat.com     # doit toujours répondre normalement
      ```
- [ ] Vérifier que la connexion SSH fonctionne toujours avant de fermer la session en cours.

### 4.2 — real_ip côté Symfony (vérification, pas de config supplémentaire a priori)

- [ ] Se connecter réellement sur `dark.horlynat.com`, consulter `LoginHistory` en base
      (`SELECT ip, user_agent FROM login_history ORDER BY id DESC LIMIT 1;`) et vérifier
      que l'IP enregistrée est la vraie IP du visiteur, pas une IP Cloudflare
      (`173.245.0.0/20`, `103.21.244.0/22`, etc. — si ça matche une de ces plages, le
      `real_ip` Nginx n'est pas appliqué correctement sur ce vhost).
- [ ] Si l'IP enregistrée est toujours celle de Cloudflare : vérifier que
      `framework.trusted_proxies` dans `backend/config/packages/framework.yaml` n'est pas
      nécessaire ici (le `real_ip` Nginx réécrit `$remote_addr` avant PHP-FPM, donc
      normalement rien à changer côté Symfony) ; sinon, en dernier recours, ajouter
      `trusted_proxies: '127.0.0.1,::1'` + `trusted_headers` pour que Symfony fasse
      confiance à `X-Forwarded-For`.

### 4.3 — Cloudflare Access sur `dark.horlynat.com`

- [ ] Zero Trust → Access → Applications → **Add an application** → Self-hosted.
- [ ] Domaine : `dark.horlynat.com`, session duration raisonnable (ex. 24h).
- [ ] Policy : `Allow` limité à l'email `<EMAIL_ADMIN>` (ajouter d'autres emails clients
      seulement si des clients doivent vraiment se connecter à cet espace).
- [ ] Méthode d'auth : One-time PIN par email (par défaut, suffisant) — SSO Google/GitHub en
      option si souhaité.
- [ ] Tester en navigation privée : accéder à `dark.horlynat.com` sans être connecté →
      doit demander le code Cloudflare avant toute chose.

### 4.4 — WAF / Bot Fight Mode / rate limiting

- [ ] Security → WAF → activer les **Managed Rules** par défaut (plan Free) sur la zone.
- [ ] Security → Bots → activer **Bot Fight Mode**.
- [ ] Security → WAF → Rate limiting rules : créer une règle sur les chemins de login
      (`dark.horlynat.com/login`, endpoint de login JWT sur `api.horlynat.com`) — ex. bloquer
      au-delà de 5 tentatives/minute par IP. Vérifier au moment de la config si le plan Free
      permet une règle custom ou s'il faut passer par les règles fournies par défaut (les
      quotas Cloudflare évoluent, à vérifier dans le dashboard).

## Phase 5 — Validation finale (à cocher avant de considérer la migration terminée)

- [ ] `https://horlynat.com`, `https://www.horlynat.com`, `https://api.horlynat.com`,
      `https://dark.horlynat.com` répondent tous en HTTPS valide (cadenas vert, pas
      d'avertissement de certificat).
- [ ] `http://` (sans s) redirige bien vers `https://` sur les 3 domaines.
- [ ] Accès direct à `<IP_VPS>` sur 80/443 refusé depuis l'extérieur.
- [ ] `dark.horlynat.com` demande Cloudflare Access avant Symfony.
- [ ] `LoginHistory` enregistre la vraie IP du visiteur (pas une IP Cloudflare).
- [ ] Les emails sortants (Symfony Mailer : confirmation de contact/devis, vérification
      email) partent toujours normalement (les enregistrements MX/SPF/DKIM n'ont pas été
      cassés par la bascule DNS).
- [ ] Le webhook de revalidation Next.js (`/api/revalidate`) fonctionne toujours depuis le
      backend (vérifier qu'il n'est pas bloqué par une règle WAF trop stricte).
- [ ] Cache Rules "Bypass" actives et vérifiées sur `api.` et `dark.` (pas de contenu
      dynamique servi en cache : tester deux requêtes successives et comparer le header
      `cf-cache-status`, qui doit être `BYPASS` ou `DYNAMIC`).

## Phase 6 — Plan de rollback

Si un problème bloquant apparaît à n'importe quelle phase :

- [ ] Repasser l'enregistrement DNS concerné en **DNS only (nuage gris)** dans Cloudflare —
      effet quasi immédiat, le trafic recontacte directement le VPS avec l'ancien certificat
      Let's Encrypt (toujours en place, jamais supprimé pendant la migration).
- [ ] Si la zone entière pose problème : rebasculer les nameservers vers l'ancien
      fournisseur DNS (noté en Phase 0) — propagation plus lente (jusqu'à 24-48h), donc à
      n'utiliser qu'en dernier recours.
- [ ] Si le pare-feu ufw bloque à tort : se reconnecter en SSH (toujours ouvert séparément)
      et faire `ufw reset` ou retirer les règles ajoutées en 4.1.

---

## Annexe A — Snippet Nginx `real_ip`

À placer dans `/etc/nginx/snippets/cloudflare-realip.conf`, `include` dans les 3 vhosts :

```nginx
# IPv4
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;
# IPv6
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;

real_ip_header CF-Connecting-IP;
```

Cette liste évolue rarement mais peut changer — préférer le script de l'Annexe B pour la
garder à jour automatiquement plutôt que de la recopier une fois pour toutes.

## Annexe B — Script de mise à jour automatique (real_ip + ufw)

`/usr/local/bin/update-cloudflare-ips.sh` :

```bash
#!/usr/bin/env bash
set -euo pipefail

NGINX_SNIPPET="/etc/nginx/snippets/cloudflare-realip.conf"
UFW_COMMENT="cloudflare-auto"

{
  echo "# Généré automatiquement — ne pas éditer à la main"
  for ip in $(curl -fsS https://www.cloudflare.com/ips-v4); do
    echo "set_real_ip_from ${ip};"
  done
  for ip in $(curl -fsS https://www.cloudflare.com/ips-v6); do
    echo "set_real_ip_from ${ip};"
  done
  echo "real_ip_header CF-Connecting-IP;"
} > "${NGINX_SNIPPET}.tmp"
mv "${NGINX_SNIPPET}.tmp" "${NGINX_SNIPPET}"

nginx -t && systemctl reload nginx

# Retire les anciennes règles ufw taguées, puis les recrée depuis la liste à jour
ufw status numbered | grep "${UFW_COMMENT}" | awk -F'[][]' '{print $2}' | sort -rn | \
  xargs -r -n1 ufw --force delete

for ip in $(curl -fsS https://www.cloudflare.com/ips-v4) $(curl -fsS https://www.cloudflare.com/ips-v6); do
  ufw allow from "${ip}" to any port 80,443 proto tcp comment "${UFW_COMMENT}"
done
```

```bash
chmod +x /usr/local/bin/update-cloudflare-ips.sh
crontab -e
# Ajouter : exécution hebdomadaire, dimanche 3h
0 3 * * 0 /usr/local/bin/update-cloudflare-ips.sh >> /var/log/cloudflare-ips.log 2>&1
```

À exécuter manuellement une première fois en Phase 4.1 avant de vérifier les accès, puis le
cron prend le relais.
