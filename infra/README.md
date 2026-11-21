# Déploiement — horlynat.com (VPS conteneurisé)

> Vue d'ensemble de l'architecture (tous composants, sécurité, continuité) :
> [`../ARCHITECTURE.md`](../ARCHITECTURE.md). Ce fichier-ci est le runbook
> d'exécution détaillé, pas une vue d'ensemble.

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

## Accès rapides (prod)

| Usage | URL | Identifiants |
|---|---|---|
| Site public | https://horlynat.com | — |
| API | https://api.horlynat.com | — |
| Back-office app (projets, contenu, utilisateurs...) | https://dark.horlynat.com | Cloudflare Access (code email) puis compte `ROLE_SUPER_ADMIN` — cf. §6.5 |
| Gestion des comptes mail | https://mailadmin.horlynat.com | Compte super-admin PostfixAdmin créé via `/setup.php` — cf. §10 |
| Base applicative (Adminer) | https://db.horlynat.com | Cloudflare Access (code email) + basic-auth origine (`secrets/admin_basicauth`) puis compte MySQL `app` (`secrets/database_password`) — cf. §10.5 |
| Webmail / client mail (IMAP 993, SMTP 587) | `vps122840.serveur-vps.net` (pas `mail.horlynat.com`, cf. §3) | Comptes créés dans PostfixAdmin |

Identifiants eux-mêmes non stockés ici (gestionnaire de mots de passe) —
seuls les points d'entrée sont listés pour s'y retrouver rapidement.

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

⚠️ `ADMIN_PUBKEY` accepte plusieurs clés (une par ligne, valeur multi-lignes
du shell) — sur une reconstruction complète (§3 de `backend/docs/incident-
auth.md`), passe-en au moins deux dès cette étape plutôt que d'ajouter la
deuxième après coup : une seule clé installée ici est un point de
défaillance unique tant que la reconstruction n'est pas terminée.

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

⚠️ **Ne jamais proxifier `mail.horlynat.com`** (le garder en DNS only / nuage
gris) si Postfix/Dovecot tournent nativement sur ce même VPS — Cloudflare ne
relaie pas le SMTP/IMAP sur ses IP proxy standards. Testé en prod : ce
sous-domaine s'est retrouvé proxié par erreur, coupant le mail entrant
externe le temps de s'en apercevoir. Seuls `horlynat.com`, `www`, `api` et
`dark` doivent être en proxied (nuage orange).

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
| `secrets/gemini_api_key`    | Clé API Google Gemini (assistant IA — ingestion RAG + embedding) — isolée, pas partagée avec d'autres usages |
| `secrets/anthropic_api_key` | Clé API Anthropic Claude (assistant IA — conversationnel) — isolée, pas partagée avec d'autres usages |
| `secrets/revalidate_secret` | Secret partagé webhook `/api/revalidate` (frontend)   |
| `secrets/ntfy_dsn`          | DSN NTFY si utilisé                                   |
| `secrets/database_password` | Mot de passe MySQL de l'utilisateur `app` (doit matcher celui dans `database_url`) |
| `secrets/postfixadmin_db_password` | Mot de passe MySQL de l'utilisateur `postfixadmin` (base dédiée, native sur l'hôte — cf. §10) |
| `secrets/postfixadmin_setup_password` | Hash bcrypt protégeant `/setup.php` de PostfixAdmin (cf. §10) |
| `secrets/admin_basicauth`   | Fichier htpasswd (bcrypt) protégeant Adminer à l'origine — généré par `./infra/scripts/gen-admin-basicauth.sh`. 2ᵉ barrière indépendante de Cloudflare Access, cf. §3 / §10.5. **Requis avant déploiement** (garde-fou secrets, §7). |
| `secrets/offsite_s3_endpoint` | URL du endpoint S3-compatible (ex. `https://<account_id>.r2.cloudflarestorage.com` pour Cloudflare R2) — **vide = copie hors-site désactivée**, voir ci-dessous |
| `secrets/offsite_s3_bucket` | Nom du bucket dédié à la copie hors-site (uploads ET sauvegardes DB, préfixes séparés) |
| `secrets/offsite_s3_access_key_id` | Access key ID du provider S3-compatible |
| `secrets/offsite_s3_secret_access_key` | Secret access key du provider S3-compatible |
| `secrets/age_recipient` | Clé **publique** age (`age1...`, cf. §8) utilisée pour chiffrer chaque dump avant sa copie hors-site — jamais la clé privée, qui ne doit exister que hors de ce VPS. **Vide = copie hors-site des sauvegardes désactivée**, cf. §8 |

**Copie hors-site des uploads (`App\Service\OffsiteBackupUploader`)** : chaque
fichier uploadé (`App\Service\MediaUploader` — photo de profil, logo, documents
de projet...) est copié de façon asynchrone vers un stockage S3-compatible
juste après l'upload, en plus du volume Docker `uploads_data` (persistant
entre déploiements mais toujours local au VPS). Tant que les 4 fichiers
ci-dessus n'existent pas (même vides), la fonctionnalité se désactive
proprement (log + no-op, aucune erreur) — mais **les 4 fichiers doivent
exister sur le VPS avant le prochain déploiement**, même vides, sinon
`deploy-remote.sh` bloque le déploiement (garde-fou secrets, cf. §7) :

```bash
# Sans provider pour l'instant (désactive juste la fonctionnalité proprement) :
touch infra/secrets/offsite_s3_endpoint infra/secrets/offsite_s3_bucket \
      infra/secrets/offsite_s3_access_key_id infra/secrets/offsite_s3_secret_access_key
chmod 600 infra/secrets/offsite_s3_*

# Avec un provider (ex. Cloudflare R2, recommandé : pas de frais de sortie) :
echo -n 'https://<account_id>.r2.cloudflarestorage.com' > infra/secrets/offsite_s3_endpoint
echo -n 'hmm-uploads-backup' > infra/secrets/offsite_s3_bucket
echo -n '<access_key_id>' > infra/secrets/offsite_s3_access_key_id
echo -n '<secret_access_key>' > infra/secrets/offsite_s3_secret_access_key
chmod 600 infra/secrets/offsite_s3_*
```

**Idem pour `secrets/age_recipient`** (nouveau secret requis par le service
`backend`, cf. §8) : `deploy-remote.sh` bloquera aussi le prochain déploiement
tant que ce fichier n'existe pas, même vide —

```bash
# Sans clé pour l'instant (désactive juste la copie hors-site des sauvegardes) :
touch infra/secrets/age_recipient && chmod 600 infra/secrets/age_recipient
```

**Idem pour `secrets/admin_basicauth`** (nouveau secret requis par le
conteneur `traefik` — middleware `admin-basicauth@file` devant Adminer, cf.
§3 / §10.5). Contrairement aux deux ci-dessus, il n'a **pas** de mode
« désactivé » : un fichier vide fait échouer *toute* connexion à
`db.horlynat.com` (fail-closed voulu — mieux vaut Adminer injoignable
qu'Adminer sans mot de passe). Un script dédié le génère (mot de passe
aléatoire, hash bcrypt, affiché une seule fois) :

```bash
./infra/scripts/gen-admin-basicauth.sh          # utilisateur "admin"
# -> note le mot de passe affiché dans ton gestionnaire de mots de passe
```

Si `htpasswd` manque : `sudo apt install -y apache2-utils` puis relance
(sans quoi le script retombe sur un hash `apr1`, accepté mais moins solide).

```bash
sudo chgrp -R hmmsecrets infra/secrets && sudo chmod 750 infra/secrets
sudo find infra/secrets -type f -exec chmod 640 {} \;
```

