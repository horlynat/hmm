# Architecture — hmm (horlynat.com)

> Document de référence unique, vérifié contre le code et l'infrastructure
> réels (pas contre les specs de conception antérieures, qui ont dérivé —
> cf. §10). Dernière vérification : 27/08/2026.
> Vue d'ensemble opérationnelle — pour le détail pas-à-pas d'une procédure,
> chaque section pointe vers le document source.

## 1. En un coup d'œil

`hmm` est une plateforme portfolio + back-office pour une activité freelance :
vitrine publique bilingue (Next.js), API + back-office de gestion (Symfony),
assistant conversationnel IA, et l'infrastructure qui fait tourner le tout sur
un unique VPS derrière Cloudflare.

Monorepo à 3 branches longues intégrées par Pull Request — jamais de push
direct :

```
backend/    → branche `backend`   → Symfony 8.1 / PHP 8.4, API Platform 4, MySQL 8
frontend/   → branche `frontend`  → Next.js 16 (App Router), React 19, TypeScript
infra/      → branche `main`      → Docker Compose, Traefik, GitHub Actions, scripts VPS
```

`backend` et `frontend` se synchronisent régulièrement vers `main` (PR
"merge: synchronise X avec main") ; `main` est la seule branche réellement
déployée.

## 2. Vue d'ensemble système

```
                            Internet
                               │
                     ┌─────────▼─────────┐
                     │     Cloudflare     │  DNS proxied, WAF, TLS Full Strict,
                     │  (+ Access sur     │  Cloudflare Access (Zero Trust) sur
                     │   dark/db/mail...) │  dark.horlynat.com et db.horlynat.com
                     └─────────┬─────────┘
                               │  (uniquement IP Cloudflare — cf. §6.4)
                     ┌─────────▼─────────┐
                     │   VPS (Ubuntu 24)  │  4 vCores / 8 Go / 150 Go / 200 Mbps
                     │  ufw + fail2ban    │
                     │  ┌───────────────┐ │
                     │  │    Traefik    │ │  reverse-proxy, certs Origin Cloudflare
                     │  └───┬───┬───┬───┘ │
                     │      │   │   │     │
                     │  ┌───▼┐┌─▼─┐┌▼───┐ │
                     │  │front││back││...│ │  containers Docker (edge-net / data-net)
                     │  └────┘└─┬──┘└────┘ │
                     │          │          │
                     │      ┌───▼───┐      │
                     │      │ MySQL │      │  volume nommé, jamais exposé
                     │      └───────┘      │
                     │                     │
                     │  Postfix/Dovecot    │  natifs sur l'hôte (pas Docker),
                     │  natifs (mail)      │  pilotés par PostfixAdmin (conteneur)
                     └─────────────────────┘
```

### 2.1. Domaines

| Domaine | Sert | Protection |
|---|---|---|
| `horlynat.com` / `www` | Vitrine publique Next.js | Cloudflare (proxied) |
| `api.horlynat.com` | API Symfony (API Platform) | Cloudflare (proxied), sécurité par opération |
| `dark.horlynat.com` | Back-office (projets, contenu, utilisateurs, incidents...) | Cloudflare-only + Cloudflare Access (code email) + auth Symfony + 2FA |
| `db.horlynat.com` | Adminer (base applicative) | Identique à `dark` (Cloudflare-only + Access) |
| `mailadmin.horlynat.com` | PostfixAdmin (comptes mail) | Cloudflare-only + whitelist IP (pas de Cloudflare Access dessus) |
| `vps122840.serveur-vps.net` | Webmail/IMAP/SMTP (pas de sous-domaine dédié) | Comptes Postfix/Dovecot |

`dark.horlynat.com` est l'espace le plus sensible (budgets/dépenses/historique
clients) : c'est le seul point avec 4 couches indépendantes (Cloudflare edge →
Access → auth Symfony → 2FA TOTP).

## 3. Stack technique

