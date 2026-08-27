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

~~Point de défaillance unique~~ : corrigé le 27/08/2026. Une deuxième paire de
clés (`hmm_admin_backup`, ed25519, générée dédiée — pas une clé déjà utilisée
ailleurs) a été installée sur le VPS, en plus de `id_ed25519` :

```
ubuntu@VPS:~/.ssh/authorized_keys   → id_ed25519 (perso) + hmm_admin_backup
deploy@VPS:~/.ssh/authorized_keys   → github-actions (CI) + hmm_admin_backup
```

Vérifié en conditions réelles (connexion effective aux deux comptes avec
`hmm_admin_backup` uniquement, `id_ed25519` absent du trousseau ssh-agent au
moment du test). Le fichier privé `hmm_admin_backup` réside pour l'instant sur
la même machine perso que `id_ed25519` — ce n'est qu'une **étape
intermédiaire** : tant qu'une copie n'a pas été déplacée vers un support
distinct (gestionnaire de mots de passe, second appareil, coffre physique),
la redondance n'est qu'à moitié réelle (les deux clés meurent avec le même
disque). Ce déplacement reste une action humaine, cf. §6.

Pour ajouter un troisième porteur de confiance plus tard :

```bash
# Sur le VPS, ajouter une clé de confiance supplémentaire :
echo "ssh-ed25519 AAAA... nouveau-porteur@..." >> ~deploy/.ssh/authorized_keys
echo "ssh-ed25519 AAAA... nouveau-porteur@..." >> ~ubuntu/.ssh/authorized_keys
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

Aucun de ces points ne se corrige par un commit — ni par moi (identifiants,
téléphone/app d'authentification et moyens de paiement du compte
appartiennent exclusivement à l'humain qui les détient). À vérifier/poser
soi-même, une fois, pas à chaque incident :

- [ ] **Clé `age` de secours** (`~/backup-key.txt` sur cette machine, seule
  copie existante — déchiffre toutes les sauvegardes DB) : en mettre une
  deuxième copie dans un gestionnaire de mots de passe (1Password/Bitwarden…)
  ou un coffre physique. Le contenu du fichier ne doit jamais transiter par
  un canal non chiffré (mail, Slack…) — copier/coller directement dans le
  champ du gestionnaire, en local.
- [ ] **Clé privée `hmm_admin_backup`** (`~/.ssh/hmm_admin_backup` sur cette
  machine, cf. §4) : même chose — une deuxième copie hors de cette machine
  avant qu'elle ne soit utile en cas de perte de la machine elle-même.
- [ ] **Compte Cloudflare** : 2FA + codes de secours sauvegardés hors du
  seul appareil habituel (perdre l'accès à ce compte bloque §5 ci-dessus).
  → dash.cloudflare.com → icône profil → *My Profile* → *Authentication*.
- [ ] **Compte GitHub** (`horlynat`) : 2FA + codes de secours — sans lui,
  impossible de déclencher le rollback du §2.
  → github.com/settings/security → *Two-factor authentication*
  (télécharger/imprimer les *recovery codes* à cette étape, pas après).
- [ ] **Domaine `horlynat.com`** : auto-renouvellement actif chez le
  registrar, coordonnées de contact à jour (email de rappel d'expiration
  souvent le seul filet avant coupure totale du site).
- [ ] **Facturation** VPS / Cloudflare (plan payant éventuel) / bucket
  S3-compatible : moyen de paiement à jour — une suspension pour impayé a le
  même effet qu'une panne.

Aucune de ces cases ne peut être cochée par un assistant : la 2FA exige
l'app d'authentification sur le téléphone du titulaire du compte, et la
facturation un moyen de paiement personnel. Les deux premières (clés à
dupliquer) sont de simples copier/coller locaux, à faire une fois, quand
vous avez cinq minutes.

## Ce que ce runbook ne couvre pas

Perte de données (VPS détruit, base corrompue) : voir
`docs/incident-data-loss.md`, qui a son propre runbook (sauvegarde 3-2-1,
reconstruction complète) — distinct de celui-ci, qui ne couvre que l'accès,
pas les données elles-mêmes.