⚠️ **Droits des secrets : toujours `640`, groupe `hmmsecrets`** (cf. §11,
utilisateur `deploy`) — pas `600`. Le conteneur `frontend` tourne en
non-root (uid/gid 1001) : il lit ses secrets grâce au bit **groupe**, le
GID de `hmmsecrets` lui étant ajouté comme groupe supplémentaire
(`group_add: ${HMM_SECRETS_GID:-5002}` dans `docker-compose.prod.yml`, 5002 =
GID de `hmmsecrets` sur le VPS ; à surcharger dans `.env.prod` si `getent
group hmmsecrets` donne autre chose). **Aucun `chgrp` par fichier** vers un
autre groupe (ex. 1001) : un secret en `600`, ou dans un autre groupe, est
illisible pour ce conteneur. Le démarrage n'échoue PAS pour autant —
l'entrypoint journalise un avertissement (`[entrypoint] AVERTISSEMENT :
secret '...' illisible`) et continue sans charger le secret : le site reste
servi, mais la fonctionnalité concernée échoue fermée (ex. sans
`REVALIDATE_SECRET`, `/api/revalidate` répond 401 et la revalidation
déclenchée par le back-office ne se fait plus). Panne silencieuse côté
utilisateur : vérifier `docker compose logs frontend | grep entrypoint`
après chaque ajout/modification de secret. Après création d'un nouveau
secret, relancer les deux commandes ci-dessus.

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
`admin-ipwhitelist`, utilisé uniquement par `mailadmin.horlynat.com` — cf.
ci-dessous) par ta/tes vraie(s) IP fixe(s) — **tant que ce n'est pas fait,
`mailadmin.horlynat.com` reste inaccessible** (fail-closed voulu).

⚠️ **IP résidentielle = pas fixe**, testé en prod (changée en quelques
heures sur `dark.horlynat.com` avant d'en tirer la conclusion suivante) :
une whitelist IP statique sur un service auquel on se connecte depuis des
réseaux variables (mobile, wifi public...) finit par bloquer l'accès la
plupart du temps, pas par exception. C'est pour ça que `dark.horlynat.com`
n'utilise **plus** `admin-ipwhitelist@file` (cf. `docker-compose.prod.yml`,
routeur `dark`) : seuls `cloudflare-only` (aucun accès direct à l'origine
hors réseau Cloudflare) et Cloudflare Access (code email, dashboard
Cloudflare Zero Trust) protègent ce routeur — renforçable côté Cloudflare
(MFA, session courte) si besoin, sans dépendre d'une IP figée. `mailadmin.
horlynat.com` garde la whitelist (pas de Cloudflare Access dessus, cf. §10)
: si son IP doit aussi devenir mobile, se poser la même question avant de
la retirer là aussi (perte d'une couche de défense en profondeur, à
compenser autrement).

⚠️ Piège testé en prod sur ce réglage :
- **`docker compose restart traefik` obligatoire après modification**, pas
  juste une modification du fichier : `traefik.yml`/`dynamic.yml` sont
  montés en bind-mount de **fichier unique** (pas un dossier), et un outil
  qui réécrit le fichier via renommage atomique (`rsync`, la plupart des
  éditeurs) change son inode — le bind-mount du conteneur reste accroché à
  l'ancien inode. `watch: true` ne voit donc jamais la modification tant
  que le conteneur n'est pas relancé.

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
#    supporte ni un réseau bridge nommé pour un RUN, ni l'accès au loopback
#    de l'hôte hors mode `network: host` — qui donnait auparavant à TOUT le
#    build (dont `npm ci` et ses scripts postinstall) la pile réseau de
#    l'hôte entière (audit 2026-11-20, point 15 : accès possible à MariaDB,
#    Netdata... et, sur ce VPS partagé, à tout ce qui écoute côté de l'autre
#    projet). Remplacé par le service `api-build-gateway` (profil `build`,
#    cf. docker-compose.prod.yml) : une passerelle TCP socat éphémère,
#    publiée UNIQUEMENT sur l'IP de docker0 (jamais 0.0.0.0, jamais
#    127.0.0.1 — injoignable depuis un RUN hors mode host) et démarrée/
#    supprimée automatiquement par `deploy-remote.sh` autour du build.
#    Démarrage manuel équivalent :
docker compose --env-file .env.prod -f docker-compose.prod.yml --profile build up -d api-build-gateway
docker compose --env-file .env.prod -f docker-compose.prod.yml build --no-cache frontend
docker compose --env-file .env.prod -f docker-compose.prod.yml --profile build rm -sf api-build-gateway
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d frontend
docker compose -f docker-compose.prod.yml ps
```

⚠️ **Risque accepté pendant la fenêtre de build** : tant que la passerelle
existe (de l'attente API à la fin de `docker compose build frontend`),
`172.17.0.1:8000` expose le backend **entier**, HORS Traefik — sans
`cloudflare-only`, sans limitation de débit, sans la restriction de chemins
du routeur `hmm-api` (back-office `/login`, `/admin`... compris) — à tous les
conteneurs (y compris ceux de l'autre projet du VPS) et processus de
l'hôte. Accepté parce que la passerelle est éphémère (quelques minutes par
déploiement, supprimée même en cas d'échec ou de coupure SSH via les
traps de `deploy-remote.sh`), liée à l'IP de docker0 seulement (jamais
joignable depuis Internet : `DOCKER-USER` ne laisse passer que 80/443
depuis Cloudflare), et durcie (`socat` non-root, `cap_drop: ALL`,
`read_only`, `no-new-privileges`). Les protections applicatives (auth,
2FA, limiteurs Symfony) restent actives ; seules celles de Traefik sont
contournées. Ne jamais laisser ce service tourner en dehors d'un build
(`docker compose ps api-build-gateway` doit être vide après un
déploiement).

⚠️ `HMM_BUILD_GATEWAY_BIND` (`.env.prod`, défaut `172.17.0.1`) doit correspondre
à l'IP de `docker0` sur ce VPS (`ip -4 addr show docker0`) — sinon le build
échoue à joindre `backend` (testé localement : mécanisme validé de bout en
bout, y compris `wait_for_api()` de `deploy-remote.sh` via cette passerelle).

Aux déploiements suivants (mise à jour de code), même ordre à respecter —
sauf le contenu du back-office, qui persiste déjà en base.

### 6.5. Premier compte admin (bootstrap — une seule fois)

⚠️ Aucun mécanisme de seed/fixture ne crée de compte admin. Sur une base
neuve (`user` vide), il n'existe **aucun chemin applicatif** pour créer le
tout premier compte :
- `RegistrationController` (`/register`) exige déjà `ROLE_ADMIN` pour créer
  un compte — inscription publique volontairement absente pour un compte
  admin.
- `app:admin:recover` (CLI, cf. `src/Command/AdminRecoverCommand.php`) ne
  répare qu'un compte **existant** (mot de passe oublié, 2FA perdue,
  déverrouillage) — il ne crée rien.

Donc, pour ce premier compte (ou tout compte admin créé hors back-office) :

```bash
# 1. Hash du mot de passe (saisie masquée, jamais en clair sur la ligne de commande)
docker compose -f docker-compose.prod.yml exec -u www-data backend php bin/console security:hash-password

# 2. Insertion directe en base avec le hash obtenu
cat > /tmp/create-admin.sql <<'SQL_EOF'
INSERT INTO user (email, roles, password, is_verified, is_active, is_two_factor_enabled, created_at, updated_at)
VALUES ('email@exemple.com', '["ROLE_SUPER_ADMIN"]', '<hash obtenu ci-dessus>', 1, 1, 0, NOW(), NOW());
SQL_EOF
# MYSQL_PWD (variable d'env du seul process mysql lancé par docker exec) au
# lieu de "-p$DBPASS" : un argument de ligne de commande reste visible dans
# `ps aux`/`/proc/<pid>/cmdline` pour tout utilisateur du VPS le temps de
# l'exécution — une variable d'env transmise ainsi ne l'est pas (seul root,
# via /proc/<pid>/environ, peut encore la lire).
sudo docker exec -i -e MYSQL_PWD="$(sudo cat secrets/database_password)" infra-database-1 mysql -u app app < /tmp/create-admin.sql
rm -f /tmp/create-admin.sql   # contient le hash, pas juste un mot de passe en clair mais autant nettoyer
```

Rôles disponibles (hiérarchie complète dans `config/packages/security.yaml`) :
`ROLE_USER < ROLE_EDITOR < ROLE_MODERATOR < ROLE_MANAGER < ROLE_ADMIN <
ROLE_SUPER_ADMIN`. Une fois ce premier compte créé, les suivants peuvent
passer par `/register` (accessible uniquement en étant déjà connecté en
admin) plutôt que par SQL direct.

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

Règle 3-2-1 : 3 copies, 2 supports différents, 1 hors-site — désormais
tenue par 3 mécanismes distincts qui ne partagent aucun point de défaillance
commun, détaillés dans `backend/docs/incident-data-loss.md` :