| Domaine | Backend | Frontend | Infra |
|---|---|---|---|
| Langage/Framework | PHP 8.4, Symfony 8.1 | TypeScript, Next.js 16 (App Router), React 19 | Bash, YAML (Docker Compose, GitHub Actions) |
| API | API Platform 4 (Doctrine ORM) | 100 % server-side (Server Components/Actions), zéro appel client direct à l'API | — |
| Données | MySQL 8 | — (aucune donnée propre, tout vient de l'API) | Volume Docker nommé `mysql_data` |
| Auth | JWT (`lexik/jwt-bundle`, `/api/login_check`) + session Symfony + 2FA TOTP (`scheb/2fa-bundle`) pour le back-office | — (pas de compte utilisateur public) | Cloudflare Access en amont de `dark`/`db` |
| Assets | Twig, Symfony UX (Stimulus/Turbo/Live Component), Tailwind v4, Vite | Tailwind v4, `next-intl` (FR par défaut, EN) | — |
| Tests | PHPUnit, PHPStan, PHP-CS-Fixer | Vitest + Testing Library, Playwright (e2e manuel) | — |
| Reverse proxy / TLS | — | — | Traefik + certificat Origin Cloudflare (Full Strict) |
| CI/CD | `backend-ci.yml` (PHPStan + PHPUnit sur MySQL 8 réel) | `frontend-ci.yml` (lint + Vitest, pas de build) | `deploy.yml` (GHCR + déploiement VPS) |

## 4. Composants déployés (`docker-compose.prod.yml`)

| Service | Rôle |
|---|---|
| `traefik` | Reverse-proxy, routage par domaine, TLS |
| `frontend` | Next.js, construit **sur le VPS** à chaque déploiement (a besoin du backend joignable pendant `next build` pour l'ISR — cf. §8.3) |
| `backend` | Symfony/PHP-FPM, image versionnée sur GHCR (`ghcr.io/horlynat/hmm-backend`) |
| `messenger-worker` | Même image que `backend`, consomme la queue asynchrone (ingestion RAG assistant IA, emails...) |
| `database` | MySQL 8, volume `mysql_data` |
| `adminer` | Client web MySQL, derrière `db.horlynat.com` |
| `postfixadmin` | Gestion des comptes mail, derrière `mailadmin.horlynat.com` — Postfix/Dovecot restent natifs sur l'hôte, lisent leurs comptes depuis une base MariaDB dédiée |

Deux réseaux Docker : `edge-net` (exposé à Traefik) et `data-net` (MySQL,
jamais exposé). Secrets montés en fichiers (Docker secrets), jamais en
variables d'environnement en clair — cf. `infra/README.md` §4 pour la liste
complète (17 secrets : JWT, DB, mail, clés API IA, sauvegarde chiffrée...).

## 5. Modèle de données (40 entités, par domaine)

| Domaine | Entités principales |
|---|---|
| Contenu public | `Article`, `Project`, `Course`, `Experience`, `Skill`/`SkillCategory`, `Tag`, `Testimonial`, `HomeContent`, `AboutContent`, `Media`, `Comment` |
| Suivi projet client | `ProjectExpense`, `ProjectHistory`, `ProjectInfo`, `ProjectJoinRequest`, `ProjectTask`, `TimeEntry`, `Invoice` |
| Utilisateurs & sécurité | `User`, `Role`, `PermissionDefinition`, `LoginHistory`, `FailedLoginAttempt`, `BlockedIp`, `AuditLog`, `UserSession`, `NotificationPreference` |
| Support & communication | `ContactMessage`, `QuoteRequest`, `CandidateMessage`, `SupportTicket`/`SupportTicketMessage`, `NewsletterSubscriber`, `Translation` |
| Assistant IA | `AiAssistantEntry`, `AiAssistantDocumentChunk`, `AiAssistantConversationLog`, `AiAssistantSettings` |
| Exploitation | `Incident`, `SystemSetting`, `Integration` |

`Project` reste l'entité centrale : statut piloté par `ProjectStatusEnum`
(workflow de transitions valides — un projet `COMPLETED` peut être rouvert en
`IN_PROGRESS` par un admin, seule exception au principe "aucune suppression",
cf. `backend/docs/*` et l'historique de ce document).

## 6. Sécurité

### 6.1. RBAC — rôles et permissions

Hiérarchie stricte (`config/packages/security.yaml`) :

```
ROLE_USER < ROLE_EDITOR < ROLE_MODERATOR < ROLE_MANAGER < ROLE_ADMIN < ROLE_SUPER_ADMIN
```

- 16 `Voter` métier (un par domaine : `ProjectVoter`, `ArticleVoter`,
  `FinanceVoter`, `IncidentVoter`...) héritent tous d'`AbstractRoleVoter`.
- `PermissionRegistry` permet une surcharge dynamique par rôle, stockée en
  base (`PermissionDefinition`/`Role`, cache 6h) — sauf préfixes
  `SECURITY_`/`SETTINGS_`, non-surchargeables (`NON_OVERRIDABLE_PREFIXES`),
  pour qu'aucune permission de sécurité ne puisse être auto-élargie depuis
  l'interface.
- `ROLE_SUPER_ADMIN` n'est jamais actif par défaut sur un compte, même s'il
  est présent en base : `User::getRoles()` le retire tant que
  `isSuperAdminElevated()` (élévation à durée limitée) n'est pas active —
  principe de moindre privilège même pour le compte le plus élevé.
- `access_control` (`security.yaml`) applique un deny-by-default final
  (`^/, roles: ROLE_USER`) : toute route non listée explicitement exige au
  minimum une session authentifiée, même si son contrôleur oublie
  `#[IsGranted]`.

### 6.2. Authentification

- **Back-office** (`dark.horlynat.com`) : formulaire Symfony classique +
  **2FA TOTP obligatoire** (`scheb/2fa-bundle`), y compris sur une session
  restaurée par cookie "remember me" (7 jours) — pas de contournement de la
  2FA par ce biais.
- **API** (`api.horlynat.com`) : JWT stateless (`lexik/jwt-bundle`) via
  `POST /api/login_check` — utilisé pour les intégrations, pas par le
  frontend public (qui n'a aucun compte utilisateur, cf. §6.3).
- **CSRF** : double-submit cookie (Symfony UX `SameOriginCsrfTokenManager`)
  sur tous les formulaires du back-office.
- Comptes verrouillés après 5 échecs/15 min (`LoginListener`), alerte admin
  après 3 échecs/1h depuis la même IP.

### 6.3. Frontend public — aucune surface d'authentification

Le site vitrine n'a **aucun compte utilisateur** : toutes les lectures
(Server Components) et écritures (contact/devis, via Server Actions) passent
par le serveur Next.js, jamais par le navigateur du visiteur. Conséquence :
aucune clé d'API n'est jamais exposée côté client, aucun CORS à maintenir
pour le site public.

### 6.4. Défense en profondeur réseau

1. **Cloudflare** : proxy + WAF managé sur tous les sous-domaines, TLS Full
   (Strict), certificat Origin (15 ans) installé sur Traefik.
2. **`cloudflare-only`** : le VPS n'accepte le trafic 80/443 que depuis les
   plages IP Cloudflare — impossible de contourner le WAF/Access en appelant
   l'IP du VPS directement.
3. **Cloudflare Access** (Zero Trust, code email) devant `dark`/`db` — un
   filtre indépendant de Symfony ; si l'application est un jour compromise,
   cette couche reste debout.
4. **fail2ban** (`symfony-security` jail) bannit après échecs répétés sur
   `/login` — chaîne vérifiée en conditions réelles (échec de connexion
   externe → log JSON dédié → `fail2ban status` incrémenté).
5. **ufw + auditd + AIDE/rkhunter + AppArmor + unattended-upgrades** —
   durcissement OS standard (`infra/scripts/01-base-hardening.sh`).

Volontairement absent (documenté, pas un oubli) : WireGuard devant `dark`
(donnerait une IP de tunnel fixe), Wazuh/SIEM (disproportionné pour un VPS
solo) — cf. `infra/README.md` "Hors périmètre".

## 7. Assistant IA (RAG Gemini + Claude)

Architecture hybride, isolée du reste de l'application :

- **Ingestion asynchrone** (Gemini — résumé + embedding du contenu
  Project/Article/Experience) déclenchée à chaque création/modification de
  contenu via Symfony Messenger, stockée en MySQL (`document_embedding`, pas
  de pgvector).
- **Conversation temps réel** (Claude, `POST /api/assistant/chat`) :
  retrieval par similarité cosinus (calculée en PHP) sur ces embeddings avant
  chaque réponse.
- **Garde-fous** : détection heuristique de prompt-injection en entrée,
  séparation stricte system/user, sanitisation de sortie (fuite de system
  prompt détectée, longueur plafonnée), coupe-circuit immédiat depuis
  `/admin/ai-assistant/settings` (désactive le chat sans redéploiement — les
  FAQ statiques restent actives).
- **Budget** : plafond mensuel configurable, bascule automatique sur réponse
  de repli + alerte email au dépassement ; cache de prompt Claude (TTL 1h)
  pour réduire le coût des questions répétées.
- **RGPD** : `AiAssistantConversationLog` ne peut structurellement contenir
  aucun texte brut (longueurs, hash d'IP salé, coût — jamais la
  question/réponse) ; purge automatique après 90 jours (cron).

## 8. Déploiement & CI/CD

### 8.1. Pipeline (`.github/workflows/deploy.yml`)

Déclenché à chaque merge sur `main` (ou manuellement, avec possibilité de
rollback vers un tag d'image antérieur) :

1. Build + push de l'image backend sur GHCR (même image pour `backend` et
   `messenger-worker`, seule la commande diffère).
2. **Validation manuelle obligatoire** — environnement GitHub `production`
   protégé par un *required reviewer* : chaque déploiement attend une
   approbation explicite avant de s'exécuter, y compris pour un simple
   changement de documentation.
3. `rsync` de `infra/` et `frontend/` vers le VPS (jamais `backend/`, dont le
   code arrive uniquement via l'image GHCR).
4. `deploy-remote.sh` sur le VPS : backend → migrations → messenger-worker →
   frontend, toujours dans cet ordre (le frontend a besoin du backend et de
   son contenu réel pendant son propre build, cf. §8.3).

Garde-fou : le script vérifie que chaque secret déclaré dans
`docker-compose.prod.yml` existe bien sur le VPS avant tout appel Docker —
sinon arrêt immédiat avec la liste précise des fichiers manquants.

### 8.2. Intégration monorepo

```
feature branch → PR → backend (ou frontend)  [CI : PHPStan/PHPUnit ou lint/Vitest]
backend/frontend → PR "merge: synchronise X avec main" → main  [déclenche deploy.yml]
```

`backend`/`frontend` n'ont pas leur propre copie des workflows GitHub Actions
— `pull_request` lit toujours la version présente sur la branche cible.

### 8.3. Contrainte de build frontend

Le frontend fait des appels serveur vers l'API **pendant** `next build`
(génération statique/ISR). Deux pages (accueil, "À propos") vont plus loin :
leur contenu vient d'une ligne unique en base et son absence fait
volontairement échouer leur génération statique — signaler une vraie panne
plutôt que publier une page vide. D'où l'ordre strict backend → seed contenu
→ frontend à chaque déploiement.

## 9. Sauvegardes & continuité — voir aussi §11

Règle 3-2-1 tenue par 3 mécanismes indépendants (aucun point de défaillance
commun) :

1. **Locale** — `mysqldump` quotidien (cron + `/admin/backup`), bind mount
   persistant du VPS.
2. **Cloud, automatique** — chiffrement `age` + envoi vers un bucket
   S3-compatible (Cloudflare R2), indépendant du VPS.
3. **Machine personnelle** — `pull-backups.sh`, tiré depuis la machine de
   l'opérateur (pas poussé depuis le VPS), en cron quotidien.

Rotation : les 5 dernières générations conservées, localement et hors-site,
au cas où la plus récente serait compromise. Détail complet et procédures de
restauration : `backend/docs/incident-data-loss.md`.

## 10. Plan de continuité (résumé — détail dans `backend/docs/`)

| Scénario | Remède | Document |
|---|---|---|
| Compte admin bloqué (mot de passe/2FA perdus) | `app:admin:recover` (CLI) | `incident-auth.md` §1 |
| Firewall/code cassé (déploiement défaillant) | Rollback GitHub Actions vers un tag GHCR antérieur | `incident-auth.md` §2 |
| Base neuve, aucun compte | Création SQL directe du premier compte (aucun chemin applicatif n'en crée un) | `incident-auth.md` §3 |
| Perte de l'accès SSH lui-même | 2 clés admin indépendantes installées sur le VPS (résolu 27/08/2026, testé en réel) | `incident-auth.md` §4 |
| Cloudflare/Access en panne ou compte bloqué | Aucun remède applicatif — vérifier le statut Cloudflare et l'accès au compte | `incident-auth.md` §5 |
| Perte de données (corruption, suppression, ransomware) | Restauration depuis l'une des 3 copies 3-2-1 | `incident-data-loss.md` |
| Perte totale du VPS | Reconstruction complète depuis la copie cloud ou la copie personnelle | `incident-data-loss.md` §2 |

**Checklist humaine** (comptes tiers, hors de portée d'un commit) — état au
27/08/2026, détaillé dans `incident-auth.md` §6 :

- [x] 2ᵉ clé SSH admin (installée + testée)
- [x] 2ᵉ copie clé `age` (gestionnaire de mots de passe)
- [x] 2ᵉ copie clé SSH de secours (gestionnaire de mots de passe)
- [x] 2FA Cloudflare (3 facteurs indépendants : clé de sécurité, mobile, e-mail)
- [x] 2FA GitHub (GitHub Mobile — dépend d'un seul appareil, recovery codes recommandés en secours)
- [ ] Auto-renouvellement du domaine `horlynat.com`
- [ ] Moyens de paiement à jour (VPS / Cloudflare / bucket S3)

**Gestion des incidents applicatifs** : `/admin/incident` — journal complet
(catégorie, sévérité, statut, détection de récurrence), distinct du plan de
continuité ci-dessus qui couvre les pannes d'infrastructure/accès.

## 11. Observabilité

- **Logs applicatifs** : canaux Monolog dédiés (`security_errors`,
  `business_errors`, séparés du canal `main` bufferisé) — un échec de
  connexion atteint le fichier de log en clair, condition nécessaire pour que
  fail2ban puisse le lire.
- **Netdata** : supervision de l'hôte (pas seulement les conteneurs).
- **Monitoring externe** (UptimeRobot/Better Stack) : un moniteur dédié à
  `dark.horlynat.com/login`, en plus de la racine des domaines — une panne
  localisée au firewall ne casse parfois que `/login/*`, invisible pour un
  moniteur sur la racine seule.

## 12. Limites connues et hors périmètre (assumé, pas un oubli)

- **VPS unique** — pas de haute disponibilité ; le plan de continuité (§10)
  compense par la rapidité de reconstruction, pas par la redondance active.
- **WireGuard** devant `dark.horlynat.com` non posé — Cloudflare Access seul
  en compensation (une whitelist IP fixe a été testée et abandonnée : bloque
  plus souvent qu'elle ne protège depuis un réseau mobile).
- **Wazuh/SIEM, Prometheus/Grafana** non posés — jugés disproportionnés pour
  un VPS solo ; AIDE/rkhunter/auditd + Netdata suffisent à ce stade.
- **`mailadmin.horlynat.com`** reste protégé par whitelist IP (pas de
  Cloudflare Access dessus) — à revoir si l'IP d'administration devient
  mobile.

## 13. Où trouver le détail

| Sujet | Document |
|---|---|
| Procédure d'exécution infra complète (durcissement, secrets, déploiement pas-à-pas, mail, Adminer) | `infra/README.md` |
| Setup Cloudflare détaillé | `frontend/_procedure_cloudflare.md` |
| Gestion des erreurs (backend) | `backend/docs/error-handling.md` |
| Cycle de vie d'un projet | `backend/_cycle_vie_project.md` |
| Incidents d'authentification/accès | `backend/docs/incident-auth.md` |
| Perte de données | `backend/docs/incident-data-loss.md` |
| Développement local backend | `README.md` (racine) |

---

*Ce document remplace l'ancienne description d'architecture du `README.md`
racine (PostgreSQL, déploiement nginx/supervisor bare-metal), devenue fausse
par rapport au système réellement déployé (MySQL 8, Docker Compose + Traefik
+ GitHub Actions). Le `README.md` reste la référence pour le développement
local backend ; ce document est la référence pour l'architecture et la
continuité.*
