# Changelog — hmm (horlynat.com)

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/) —
[SemVer](https://semver.org/lang/fr/). Chaque entrée renvoie vers les PR
GitHub réelles ([milestone v2.0.0](https://github.com/horlynat/hmm/milestone/1))
plutôt que de reformuler leur contenu.

## [2.0.0] — 2026-08-27

Première version taguée du projet. Définit une base de référence après un
cycle de travail centré sur la continuité de service : que se passe-t-il si
l'authentification tombe, si une sauvegarde est corrompue, ou si le VPS est
perdu — et vérification de chaque réponse en conditions réelles plutôt que
sur la seule lecture du code.

Détail complet de l'état du système : [`ARCHITECTURE.md`](ARCHITECTURE.md).

### Continuité de service

- Runbooks de reprise après incident : compte admin bloqué, firewall/code
  cassé, base neuve, perte de l'accès SSH, panne Cloudflare Access
  ([#91](https://github.com/horlynat/hmm/pull/91),
  [#92](https://github.com/horlynat/hmm/pull/92)).
- Un projet marqué `COMPLETED` peut être rouvert par un admin (`ROLE_ADMIN`)
  au lieu d'être définitivement figé ([#91](https://github.com/horlynat/hmm/pull/91)).

### Sauvegardes — règle 3-2-1 réelle

- Sauvegardes locales rendues persistantes (bind mount survivant aux
  déploiements — ne l'étaient pas avant), chiffrement `age` + copie
  hors-site automatique vers Cloudflare R2, 3ᵉ copie tirée vers une machine
  personnelle ([#93](https://github.com/horlynat/hmm/pull/93),
  [#95](https://github.com/horlynat/hmm/pull/95),
  [#97](https://github.com/horlynat/hmm/pull/97),
  [#98](https://github.com/horlynat/hmm/pull/98)).
- Rotation sur 5 générations (locale et hors-site), pour survivre à une
  sauvegarde la plus récente compromise
  ([#101](https://github.com/horlynat/hmm/pull/101)).
- Script de sauvegarde legacy cassé (dialecte PostgreSQL contre une prod
  MySQL) retiré, restauration testée avec des données de production réelles.

### Sécurité périmétrique

- Canal de log dédié aux échecs d'authentification, jusque-là jamais
  atteint par les échecs de connexion web standards — la chaîne complète
  (échec → log → `fail2ban`) est vérifiée avec une tentative de connexion
  échouée depuis une IP externe réelle
  ([#100](https://github.com/horlynat/hmm/pull/100),
  [#104](https://github.com/horlynat/hmm/pull/104),
  [#106](https://github.com/horlynat/hmm/pull/106)).
- Espace de gestion des incidents (`/admin/incident`) : historique,
  catégorisation, sévérité, détection de récurrence
  ([#100](https://github.com/horlynat/hmm/pull/100)).

### Résilience d'accès

- Deuxième clé SSH admin indépendante installée et testée sur le VPS —
  l'accès ne dépendait jusque-là que d'une seule clé
  ([#107](https://github.com/horlynat/hmm/pull/107),
  [#108](https://github.com/horlynat/hmm/pull/108)).
- Deuxième copie de la clé de chiffrement `age` et de la clé SSH de secours
  déplacées hors de la machine principale
  ([#110](https://github.com/horlynat/hmm/pull/110)).
- 2FA vérifiée active sur les comptes Cloudflare (3 facteurs indépendants)
  et GitHub ([#112](https://github.com/horlynat/hmm/pull/112),
  [#114](https://github.com/horlynat/hmm/pull/114)).

### Documentation

- [`ARCHITECTURE.md`](ARCHITECTURE.md) : référence unique consolidée
  (backend, frontend, infra, sécurité, continuité), vérifiée contre le code
  et l'infrastructure réels.
- Correction du `README.md` racine, qui décrivait une architecture
  périmée (PostgreSQL, déploiement bare-metal) sans rapport avec le système
  réellement déployé (MySQL 8, Docker Compose + Traefik + GitHub Actions)
  ([#116](https://github.com/horlynat/hmm/pull/116)).

### Reste hors de portée d'un commit

Auto-renouvellement du domaine et moyens de paiement (VPS/Cloudflare/S3) —
suivi dans `backend/docs/incident-auth.md` §6.

[2.0.0]: https://github.com/horlynat/hmm/releases/tag/v2.0.0
