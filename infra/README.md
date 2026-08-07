# Déploiement — horlynat.com (VPS conteneurisé)

Runbook d'exécution pour ce dossier `infra/`. Suppose un clone de la branche
**`main`** sur le VPS, avec la structure :

```
/opt/hmm/
├── backend/    # Dockerfile, code Symfony (issu de la branche backend, mergée)
├── frontend/   # Dockerfile, code Next.js (issu de la branche frontend, mergée)
└── infra/      # ce dossier
```

Adapter `DEPLOY_PATH` dans les scripts si le clone n'est pas dans `/opt/hmm`.

Pas d'accès SSH depuis l'environnement où ce runbook a été écrit — tout ce
qui suit est à exécuter par toi sur le VPS.

## Ordre d'exécution

### 1. Durcissement OS (`scripts/01-base-hardening.sh`)

```bash
sudo ADMIN_PUBKEY="ssh-ed25519 AAAA... toi@laptop" ./scripts/01-base-hardening.sh
# Depuis un AUTRE terminal, teste :
ssh -p 2222 deploy@<IP_VPS>
# Si ça marche :
sudo ./scripts/01-base-hardening.sh --lock-ssh
```

⚠️ Ne saute pas l'étape de test intermédiaire — `--lock-ssh` désactive le mot
de passe root et le port 22. Si le test échoue, tu perds l'accès au serveur.

### 2. Docker (`scripts/02-docker-install.sh`)

```bash
sudo ./scripts/02-docker-install.sh
# Se reconnecter en SSH pour que l'appartenance au groupe docker prenne effet
```

### 3. Bascule Cloudflare

Suivre `_procedure_cloudflare.md` (déjà versionné côté `frontend/`, phases 0
à 6) — nameservers, DNS proxied, certificat Origin. Copier le certificat
généré sur le VPS :

```bash
mkdir -p /opt/hmm/infra/certs
# copier cloudflare-origin.pem et cloudflare-origin.key ici, chmod 600 sur la clé
chmod 600 /opt/hmm/infra/certs/cloudflare-origin.key
```

### 4. Secrets

Créer un fichier par secret dans `infra/secrets/` (contenu brut, **sans**
retour à la ligne final ni commentaire — chaque fichier devient tel quel
une variable d'env via `docker-entrypoint.sh`) :

| Fichier                    | Contenu                                             |
|-----------------------------|------------------------------------------------------|
| `secrets/app_secret`        | `APP_SECRET` Symfony (32 hex, `openssl rand -hex 16`) |
| `secrets/jwt_passphrase`    | Passphrase de la clé JWT                              |
| `secrets/jwt_secret_key`    | Contenu de la clé privée JWT (lexik/jwt-bundle)       |
| `secrets/jwt_public_key`    | Contenu de la clé publique JWT                        |
| `secrets/database_url`      | DSN complet, ex. `mysql://app:<motdepasse>@database:3306/app?serverVersion=8.0.32&charset=utf8mb4` |
| `secrets/mailer_dsn`        | DSN SMTP vers Postfix natif de l'hôte, ex. `smtp://user:pass@host.docker.internal:587` |
| `secrets/llm_api_key`       | Clé API Anthropic (assistant IA) — isolée, pas partagée avec d'autres usages |
| `secrets/revalidate_secret` | Secret partagé webhook `/api/revalidate` (frontend)   |
| `secrets/ntfy_dsn`          | DSN NTFY si utilisé                                   |
| `secrets/database_password` | Mot de passe MySQL de l'utilisateur `app` (doit matcher celui dans `database_url`) |

```bash
chmod 600 infra/secrets/*
```

🛑 **Base de données — bloquant, confirmé** : les migrations existantes
(`migrations/Version*.php`) sont écrites en dialecte **MySQL**
(`AUTO_INCREMENT`, testé : `doctrine:migrations:migrate` échoue avec
`SQLSTATE[42601]: syntax error at or near "AUTO_INCREMENT"` sur Postgres 16).
`doctrine.yaml` a pourtant un réglage `identity_generation_preferences`
spécifique à `PostgreSQLPlatform`, et le `.env` de dev garde une ligne
Postgres commentée — signe que Postgres était probablement visé à un moment,
mais les migrations committées n'ont jamais été régénérées pour ce moteur.

`docker-compose.prod.yml` utilise **MySQL 8** (pas Postgres) pour matcher les
migrations telles qu'elles existent réellement aujourd'hui — c'est le choix
qui ne demande aucune modification du code applicatif. Basculer sur Postgres
resterait possible mais demande de régénérer toutes les migrations depuis
l'état actuel des entités (`doctrine:migrations:diff` sur une base Postgres
vierge), une décision côté code applicatif, pas infra — à trancher toi-même
si tu préfères Postgres.