1. **Locale** — `App\Service\DatabaseBackupService` (mysqldump), déclenchée
   depuis `/admin/backup` ou en CLI (`app:backup:create`), écrite dans
   `infra/backups/` (bind mount du VPS — cf. `docker-compose.prod.yml`,
   survit désormais aux déploiements, contrairement à avant que ce bind
   mount existe). Propriétaire du dossier corrigé automatiquement à chaque
   déploiement (`deploy-remote.sh`, `ensure_www_data_writable`) : sans ça,
   Docker le crée owned par root au premier `up`, et www-data (le process
   applicatif) ne peut pas y écrire — constaté en prod ("Permission
   denied"), pareil pour `infra/logs/backend`.
2. **Cloud, automatique** — la même commande chiffre (age) et pousse chaque
   dump vers le bucket S3-compatible (`OFFSITE_S3_*`, préfixe `database/`) :
   copie 24/7, indépendante du VPS.
3. **Machine perso** — `scripts/pull-backups.sh`, à exécuter (ou cron/
   launchd) **depuis ta machine**, pas sur le VPS : tire `infra/backups/` en
   `rsync` par-dessus SSH. Support physiquement différent du VPS et du
   provider cloud.

Générer la clé de chiffrement (une seule fois, **jamais sur le VPS** pour la
partie privée) :

```bash
age-keygen -o backup-key.txt   # depuis TA machine, pas le VPS
# Copie la clé publique affichée (age1...) dans le secret ci-dessous.
# Garde backup-key.txt (la clé PRIVÉE) uniquement sur ta machine / un gestionnaire
# de secrets — c'est la seule façon de déchiffrer une sauvegarde le jour où le
# VPS n'existe plus.
echo -n 'age1...' > infra/secrets/age_recipient
chmod 600 infra/secrets/age_recipient
```

Sans ce secret (ou sans les 4 `OFFSITE_S3_*`, cf. §4), la copie hors-site (2)
se désactive proprement (log, pas d'erreur) — seule la copie locale (1) reste
active, ce qui ne protège plus d'une perte du VPS lui-même.

Ajouter au crontab root :
```
0 3 * * * docker compose -f /opt/hmm/infra/docker-compose.prod.yml exec -T -u www-data backend php bin/console app:backup:create
# Purge RGPD des logs de conversation de l'assistant IA (> 90 jours, cf. §12)
0 4 * * * docker compose -f /opt/hmm/infra/docker-compose.prod.yml exec -T -u www-data backend php bin/console app:ai-assistant:purge-logs
# Purge du journal de connexions (connexions réussies > 365j, tentatives échouées > 90j, cf. SecurityLogRetentionPolicy)
0 5 * * * docker compose -f /opt/hmm/infra/docker-compose.prod.yml exec -T -u www-data backend php bin/console app:security-log:purge
```

Et, **depuis ta machine perso** (pas le VPS) :
```
0 7 * * * VPS_HOST=<IP_VPS> /chemin/vers/pull-backups.sh >> ~/hmm-backups/pull.log 2>&1
```

Tester une restauration au moins une fois avant d'en dépendre — normale
(base vivante, VPS intact) via `/admin/backup` (SUPER_ADMIN), désastre
complet (VPS perdu, base à reconstruire depuis la copie cloud ou la machine
perso) via la procédure pas-à-pas de `backend/docs/incident-data-loss.md`.

### 8.5. Montée MySQL 8.0 → 8.4 LTS — ⚠️ À VALIDER AVANT FUSION

**Non appliquée dans ce lot** (image actuelle : `mysql:8.0.46`, épinglée par
digest, dernière publication de la branche 8.0 — fin de vie). À faire dans
un **commit séparé**, après une sauvegarde complète et testée, en dehors
d'une fenêtre de déploiement automatique :

```bash
cd /opt/hmm/infra

# 1. Sauvegarde — AVANT toute action (cf. §8). Vérifier la taille/contenu
#    du dump obtenu, pas seulement son exit code.
docker compose -f docker-compose.prod.yml exec -T -u www-data backend \
  php bin/console app:backup:create

# 2. Snapshot du volume de données (filet de secours indépendant du dump
#    applicatif — restaure l'état binaire exact en cas de souci pendant la
#    conversion du format de fichiers 8.0 -> 8.4) :
docker compose -f docker-compose.prod.yml stop database
sudo cp -a "$(docker volume inspect -f '{{.Mountpoint}}' infra_mysql_data)" \
  /root/mysql_data.pre-8.4.bak   # chemin hors des bind mounts rsync'd

# 3. Changer l'image dans docker-compose.prod.yml :
#      image: mysql:8.4.13@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902
#    puis recréer SEULEMENT ce service :
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d database
docker compose -f docker-compose.prod.yml logs -f database   # suivre l'upgrade interne de l'image officielle (mysql_upgrade)

# 4. Vérifier la version effective et l'intégrité :
docker compose -f docker-compose.prod.yml exec database \
  sh -c 'MYSQL_PWD="$(cat /run/secrets/database_password)" mysql -u app app -e "SELECT VERSION(); CHECK TABLE user, project, invoice;"'

# 5. Redémarrer backend/messenger-worker (reconnexion propre, pas requis en
#    théorie — Doctrine se reconnecte — mais plus prudent) :
docker compose -f docker-compose.prod.yml restart backend messenger-worker

# 6. Mettre à jour database_url (serverVersion=8.4...) dans secrets/ si la
#    chaîne de connexion l'épingle explicitement, puis vérifier
#    /admin/dashboard et quelques écritures réelles (création d'un brouillon,
#    etc.) avant de considérer la montée terminée.
```

**Retour arrière** (avant l'étape 3, ou si l'étape 3-4 échoue) : remettre
`image: mysql:8.0.46@sha256:...` dans le compose, `docker compose stop
database`, restaurer le volume depuis le snapshot de l'étape 2
(`rsync -a --delete /root/mysql_data.pre-8.4.bak/ <mountpoint>/`), puis
`docker compose up -d database`. MySQL ne revient PAS en arrière après avoir
démarré en 8.4 sur un datadir donné (changement de format de fichiers
`undo` irréversible) — le retour arrière passe donc uniquement par la
restauration du volume, jamais par un simple changement de tag d'image.

### 9. Monitoring (léger, cohérent avec un VPS solo)

- **Netdata** : PAS `curl | sh` (exécute un script téléchargé sans jamais
  l'avoir lu — root sur l'hôte) : télécharger le kickstart, le lire, puis
  l'exécuter en deux étapes séparées (ou utiliser le paquet natif
  `apt install netdata` quand la version du dépôt Ubuntu est acceptable) :
  ```bash
  curl -fsSL https://get.netdata.cloud/kickstart.sh -o /tmp/netdata-kickstart.sh
  sha256sum /tmp/netdata-kickstart.sh   # comparer avec le hash publié par Netdata avant d'aller plus loin
  less /tmp/netdata-kickstart.sh        # relecture
  sudo sh /tmp/netdata-kickstart.sh
  rm -f /tmp/netdata-kickstart.sh
  ```
  Sur l'hôte (hors Docker — surveille aussi l'hôte lui-même, pas seulement
  les conteneurs). Pas scripté ici (interactif, à valider toi-même).
