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

## Ce que ce runbook ne couvre pas

Panne de base de données ou perte du VPS lui-même : voir `infra/README.md`
§8 (sauvegardes chiffrées) — et **tester une restauration au moins une fois**
avant d'en dépendre, comme indiqué là-bas. Un plan de secours jamais exécuté
en conditions réelles n'est qu'une hypothèse.