### 5. Config non sensible

```bash
cp .env.prod.example .env.prod
# éditer .env.prod
```

Éditer `traefik/dynamic.yml` : remplacer `203.0.113.10/32` (middleware
`admin-ipwhitelist`) par ta/tes vraie(s) IP fixe(s) — **tant que ce n'est
pas fait, `dark.horlynat.com` reste inaccessible** (fail-closed voulu).

### 6. Build en trois temps (important)

Le frontend fait des appels serveur vers l'API **pendant** `next build`
(génération statique/ISR de la plupart des pages — layouts inclus). Deux
pages (accueil, "À propos") vont plus loin : leur contenu vient d'une ligne
unique en base (`App\Entity\HomeContent`/`AboutContent`, pilotée depuis le
back-office) et **l'absence de cette ligne fait volontairement échouer leur
génération statique** (cf. commentaires dans ces pages — un choix
assumé : signaler une vraie panne plutôt que publier une page vide). Il faut
donc, dans cet ordre :

1. **Backend + DB**, migrations, puis seed du contenu réel.
2. **Frontend** seulement après.

```bash
cd /opt/hmm/infra

# 1. Backend + DB + Traefik d'abord — SANS messenger-worker (le frontend
#    n'est pas encore construit, inutile de l'inclure ici). Messenger tourne
#    en doctrine://default avec auto_setup : s'il démarre avant les
#    migrations, il auto-crée sa propre table messenger_messages, qui entre
#    ensuite en collision avec la migration censée la créer (testé en prod :
#    "Table 'messenger_messages' already exists"). Toujours migrations
#    d'abord, worker après (étape 2.5).
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build backend database traefik
docker compose -f docker-compose.prod.yml ps backend   # attendre "healthy"

# 2. Migrations — IMPORTANT : `docker compose exec` tourne en root par défaut
#    (l'entrypoint su-exec vers www-data ne s'applique qu'au process PID 1,
#    pas à exec — testé). Sans -u www-data, Symfony régénère son cache prod
#    (property_metadata, isInitializable...) appartenant à root, et le vrai
#    process web (www-data) se retrouve avec des "Permission denied" dessus
#    au premier vrai appel API. Toujours -u www-data sur ces commandes ; si
#    jamais oublié : `docker exec <container> chown -R www-data:www-data
#    /app/var/cache` répare après coup.
docker compose -f docker-compose.prod.yml exec -u www-data backend php bin/console doctrine:migrations:migrate --no-interaction

# 2.5. Worker Messenger, seulement maintenant que les migrations ont créé
#      messenger_messages proprement.
docker compose -f docker-compose.prod.yml up -d messenger-worker

# 3. Contenu obligatoire : `app:seed-content` (src/Command/SeedContentCommand.php)
#    écrit directement le contenu réel des pages Accueil et À propos en base.
#    Sans ça, /home_contents et /about_contents renvoient une collection vide
#    et l'étape suivante échoue à la génération statique de "/" et
#    "/a-propos" (comportement voulu, pas un bug — cf. commentaires dans ces
#    pages). Idempotente : ignore les entrées déjà personnalisées sauf
#    --force. Le contenu reste ensuite modifiable via le back-office
#    (dark.horlynat.com/admin/content/home et /about) comme n'importe quel
#    contenu.
docker compose -f docker-compose.prod.yml exec -u www-data backend php bin/console app:seed-content