- **UptimeRobot / Better Stack** : moniteur externe HTTPS sur les 3 domaines,
  à créer manuellement (compte tiers, hors scope infra). Ajouter un moniteur
  **dédié à `https://dark.horlynat.com/login`** en plus de la racine des
  domaines — un moniteur sur `dark.horlynat.com` seul ne détecte pas
  forcément une panne localisée au firewall `main` (ex. incident OIDC déjà
  survenu en prod, cf. `backend/docs/incident-auth.md` §2, qui ne cassait que
  `/login/*`). En cas d'alerte sur ce moniteur précis : suivre
  `backend/docs/incident-auth.md`, pas `app:admin:recover` seul (qui ne
  répare qu'un compte, pas le code).

### 10. PostfixAdmin — gestion des comptes mail

Panneau web (`mailadmin.horlynat.com`) pour créer/éditer/supprimer les
boîtes Postfix/Dovecot sans repasser par du SQL manuel — remplace ce
qu'ISPConfig faisait avant d'être désinstallé du VPS. Mis en place une
seule fois (bootstrap) ; ensuite tout se gère depuis l'interface.

**Architecture** : Postfix/Dovecot restent natifs sur l'hôte (comme avant),
mais lisent maintenant leurs comptes/routage depuis une base MySQL dédiée
(`postfixadmin`, MariaDB natif sur l'hôte — **pas** la base applicative
Docker) au lieu de fichiers plats. PostfixAdmin lui-même tourne en
conteneur Docker derrière Traefik, protégé par `cloudflare-only` +
`admin-ipwhitelist` (IP fixe à maintenir à jour — contrairement à
`dark.horlynat.com`, ce routeur n'a pas de Cloudflare Access en
compensation, cf. §5).

**Si à refaire sur un VPS neuf** (résumé des étapes, déjà appliquées ici) :

```bash
# 1. Base dédiée (MariaDB déjà natif sur ce VPS, résidu de l'ancienne
#    installation ISPConfig — sinon : apt install mariadb-server)
sudo mysql <<'SQL'
CREATE DATABASE postfixadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'postfixadmin'@'172.18.0.0/255.255.0.0' IDENTIFIED BY '<motdepasse>'; -- conteneur PostfixAdmin (edge-net)
CREATE USER 'postfixadmin'@'127.0.0.1' IDENTIFIED BY '<motdepasse>';             -- Postfix/Dovecot natifs (lecture seule)
CREATE USER 'postfixadmin'@'localhost' IDENTIFIED BY '<motdepasse>';
GRANT ALL PRIVILEGES ON postfixadmin.* TO 'postfixadmin'@'172.18.0.0/255.255.0.0';
GRANT SELECT ON postfixadmin.* TO 'postfixadmin'@'127.0.0.1';
GRANT SELECT ON postfixadmin.* TO 'postfixadmin'@'localhost';
SQL

# MariaDB doit écouter au-delà de 127.0.0.1 pour que le conteneur (edge-net)
# la joigne — testé : bind-address par défaut = 127.0.0.1 uniquement.
sudo sed -i "s/^bind-address.*/bind-address            = 0.0.0.0/" /etc/mysql/mariadb.conf.d/50-server.cnf
sudo systemctl restart mariadb
sudo ufw allow from 172.16.0.0/12 to any port 3306 proto tcp comment "MariaDB PostfixAdmin (Docker only)"

# 2. Pilotes SQL manquants par défaut sur Ubuntu — sans eux : "Unknown
#    database driver 'mysql'" (Dovecot) / "unsupported dictionary type:
#    mysql" (Postfix). Testé, casse tout silencieusement sinon.
sudo apt install -y dovecot-mysql postfix-mysql

# 3. Conteneur PostfixAdmin : cf. service `postfixadmin` dans
#    docker-compose.prod.yml + secrets/postfixadmin_db_password et
#    secrets/postfixadmin_setup_password (hash bcrypt, PAS une variable
#    ${...} — testé, un hash plein de "$" est tronqué par la substitution
#    Compose). Générer le hash SANS passer le mot de passe en argument (visible
#    dans l'historique shell et `ps`) — saisie masquée (read -s), lue depuis
#    stdin par PHP, jamais affichée ni placée sur la ligne de commande :
#    read -rs -p 'Mot de passe : ' PFA_PW && echo && printf '%s' "$PFA_PW" | \
#      php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), "\n";'; unset PFA_PW

# 4. Une fois le conteneur up et le domaine/les boîtes créés via
#    https://mailadmin.horlynat.com/setup.php puis l'UI normale :
#    basculer Dovecot et Postfix dessus.

# Dovecot : /etc/dovecot/dovecot-sql.conf.ext (driver=mysql, connect via
# 127.0.0.1, default_pass_scheme=CRYPT — détecte automatiquement le format
# du hash stocké par PostfixAdmin, bcrypt $2y$ dans les faits malgré
# $CONF['encrypt']=md5crypt affiché par setup.php).
# passdb -> sql dans /etc/dovecot/conf.d/auth-sql.conf.ext (garder userdb en
# static, mêmes uid/gid/home qu'avant — inutile de tout re-router).
# Activer l'include : commenter "!include auth-passwdfile.conf.ext" et
# décommenter "!include auth-sql.conf.ext" dans 10-auth.conf.
sudo systemctl reload dovecot

# Postfix : trois fichiers mysql-virtual-{mailbox-domains,mailbox-maps,alias-maps}.cf
# dans /etc/postfix/ (user/password/hosts=127.0.0.1/dbname/query), puis :
sudo postconf -e \
  "virtual_mailbox_domains=mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf" \
  "virtual_mailbox_maps=mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf" \
  "virtual_alias_maps=mysql:/etc/postfix/mysql-virtual-alias-maps.cf"
sudo postfix check && sudo systemctl reload postfix

# 5. Test direct (sans passer par un envoi réel) :
sudo doveadm auth test <compte>@horlynat.com
```

⚠️ Les alias par défaut créés automatiquement par PostfixAdmin à la création
d'un domaine (`abuse@`, `postmaster@`, `hostmaster@`, `webmaster@`)
pointent vers un placeholder cassé (`...@change-this-to-your.domain.tld`)
— à corriger manuellement (UPDATE SQL ou UI) vers une vraie boîte après
création du domaine.

### 10.5. Adminer — gestion de la base applicative

Interface web (`db.horlynat.com`) pour consulter/éditer la base MySQL du
service `database` sans repasser par un `docker compose exec` + SQL manuel.
Ajouté sur demande explicite, pas un outil de dev laissé traîner en prod.

**Protection en couches** — contrairement à `dark.horlynat.com` (auth
applicative Symfony : mot de passe + 2FA + rate-limit + fail2ban + blocage
IP) et `mailadmin.horlynat.com` (`admin-ipwhitelist`), **Adminer n'a aucun
contrôle d'accès propre** au-delà de l'écran de connexion MySQL. D'où trois
barrières, **dans cet ordre précis** sur le routeur (`cloudflare-only` puis
`security-headers` puis `ratelimit-strict` puis `admin-basicauth`) : le
limiteur de débit doit voir — et compter — CHAQUE essai de mot de passe,
basic-auth compris. Dans l'ordre inverse (testé) : `admin-basicauth`
répond 401 avant que `ratelimit-strict` n'intervienne, autorisant un
brute-force illimité sur le htpasswd depuis une IP qui reste malgré tout
derrière Cloudflare.

1. `cloudflare-only@file` — n'accepte que le trafic transitant par Cloudflare.
   À lui seul, il ne bloque **pas** un visiteur Cloudflare quelconque : toute
   requête proxyée par Cloudflare (n'importe qui pointant un domaine chez eux)
   atteint le routeur.
2. `ratelimit-strict@file` — anti-bruteforce, compte les essais AVANT
   l'étape suivante (cf. remarque d'ordre ci-dessus).
3. `admin-basicauth@file` — **barrière d'identité à l'origine**, htpasswd
   bcrypt (`secrets/admin_basicauth`, cf. §4), gérée dans ce dépôt et donc
   vérifiable. Elle tient même si l'application Cloudflare Access ci-dessous
   est supprimée, expirée ou mal configurée. Fail-closed : secret manquant =
   `db.horlynat.com` injoignable.
4. **Cloudflare Access** (Zero Trust, code email) — barrière principale
   recommandée, en amont. Configurée hors dépôt → à créer ET à revérifier
   périodiquement (cf. checklist §12).

Pas de `admin-ipwhitelist` (même raison qu'en §5 : IP non stable).

**Deux étapes manuelles, côté dashboard Cloudflare, avant que ce soit
utilisable** (rien de tout ça n'est dans ce repo) :

1. **DNS** : enregistrement `db` → IP du VPS, proxied (nuage orange), comme
   `dark`/`api`/`www`.
2. **Access** : Zero Trust → Access → Applications → nouvelle application
   couvrant `db.horlynat.com`, même politique (code email) que celle déjà en
   place sur `dark.horlynat.com` — la dupliquer plutôt que la réutiliser
   (une politique par sous-domaine). **Vérifier** ensuite en navigation
   privée que `https://db.horlynat.com` renvoie bien l'écran Access (et pas
   directement Adminer).

**Connexion** : sur l'écran de connexion Adminer, le champ Serveur est déjà
pré-rempli (`database`) — Système = MySQL, Utilisateur = `app`, Mot de passe
= contenu de `secrets/database_password`, Base = `app`. Identifiants jamais
stockés côté conteneur, ressaisis à chaque session — aucun secret
supplémentaire à provisionner, c'est le même compte que celui du backend.

⚠️ Accès en lecture **et écriture** sur les données réelles de l'application
(comptes, factures, devis...) — pas un accès en lecture seule.

### 11. Déploiement continu (GitHub Actions)

Trois workflows (`.github/workflows/`), déclenchés uniquement depuis `main`
(le repo est un monorepo à branches longues intégrées par PR — backend/
frontend n'ont pas leur propre copie de ces fichiers, inutile puisque
`pull_request` lit toujours la version présente sur la branche cible).
Durcissement commun (audit 2026-11-20, point 15) : `permissions: contents:
read` au niveau workflow partout (`packages: write` uniquement sur le job
qui pousse l'image), toutes les actions tierces épinglées par SHA complet
(tag d'origine en commentaire — Dependabot, cf. plus bas, propose les
montées de version sans jamais relâcher l'épinglage).

- `backend-ci.yml` — sur chaque PR touchant `backend/` :
  - `test` : PHPStan + PHPUnit contre une vraie base MySQL 8.0.46 (service
    container, même version épinglée qu'en prod), PHP 8.4 à jour (`update:
    true`) ;
  - `image-scan` : construit l'image de prod (même `Dockerfile`, cache
    `gha`) **sans la pousser** (`push: false`), vérifie que le PHP embarqué
    est ≥ 8.4.27 (même garde-fou PHP2Shell que dans `deploy.yml`), puis la
    scanne avec **Trivy** (image officielle épinglée par digest, pas l'action
    tierce `aquasecurity/trivy-action` — dont les tags ont été réécrits lors
    d'une compromission de la supply chain en mars 2026) : la CI échoue sur
    toute vulnérabilité **CRITICAL/HIGH pour laquelle un correctif existe**
    (`--ignore-unfixed` : une CVE sans correctif publié n'est pas
    actionnable, elle ne bloque pas la PR).
- `frontend-ci.yml` — sur chaque PR touchant `frontend/` : lint + tests
  unitaires (Vitest) + `type-check`. **Ne construit pas** l'app (`next build`
  a besoin d'un backend joignable et peuplé de contenu réel, cf. commentaire
  dans le fichier) — les tests e2e (`test:e2e`, Playwright) restent
  volontairement manuels pour la même raison.
- `deploy.yml` — à chaque merge sur `main` (ou déclenchement manuel) :
  1. `resolve-tag` : valide `backend_tag` (`workflow_dispatch`) contre
     `^([0-9a-f]{7,40}|latest)$` **avant tout usage** — une valeur hors de ce
     format fait échouer le run immédiatement, avant même de toucher au VPS ;
  2. `build-backend-image` (sauté sur un rollback, `backend_tag` fourni) :
     build **sans push**, vérification PHP ≥ 8.4.27, puis push sur GHCR
     (`ghcr.io/horlynat/hmm-backend`, tags `latest` et `<sha>`) — c'est la
     même image pour `backend` et `messenger-worker` (cf.
     `docker-compose.prod.yml`) ;
  3. `deploy` (environnement GitHub **`production`** protégé, validation
     manuelle requise — cf. ci-dessous) :
     - clé SSH décodée dans un fichier temporaire, jamais interpolée dans
       une commande shell (`DEPLOY_SSH_KEY`/`DEPLOY_HOST` lus via `env:`) ;
     - `rsync` de `infra/` et `frontend/` vers `/opt/hmm/` (jamais
       `backend/`, dont le code arrive désormais uniquement via l'image
       GHCR) ;
     - exécution de `infra/scripts/deploy-remote.sh <tag>` sur le VPS (tag
       passé en **argument**, re-validé une 2ᵉ fois côté script, échappé
       via `printf %q` avant d'entrer dans la commande SSH), qui reproduit
       l'ordre du §6 (backend → migrations → messenger-worker → frontend,
       toujours dans cet ordre, redeploy ou non) ;
     - clé SSH supprimée du runner en fin de job (`if: always()`).
  - `concurrency: deploy-production` (`cancel-in-progress: false`) : un
    deuxième déclenchement (merge rapproché, rollback manuel) attend la fin
    du précédent plutôt que d'entrelacer deux `rsync`/`docker compose up`
    sur le même VPS — un déploiement coupé à mi-chemin est pire qu'une
    file d'attente.

**Garde-fou secrets** : `deploy-remote.sh` vérifie, avant tout appel Docker
Compose, que chaque secret déclaré dans `docker-compose.prod.yml` existe bien
sur le VPS — sinon le déploiement s'arrête immédiatement avec la liste
précise des fichiers manquants (`infra/secrets/<nom>`) plutôt que d'échouer
avec l'erreur générique de Compose. Constaté en prod : ajouter un nouveau
secret dans `docker-compose.prod.yml` sans l'avoir d'abord créé sur le VPS
fait échouer le déploiement dès la première commande — toujours créer le
fichier secret (§4) **avant** de merger/pusher le changement qui le
référence.

**Garde-fou final** : après le déploiement, `deploy-remote.sh` vérifie que
`https://api.horlynat.com/login` renvoie bien **404** (cf. point 4
ci-dessous, routeur `hmm-api` restreint à `/api`/`/uploads`/`/newsletter`) —
tout autre code fait échouer le run avec une alerte explicite, pour détecter
une régression de cette restriction au déploiement qui la causerait, pas
des semaines après.

**Rollback** : relancer `deploy.yml` manuellement (`workflow_dispatch`) avec
`backend_tag` = un `<sha>` antérieur (visible dans l'historique GHCR ou les
runs précédents) — saute le rebuild et redéploie directement cette image.

**Dependabot** (`.github/dependabot.yml`) : mises à jour hebdomadaires
groupées (une PR par écosystème et par semaine, passant par les mêmes CI)
pour les actions GitHub (SHA + commentaire mis à jour ensemble), les images
des `Dockerfile` (`backend/`, `frontend/`), les images du compose de prod
(`infra/`, écosystème `docker-compose`), Composer (`backend/`) et npm
(`backend/`, `frontend/`). Les alertes de **sécurité** Dependabot (*Settings
→ Code security*) restent immédiates, indépendantes de ce calendrier.
`backend/Dockerfile` référence l'image FrankenPHP via un `ARG`
(`FRANKENPHP_IMAGE`) que Dependabot ne suit pas : la montée PHP/FrankenPHP
reste manuelle (cf. commentaire dans ce fichier).

#### Secret de déploiement — à déplacer en secrets d'environnement (ACTION MANUELLE REQUISE)

⚠️ **Constat de l'audit, non corrigé automatiquement** (nécessite des clics
dans les réglages GitHub, pas un changement de code) : si `DEPLOY_SSH_KEY`
et `DEPLOY_HOST` restent des secrets/variables **de dépôt** (*Settings →
Secrets and variables → Actions*), **n'importe quel workflow déclenché
depuis n'importe quelle branche poussée** y a accès, sans attendre
l'approbation manuelle de l'environnement `production` — et l'utilisateur
`deploy` est dans le groupe `docker`, donc équivalent root sur le VPS (cf.
plus bas). Un repli `${{ secrets.DEPLOY_SSH_KEY || ... }}` existe dans
`deploy.yml` pour ne pas casser un déploiement en cours de bascule, mais
**le but est de ne plus jamais utiliser ce repli en pratique**.

**Bascule sans coupure de service** — GitHub résout `secrets.X`/`vars.X`
dans un job qui porte `environment: production` (c'est le cas du job
`deploy`) en préférant la valeur **de l'environnement** sur celle **du
dépôt** quand les deux existent sous le même nom, de façon totalement
transparente pour `deploy.yml` : aucun changement de workflow n'est requis
pour basculer, seulement des clics dans les réglages, dans cet ordre :

1. *Settings → Environments → `production`* (déjà créé pour le required
   reviewer, cf. ci-dessous) → **Add secret** → `DEPLOY_SSH_KEY`, même
   valeur que le secret de dépôt actuel. Idem **Add variable** pour
   `DEPLOY_HOST` et `DEPLOY_KNOWN_HOSTS` (ou **Add secret** pour `DEPLOY_HOST`
   si tu préfères ne plus l'exposer même en lecture dans les réglages —
   `deploy.yml` accepte les deux formes, cf. `secrets.DEPLOY_HOST ||
   vars.DEPLOY_HOST`). Pendant cette étape, le secret/la variable de dépôt
   reste en place : la valeur de l'environnement, une fois ajoutée, est
   DÉJÀ celle utilisée par le job `deploy` (priorité automatique) — pas de
   fenêtre sans secret disponible.
2. Déclencher un déploiement de test (`workflow_dispatch` sans
   `backend_tag`, par ex.) et vérifier qu'il réussit — il utilise déjà la
   valeur de l'environnement à ce stade (les deux étant identiques, aucune
   différence observable ; l'étape 3 confirme laquelle était réellement lue).
3. **Supprimer** `DEPLOY_SSH_KEY`/`DEPLOY_HOST` au niveau dépôt (*Settings →
   Secrets and variables → Actions*) et relancer le même déploiement de
   test : s'il réussit toujours, la valeur venait bien de l'environnement.
   Ne JAMAIS faire cette suppression avant l'étape 1 (le job échouerait
   faute de n'importe quelle valeur disponible).
4. *Settings → Environments → `production` → Deployment branches and tags*
   → **Selected branches** → `main` : rend explicite et vérifiable dans les
   réglages que ces secrets ne sont accessibles qu'aux runs déclenchés
   depuis `main` (déjà le cas par construction, puisque seul `main`
   déclenche `deploy.yml`, mais une restriction au niveau de
   l'environnement reste la garantie qui ne dépend pas de rester
   synchronisée avec `on.push.branches` du workflow).

**Restriction de la clé côté VPS** (`~deploy/.ssh/authorized_keys`), pour
qu'une clé volée ne donne PAS un shell SSH interactif : préfixer la clé CI
d'un `command=` forcé et de `restrict` (pas de pty, ni redirection de
port/agent/X11), par exemple :

```
restrict,command="/usr/local/sbin/hmm-deploy-gate" ssh-ed25519 AAAA... ci-deploy
```

**⚠️ Proposé, NON intégré à ce lot** : un script `deploy-ssh-gate.sh`
implémentant cette commande forcée (délègue le `rsync` en écriture seule à
`rrsync`, confiné à `/opt/hmm/`, et n'autorise que
`bash /opt/hmm/infra/scripts/deploy-remote.sh <tag>` avec `<tag>` déjà
validé) a été écrit et testé localement (rsync légitime accepté, lecture/
tunnel/autre commande/injection shell dans le tag tous refusés et journalisés)
mais **reste hors de ce dépôt** : une restriction de clé SSH touche l'accès
à l'hôte entier, potentiellement partagé avec un autre projet sur ce même
VPS — décision à prendre par toi, pas par ce lot. Si tu veux l'adopter,
demande-le explicitement ; le fichier existe en dehors du contrôle de
version, prêt à être ajouté.

#### Utilisateur `deploy` dédié (pas `ubuntu`)

La CI ne dispose jamais de la clé du compte `ubuntu` (sudo complet). Un
utilisateur système `deploy` a été créé spécifiquement :

```bash
sudo useradd -m -s /bin/bash -G docker deploy   # docker uniquement, pas sudo
sudo usermod -aG ubuntu deploy                  # accès rw à infra/frontend/backend (déjà group=ubuntu)
sudo groupadd hmmsecrets                        # lecture SEULE sur infra/secrets
sudo usermod -aG hmmsecrets ubuntu deploy
sudo chgrp -R hmmsecrets infra/secrets && sudo chmod 750 infra/secrets
sudo find infra/secrets -type f -exec chmod 640 {} \;
# Ajouter deploy à la liste blanche SSH posée par 01-base-hardening.sh :
sudo sed -i 's/^AllowUsers ubuntu$/AllowUsers ubuntu deploy/' /etc/ssh/sshd_config.d/99-hardening.conf
sudo systemctl reload ssh   # PAS "sshd" : nom de service = ssh sur Ubuntu (cf. §1)
```

⚠️ Honnêteté sur le niveau réel d'isolation : l'appartenance au groupe
`docker` équivaut déjà à un accès root (accès au socket Docker = possibilité
de monter n'importe quel chemin hôte dans un conteneur). Ne pas donner sudo
à `deploy` évite un accès root *interactif* direct, mais pas un accès root
*via Docker* — c'est un choix de moindre privilège raisonnable pour ce
contexte (clé stockée uniquement dans les secrets chiffrés GitHub, jamais
sur un poste), pas une isolation complète.

#### Secrets et variables à configurer sur GitHub

**Cible** (après la bascule décrite ci-dessus — *Settings → Environments →
`production`*, pas *Settings → Secrets and variables → Actions* au niveau
dépôt) :

| Portée | Type | Nom | Valeur |
|---|---|---|---|
| Environnement `production` | Secret | `DEPLOY_SSH_KEY` | Clé privée ed25519 dédiée à la CI (jamais celle de `ubuntu`) |
| Environnement `production` | Variable (ou secret) | `DEPLOY_HOST` | IP ou nom d'hôte du VPS |
| Environnement `production` | Variable | `DEPLOY_KNOWN_HOSTS` | Sortie de `ssh-keyscan -t ed25519 <IP_VPS>` |

*Settings → Environments → New environment `production`* : ajouter un
*required reviewer* (toi-même) pour que chaque déploiement attende une
validation manuelle en un clic avant de s'exécuter, et restreindre
*Deployment branches and tags* à `main` (cf. ci-dessus).

*Packages* : le package `hmm-backend` doit être **public** (Settings du
package sur GitHub) — aucun secret n'est embarqué dans l'image (tout passe
par les Docker secrets montés à l'exécution, cf. §4), ça évite d'avoir à
gérer un token de lecture GHCR sur le VPS. Sinon (image privée), prévoir un
`docker login ghcr.io` avec un PAT `read:packages` dans
`deploy-remote.sh`.

### 12. Assistant IA (RAG Gemini + Claude)

Architecture hybride : ingestion asynchrone (Gemini, résumé + embedding du
contenu Project/Article/Experience, table MySQL `document_embedding` — pas
de pgvector, la prod est en MySQL) déclenchée automatiquement à chaque
création/modification de contenu via Symfony Messenger (même worker que le
reste, `messenger-worker` ci-dessus) ; conversation temps réel (Claude,
`POST /api/assistant/chat`) qui fait un retrieval par similarité cosinus
(calculée en PHP) sur ces embeddings avant de répondre.

- **Clés API** : `secrets/gemini_api_key` (ingestion + embedding, montée sur
  `backend` et `messenger-worker`) et `secrets/anthropic_api_key`
  (conversationnel, montée seulement sur `backend`) — cf. §4. Jamais
  partagées avec d'autres usages.
- **Réingestion manuelle** (après un changement de contenu massif, un
  ajustement du prompt de résumé, ou une migration de modèle Gemini) :
  ```bash
  docker compose -f docker-compose.prod.yml exec -T -u www-data backend php bin/console app:assistant:reingest --all
  # ou une seule entité :
  docker compose -f docker-compose.prod.yml exec -T -u www-data backend php bin/console app:assistant:reingest --entity=Project --id=42
  ```
- **Plafond budgétaire** (`ASSISTANT_MONTHLY_BUDGET_USD`, cf. `.env.prod`) :
  dès dépassement, bascule automatique sur la réponse de repli statique
  (`AiAssistantSettings.fallback`) + email d'alerte (au plus 1/jour). Suivi
  du coût cumulé dans `ai_assistant_conversation_log` (table anonymisée,
  jamais de texte brut — cf. ci-dessous).
- **Cache de prompt Claude** (`App\Service\ClaudeClient`, `cache_control`
  ephemeral TTL 1h sur le system prompt) : le contexte RAG injecté est
  identique à chaque question tant que le contenu n'a pas été réingéré —
  mis en cache côté Anthropic, une lecture en cache ne coûte que 10% du prix
  d'entrée standard (contre 100% sans cache), rentable dès la 2e question
  dans l'heure. Suivi séparé dans `AiAssistantChatProcessor::estimateCost()`
  (écriture cache = 2x le prix d'entrée, lecture cache = 0,1x).
- **Coupe-circuit immédiat** : décocher "Chat conversationnel actif" dans
  `/admin/ai-assistant/settings` désactive `/api/assistant/chat` (503) sans
  redéploiement. Les chips de FAQ restent toujours actives (réponses
  locales, sans appel externe).
- **Garde-fous anti prompt-injection** : détection heuristique en entrée
  (`AiAssistantInputGuard`), séparation stricte system/user dans l'appel
  Claude, contexte RAG délimité explicitement dans le system prompt, et
  sanitisation de sortie (`AiAssistantOutputSanitizer`) qui détecte toute
  fuite du system prompt et plafonne la longueur de la réponse.
- **RGPD** : `ai_assistant_conversation_log` ne peut structurellement
  contenir aucun texte brut (longueurs, hash d'IP salé, ids de chunks
  utilisés, coût, statut — jamais la question/réponse elle-même). Purge
  automatique après 90 jours (`app:ai-assistant:purge-logs`, en cron, cf.
  §8) — c'est le garde-fou documenté depuis longtemps dans "Hors périmètre"
  ci-dessous, désormais implémenté.

### 13. Durcissement des conteneurs & réseau (ce lot, services du portfolio)

Scope de ce lot : **uniquement** les services propres au portfolio
(`backend`, `messenger-worker`, `frontend`, `database`, `adminer`,
`api-build-gateway`). Le service `traefik`, ses fichiers
(`traefik/traefik.yml`, `traefik/dynamic.yml`), le service `postfixadmin`
et les `networks:` du compose sont **partagés avec un autre projet hébergé
sur ce même VPS** et restent donc hors de ce lot — recommandations listées
en fin de section, à valider séparément avant tout changement.

#### Images épinglées (version exacte + digest)

Toutes les images des services du portfolio sont désormais épinglées par
digest (`image:tag@sha256:...`) — une image retaguée localement ou repoussée
sous le même tag sur le registre n'est jamais utilisée silencieusement.
Dependabot (ci-dessus) propose les montées de version. Résumé :

| Service | Avant | Après |
|---|---|---|
| `database` | `mysql:8.0` (flottant) | `mysql:8.0.46@sha256:...` (fin de vie — montée 8.4 LTS documentée en §8.5, hors de ce lot) |
| `adminer` | `adminer:latest` | `adminer:6.3.0@sha256:...` (PHP 8.4.28 embarqué ; `latest` tournait depuis 3 mois en PHP 8.4.24, vulnérable PHP2Shell) |
| `backend`/`messenger-worker` | `ghcr.io/horlynat/hmm-backend:${BACKEND_IMAGE_TAG}` | inchangé (déjà versionné par sha de commit, cf. `deploy.yml`) |
| `api-build-gateway` (nouveau) | — | `alpine/socat:1.8.1.3@sha256:...` |

`deploy-remote.sh` fait désormais un `pull` explicite de **tous** les
services du portfolio tirés d'un registre (`database backend
messenger-worker adminer`, plus `api-build-gateway` sous son profil) — pas
seulement `backend` comme avant. C'est ce qui applique réellement une
montée de version de `mysql`/`adminer`/`socat` dès le déploiement qui la
contient (sans ce `pull`, une image locale plus ancienne du même tag reste
utilisée indéfiniment). `traefik` et `postfixadmin` sont exclus de cette
liste : partagés avec l'autre projet / image locale non versionnée
(`postfixadmin:dbg`, cf. plus bas) — un `pull` sur `postfixadmin` ferait
échouer tout le déploiement.

#### `no-new-privileges` / `cap_drop: [ALL]` / `read_only` / `pids_limit`

Appliqué à `backend`, `messenger-worker`, `frontend`, `database`, `adminer`
(`x-hardening` dans `docker-compose.prod.yml`), chaque `cap_add` justifié en
commentaire à côté du service et **validé en conditions réelles** (stack
jetable locale : migrations, seed, build frontend, connexion Adminer) :

| Service | `cap_add` | `read_only` | Pourquoi |
|---|---|---|---|
| `backend` | `CHOWN,DAC_OVERRIDE,SETUID,SETGID,NET_BIND_SERVICE` | non | entrypoint root (lit les secrets, écrit `.env.local`), su-exec vers www-data, frankenphp lié au port 80 |
| `messenger-worker` | `CHOWN,DAC_OVERRIDE,SETUID,SETGID` | non | même entrypoint, jamais frankenphp (pas de `NET_BIND_SERVICE`) |
| `frontend` | aucune (+ `group_add` GID `hmmsecrets`) | non (ISR réécrit `.next/`) | image entièrement non-root (uid 1001) : secrets lus via le bit groupe, ni root ni su-exec |
| `database` | `CHOWN,DAC_OVERRIDE,SETUID,SETGID` | **oui** (+ tmpfs `/tmp`, `/var/run/mysqld`) | entrypoint officiel MySQL, validé : init + migrations + redémarrage OK |
| `adminer` | aucune | **oui** (+ tmpfs `/tmp`) | aucun entrypoint privilégié, sessions PHP en tmpfs |
| `api-build-gateway` | aucune | **oui** | `socat` seul, utilisateur non-root (`65534:65534`) |

`pids_limit` posé sur les 6 (64 à 512 selon le service) — anti fork-bomb,
sans impact observé en usage normal (worker Messenger, requêtes concurrentes
backend/frontend testées en local).

#### Routeur `api` restreint (point 4 de l'audit)

`api.horlynat.com` ne servait auparavant aucune restriction de chemin — tout
le back-office (`/login`, `/admin/*`, `/2fa`...) y était joignable avec le
rate-limit standard (pas `strict`) et sans Cloudflare Access. Restreint à :
`Host(api.horlynat.com) && (Path(/api) || PathPrefix(/api/) ||
PathPrefix(/uploads/) || PathPrefix(/newsletter/))` — vérifié contre le
frontend (`frontend/src/lib/media.ts`, `next.config.ts` : seul `/uploads/**`
est consommé publiquement par le domaine API) et le backend (préfixe
`/newsletter/*`, routes publiques hors `/api` cliquées depuis un client
mail ; `/build` et `/bundles` volontairement exclus — back-office seul
consommateur, doc API Platform désactivée en prod). **Testé par attaque** :
~25 tentatives de contournement (traversée de chemin en clair et encodée,
double-encodage, `..;`, `%00`, préfixe dupliqué `//api/..`) contre une
instance Traefik réelle — toutes bloquées (404 natif de Traefik, backend
jamais atteint) ; les quelques requêtes qui matchent légitimement le
préfixe `/api/` littéral (ex. `/api/..%2Fadmin/`) atteignent bien l'API,
jamais le back-office (confirmé : réponse vide, pas de contenu admin/login).

#### Collision de noms de routeur/service Traefik avec l'autre projet du VPS — **trouvé pendant la phase d'attaque**

En testant la restriction ci-dessus contre une instance Traefik réelle
partageant le socket Docker de l'hôte (comme en prod), les routeurs/services
du portfolio nommés `api`, `backend`, `frontend` et `dbadmin` (identifiants
**globaux à toute l'instance Traefik**, tous projets confondus — pas de
simple nom de service Docker) sont entrés en collision avec des conteneurs
d'un autre projet utilisant les **mêmes noms génériques** pour leurs propres
routeurs. Résultat observé : `"HTTP router defined multiple times with
different configurations"` — Traefik **supprime les deux routeurs en
conflit**, silencieusement (404 natif, aucune erreur visible côté
utilisateur) ; `api.horlynat.com` (et le routeur de l'autre côté) auraient
cessé de répondre sans qu'aucun log d'erreur applicatif ne le signale.

Ce risque est **pré-existant à ce lot** (les noms `api`/`backend`/`dark`/
`dbadmin` étaient déjà ceux du compose avant ces changements) et touche
potentiellement la prod réelle, selon les noms utilisés par l'autre projet
— à vérifier côté Traefik/dashboard interne (désactivé ici) ou en
confirmant avec l'équipe de l'autre projet.

**Corrigé** (changement de labels, uniquement sur les services du
portfolio — ne touche ni le service `traefik`, ni `postfixadmin`, ni les
`networks:`) : tous les identifiants de routeur/service du portfolio
préfixés `hmm-` (`hmm-api`, `hmm-dark`, `hmm-frontend`, `hmm-backend`,
`hmm-dbadmin`) — `mailadmin`/`postfixadmin`, hors périmètre de ce lot, **non
renommé**, à vérifier séparément. **Retesté après correction** : plus aucune
erreur de collision, les quatre routeurs du portfolio répondent
correctement (`api.horlynat.com`, `dark.horlynat.com`, `horlynat.com`/`www`,
`db.horlynat.com`), y compris avec les conteneurs de l'autre projet présents
sur le même hôte Docker.

#### Variables d'environnement (`.env.prod`)

Documentées dans `.env.prod.example` ; les défauts du compose suffisent en
production :

- `BACKOFFICE_URL` — base des liens e-mail destinés aux comptes back-office
  (`App\Service\MailLinkGenerator`) ; défaut `https://dark.horlynat.com`.
- `PUBLIC_API_HOSTS` — hôte(s) où seuls `/api`, `/uploads`, `/newsletter`
  répondent côté Symfony ; défaut `api.horlynat.com`.
- `SYMFONY_TRUSTED_HOSTS` — liste blanche d'hôtes Symfony (400 sinon) ;
  défaut `^(api|dark)\.horlynat\.com$,^hmm-backend$`.
  `^hmm-backend$` (alias edge-net propre au portfolio) est indispensable (appels internes du frontend + build via `api-build-gateway`).
- `CF_ACCESS_HOSTS`, `CF_ACCESS_TEAM_DOMAIN`, `CF_ACCESS_AUD` — vérification
  côté Symfony du jeton Cloudflare Access (vide = inactif). À renseigner
  ensemble : `CF_ACCESS_HOSTS` seul refuse tout sur ces hôtes (fail-closed).
- `PUBLIC_API_HOSTS` inclut aussi les noms internes `hmm-backend`/`backend` :
  joignables sans Cloudflare, ils ne servent que `/api`, `/uploads`,
  `/newsletter`, jamais le back-office.
- `HMM_BUILD_GATEWAY_BIND` — IP de `docker0` pour la passerelle de build
  éphémère (défaut `172.17.0.1`, cf. §6).

#### Secrets SQL hors ligne de commande (P2, `README` uniquement)

`mysql -p"$DBPASS"` (argument visible dans `ps`) remplacé par `MYSQL_PWD`
(variable d'environnement du seul process concerné) dans le healthcheck
`database` et dans la procédure de création du premier compte admin (§6.5).
Génération du hash bcrypt PostfixAdmin (§10) : saisie masquée (`read -s`),
plus de mot de passe en clair sur la ligne de commande ni dans l'historique
shell. Netdata (§9) : plus de `curl | sh` (téléchargement, vérification,
relecture puis exécution en étapes séparées).

#### `.gitignore`

Racine (nouveau) + `infra/.gitignore` (étendu) : `*.sql`, `*.sql.gz`,
`*.sql.age`, `backups/`, `logs/`, `.env.prod*` (sauf `.env.prod.example`),
clés/certificats (`*.pem`, `*.key`, `id_*`). Aucun fichier déjà suivi par
Git ne correspondait à ces nouveaux motifs (vérifié par `git ls-files` avant
d'ajouter les règles — rien à purger de l'historique).

#### Hors périmètre de ce lot — infra partagée avec l'autre projet (recommandations, non appliquées)

- **`docker.sock` monté dans Traefik** (`:ro`, qui ne protège pas l'API) :
  recommandation inchangée — `tecnativa/docker-socket-proxy` (image
  épinglée, `CONTAINERS=1 NETWORKS=1 EVENTS=1 POST=0`) sur un réseau
  interne dédié, `providers.docker.endpoint: tcp://socket-proxy:2375` côté
  Traefik. Nécessite de modifier le service `traefik` partagé — à faire
  avec l'accord de l'autre projet, pas unilatéralement.
- **`providers.docker.network`**, `aliasHeadersStrategy`,
  `core.strictTLSOptions`, `encodedCharacters` (`traefik.yml`) : durcissements
  valables en général (testés dans ce lot sur une instance isolée) mais
  touchent la config statique partagée — à proposer, pas à appliquer seul.
- **Segmentation réseau** (`edge-net` plat, un seul réseau pour tous les
  services routés) : un réseau par paire routeur↔service réduirait le rayon
  d'action d'un conteneur compromis, mais `edge-net`/`data-net` sont
  partagés avec l'autre projet — à coordonner.
- **`SYMFONY_TRUSTED_PROXIES`** sur tout le RFC1918 : resserrer au seul
  sous-réseau Traefik↔backend suppose un réseau dédié à cette paire (cf.
  point précédent) — même dépendance.
- **Image PostfixAdmin** : la prod tourne sur `postfixadmin:dbg` (image
  locale construite sur le VPS par l'autre projet, absente de tout
  registre — confirmé : un `pull` échoue avec `not found`). Cible validée
  en local pour ce lot (non appliquée) : `postfixadmin:4.0.7-apache@sha256:
  3d81c8e0c55d6984befc4076765f57cf730c1288b3408373569181d42176b636` (PHP
  8.4.28, contre PHP 8.3.33 sur l'image `:dbg` actuelle — montée de version
  PostfixAdmin elle-même, donc schéma de base à vérifier via `/setup.php`
  avant bascule). Durcissement conteneur prêt mais non appliqué (cap_add
  `CHOWN,DAC_OVERRIDE,FOWNER,SETUID,SETGID,KILL`, validé localement contre
  l'image officielle 4.0.7) — à activer SEULEMENT en même temps que le
  changement d'image, jamais sur l'image `:dbg` actuelle (capacités non
  testées contre elle).
- **fail2ban `symfony-security`** (bannit l'IP réelle mais le trafic arrive
  de Cloudflare via `DOCKER-USER`, pas filtré par ce jail), jail `sshd`
  (cohérence du port avec la CI) : scripts hôte (`01-base-hardening.sh`)
  hors de ce lot.

## Hors périmètre de cette livraison (documenté, pas implémenté)

- **WireGuard devant `dark.horlynat.com`** : amélioration future la plus
  impactante selon le guide — donnerait une IP de tunnel fixe même en
  connexion mobile, restaurant une vraie défense en profondeur par IP en
  plus de Cloudflare Access. Pour l'instant : `cloudflare-only` +
  Cloudflare Access seuls (whitelist IP retirée, cf. §5 — testé en prod,
  une IP figée sur ce routeur bloquait l'accès la plupart du temps plutôt
  que par exception). `mailadmin.horlynat.com` garde la whitelist IP,
  faute de Cloudflare Access dessus.
- ~~Filtrage de sortie anti-prompt-injection de l'assistant IA et
  anonymisation RGPD des logs de conversation~~ : implémenté, cf. §12.
- ~~**`config/packages/security.yaml`** : la règle globale
  `{ path: ^/api, roles: IS_AUTHENTICATED_FULLY }` bloquait tous les appels
  publics vers `/api`~~ : corrigée côté backend (`^/api, roles:
  PUBLIC_ACCESS` — sécurité déléguée à API Platform par opération, cf.
  `security.yaml` actuel). Vérifié le 27/08/2026, cette note était restée
  périmée ici après le correctif.
- **Wazuh/SIEM, Prometheus+Grafana** : mentionnés par le guide pour des
  besoins plus lourds qu'un VPS solo — AIDE/rkhunter/auditd + Netdata
  suffisent ici. Note laissée si le besoin grandit.
- ~~Channel Monolog dédié `security_errors` → fichier~~ : implémenté
  (`security_errors_file` dans `config/packages/monolog.yaml` +
  `App\EventSubscriber\LoginFailureLoggerSubscriber` côté backend, cf.
  `backend/docs/incident-auth.md` §5). Vérifié en conditions réelles avec
  une tentative de connexion échouée depuis une IP externe : `fail2ban
  status symfony-security` compte bien la tentative. A nécessité aussi
  `backend = polling` sur ce jail (cf. `01-base-hardening.sh`) — `auto`
  résolvait vers le backend systemd sur ce serveur, qui ignorait
  totalement `logpath`.

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
- [ ] Sauvegardes 3-2-1 en place (locale + hors-site cloud + machine perso, cf. §8) et restauration testée sur les trois
- [ ] Monitoring (Netdata + Uptime externe) en place
- [ ] `admin-ipwhitelist` éditée avec la vraie IP avant d'utiliser `mailadmin.horlynat.com` (`dark.horlynat.com` n'en dépend plus, cf. §5)
- [ ] `secrets/admin_basicauth` généré (htpasswd bcrypt) avant tout déploiement — sinon `db.horlynat.com` est injoignable (fail-closed voulu, cf. §4 / §10.5)
- [ ] DNS `db.horlynat.com` + application Cloudflare Access créés avant d'utiliser Adminer (cf. §10.5) — sans Access, `cloudflare-only` seul ne bloque que le hors-Cloudflare, pas un visiteur Cloudflare quelconque
- [ ] **Revérifier périodiquement** (navigation privée) que `db.horlynat.com`, `dark.horlynat.com` et `mailadmin.horlynat.com` renvoient bien l'écran Cloudflare Access / la whitelist — une politique supprimée ou expirée ne se voit pas autrement
- [x] `security.yaml` `/api` corrigé côté backend (vérifié 27/08/2026)
