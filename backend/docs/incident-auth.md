# Incident d'authentification — que faire quand plus personne ne peut administrer

Deux scénarios très différents partagent le même symptôme ("je ne peux plus me
connecter à `/admin`"), et confondre les deux fait perdre du temps pendant
l'incident. Commencer par les distinguer :

| Symptôme | Cause probable | Remède |
|---|---|---|
| Un compte précis est bloqué (mot de passe oublié, 2FA perdue, compte désactivé) — les autres comptes admin, eux, se connectent normalement | Problème de **compte** | `app:admin:recover` (§1) |
| **Personne** ne peut se connecter, y compris des comptes qui fonctionnaient la veille ; `/login` renvoie une 500 ou un comportement anormal | Problème de **code/config** (firewall, OIDC, JWT) | Rollback du déploiement (§2) |
| Base neuve, table `user` vide | Pas encore de compte | Bootstrap (§3, déjà documenté ailleurs) |

`app:admin:recover` ne répare que le premier cas. Lancée sur le deuxième, elle
ne fait rien d'utile : le compte n'a rien de cassé, c'est le code qui
l'empêche d'être évalué.

## 1. Compte bloqué

CLI volontaire (`src/Command/AdminRecoverCommand.php`) — pas de flux web, pas
d'email, pas de token qui pourrait fuiter, pour un scénario rare. Nécessite un
accès SSH au VPS (cf. §4 sur ce prérequis).

```bash
docker compose -f docker-compose.prod.yml exec -u www-data backend \
  php bin/console app:admin:recover email@exemple.com
# Sans option : réinitialise le mot de passe (saisie masquée), réactive le
# compte et désactive la 2FA, en une fois. Options ciblées disponibles :
# --reset-password / --unlock / --disable-2fa
```

Chaque action est journalisée (`AuditLogger`, action `admin_recovery_cli`),
consultable ensuite dans `/admin` comme n'importe quel événement de sécurité.

## 2. Firewall/code cassé

Incident déjà survenu en pratique (cf. commentaire dans
`config/packages/security.yaml` : une config OIDC invalide a fait renvoyer
une 500 sur **tout** `/login/*`, avant même d'atteindre un contrôleur). Dans
ce cas, `app:admin:recover` ne répare rien — il faut revenir à la version de
code précédente :

1. GitHub → Actions → `deploy.yml` → *Run workflow*.
2. Renseigner `backend_tag` avec le SHA d'une image antérieure connue-saine
   (visible dans l'historique GHCR ou les runs précédents de ce workflow).
3. Lancer — ça saute le rebuild et redéploie directement cette image.

Détail complet : `infra/README.md` §11 ("Rollback").

Si le rollback ne suffit pas (la régression est plus ancienne que les images
disponibles), diagnostic direct sur le VPS :

```bash
docker compose -f docker-compose.prod.yml logs backend --tail=200
```

## 3. Base neuve, aucun compte

Cas différent des deux précédents (pas une panne, un état initial normal) :
aucun mécanisme applicatif ne crée de premier compte admin — l'inscription
publique (`/register`) exige déjà `ROLE_ADMIN`. Procédure de bootstrap
(hash + INSERT SQL direct) : `infra/README.md` §6.5.

## 4. Prérequis commun aux §1 et §2 : l'accès SSH lui-même

Les deux remèdes ci-dessus supposent un accès SSH au VPS. `01-base-hardening.sh`
n'installe qu'une seule clé (`ADMIN_PUBKEY`) au moment du durcissement initial
— si cette clé est perdue, ou si la seule personne qui la détient est
indisponible, ni §1 ni §2 ne sont exécutables. Point de défaillance unique à
corriger une fois, pas à chaque incident :

```bash
# Sur le VPS, ajouter une deuxième clé de confiance à côté de la première :
echo "ssh-ed25519 AAAA... second-porteur@..." >> ~deploy/.ssh/authorized_keys
```

## 5. Cloudflare Access — en amont de tout ce qui précède

`dark.horlynat.com` est protégé par `cloudflare-only` (aucun accès direct à
l'origine hors réseau Cloudflare) **et** Cloudflare Access (Zero Trust, code
email) — cf. `infra/docker-compose.prod.yml`, routeur `dark`. Si l'un des
deux casse (compte Cloudflare bloqué, policy Access mal configurée, panne
Cloudflare), la requête n'atteint même pas `/login` : §1 et §2 ci-dessus ne
s'appliquent pas, ils supposent qu'on peut déjà atteindre l'application.

Aucun remède applicatif ici — c'est un problème de compte/service tiers, pas
de code : vérifier le statut Cloudflare (cloudflare-status.com), l'accès au
compte Cloudflare lui-même (§6 ci-dessous), et la policy Access dans le
dashboard Zero Trust (Access → Applications → `dark.horlynat.com`).

## 6. Points hors du contrôle applicatif — checklist humaine

Aucun de ces points ne se corrige par un commit ; à vérifier/poser
soi-même, une fois, pas à chaque incident :

- [ ] **Compte Cloudflare** : 2FA avec codes de secours sauvegardés hors du
  seul appareil habituel (perdre l'accès à ce compte bloque §5 ci-dessus).
- [ ] **Compte GitHub** (`horlynat`) : 2FA + codes de secours — sans lui,
  impossible de déclencher le rollback du §2.
- [ ] **Domaine `horlynat.com`** : auto-renouvellement actif chez le
  registrar, coordonnées de contact à jour (email de rappel d'expiration
  souvent le seul filet avant coupure totale du site).
- [ ] **Facturation** VPS / Cloudflare (plan payant éventuel) / bucket
  S3-compatible : moyen de paiement à jour — une suspension pour impayé a le
  même effet qu'une panne.

## Ce que ce runbook ne couvre pas

Perte de données (VPS détruit, base corrompue) : voir
`docs/incident-data-loss.md`, qui a son propre runbook (sauvegarde 3-2-1,
reconstruction complète) — distinct de celui-ci, qui ne couvre que l'accès,
pas les données elles-mêmes.