# 4. Frontend ensuite — son build a besoin de `backend` joignable en HTTP le
#    temps du `RUN npm run build` (génération statique/ISR). BuildKit ne
#    supporte pas nommer un réseau bridge personnalisé pour un RUN ; le
#    Dockerfile utilise donc `RUN --network=host`, et le service `backend`
#    publie son port sur `127.0.0.1:8000` (loopback uniquement — jamais
#    exposé publiquement, cf. commentaire dans docker-compose.prod.yml) pour
#    que ce RUN puisse l'atteindre via l'hôte. `docker compose build` gère
#    l'entitlement `network.host` tout seul, rien à ajouter en CLI.
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build frontend
docker compose -f docker-compose.prod.yml ps
```

Aux déploiements suivants (mise à jour de code), même ordre à respecter —
sauf le contenu du back-office, qui persiste déjà en base.

### 7. Vérifications

```bash
curl -I https://horlynat.com
curl -I https://api.horlynat.com
curl -I https://dark.horlynat.com   # 403 attendu si ton IP n'est pas encore whitelistée
```

Confirmer que les logs backend affichent l'IP réelle du visiteur (pas une IP
Cloudflare) — sinon revoir `SYMFONY_TRUSTED_PROXIES` et
`entryPoints.websecure.forwardedHeaders.trustedIPs` dans `traefik/traefik.yml`.

### 8. Sauvegardes

```bash
age-keygen -o /root/.age/backup-key.txt   # noter la clé publique affichée
chmod 600 /root/.age/backup-key.txt
```

Ajouter au crontab root :
```
0 3 * * * DEPLOY_PATH=/opt/hmm AGE_RECIPIENT=age1... /opt/hmm/infra/scripts/backup.sh
```

Tester une restauration au moins une fois (`scripts/backup.sh --restore <fichier>`)
avant d'en dépendre.

### 9. Monitoring (léger, cohérent avec un VPS solo)

- **Netdata** : `curl https://get.netdata.cloud/kickstart.sh | sh` sur l'hôte
  (hors Docker — surveille aussi l'hôte lui-même, pas seulement les
  conteneurs). Pas scripté ici (interactif, à valider toi-même).
- **UptimeRobot / Better Stack** : moniteur externe HTTPS sur les 3 domaines,
  à créer manuellement (compte tiers, hors scope infra).

## Hors périmètre de cette livraison (documenté, pas implémenté)

- **WireGuard devant `dark.horlynat.com`** : amélioration future la plus
  impactante selon le guide. Pour l'instant : whitelist IP Traefik +
  Cloudflare Access. À revisiter si tu changes souvent de lieu de connexion
  (la whitelist IP devient alors pénible).
- **Filtrage de sortie anti-prompt-injection de l'assistant IA** et
  **anonymisation RGPD des logs de conversation** : changements applicatifs
  (code Symfony), pas de l'infra. La clé LLM est isolée (secret dédié,
  jamais partagée), mais le filtrage de contenu reste à coder.
- **`config/packages/security.yaml`** : la règle globale
  `{ path: ^/api, roles: IS_AUTHENTICATED_FULLY }` déjà repérée dans
  `_config.frontend.md` bloque toujours tous les appels publics vers `/api`.
  Non corrigée ici (hors scope infra) — sans ce correctif, `api.horlynat.com`
  répondra 401 partout une fois déployé.
- **Wazuh/SIEM, Prometheus+Grafana** : mentionnés par le guide pour des
  besoins plus lourds qu'un VPS solo — AIDE/rkhunter/auditd + Netdata
  suffisent ici. Note laissée si le besoin grandit.
- **Channel Monolog dédié `security_errors` → fichier** : la jail fail2ban
  `symfony-security` (dans `01-base-hardening.sh`) lit `var/log/prod.log`
  en attendant ce channel dédié (déjà prévu dans `_config.backend.md`), qui
  donnerait un signal plus propre pour le ban.

## Checklist finale (guide hardening serveur §14)

- [ ] Root désactivé, sudo `deploy` fonctionnel testé
- [ ] SSH : clé uniquement, port modifié, root login off
- [ ] UFW actif, deny by default, restreint aux IP Cloudflare sur 80/443
- [ ] DOCKER-USER restreint (même règle, au niveau Docker — cf. §3 ci-dessus)
- [ ] Fail2ban actif sur SSH et `dark.horlynat.com`
- [ ] Mises à jour automatiques configurées
- [ ] sysctl hardening appliqué
- [ ] AppArmor actif
- [ ] AIDE/rkhunter installés et planifiés
- [ ] auditd actif avec règles critiques
- [ ] TLS vérifié (Cloudflare Full Strict + cert Origin sur Traefik)
- [ ] Sauvegardes automatiques chiffrées + restauration testée
- [ ] Monitoring (Netdata + Uptime externe) en place
- [ ] `admin-ipwhitelist` éditée avec la vraie IP avant d'utiliser `dark.horlynat.com`
- [ ] `security.yaml` `/api` corrigé côté backend (sinon l'API reste 401 partout)
