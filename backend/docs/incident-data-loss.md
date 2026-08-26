# Perte de données — restaurer la base

Distinguer, comme dans `incident-auth.md`, deux scénarios très différents :

| Symptôme | Remède |
|---|---|
| Le VPS et l'app tournent, mais la base a été corrompue par une mauvaise manip (restauration ratée, `UPDATE` sans `WHERE`...) | §1 — restauration normale |
| Le VPS lui-même a disparu (panne matérielle, résiliation, ransomware...) | §2 — reconstruction complète |

## 0. D'où viennent les sauvegardes disponibles

Trois copies indépendantes (règle 3-2-1, cf. `infra/README.md` §8) :

1. **Locale**, en clair, sur le VPS : `infra/backups/backup_YYYYMMDD_HHMMSS.sql`.
2. **Cloud**, chiffrée (age) : bucket S3-compatible, clé `database/backup_YYYYMMDD_HHMMSS.sql.gz.age`.
3. **Machine perso**, en clair : `~/hmm-backups/backup_YYYYMMDD_HHMMSS.sql` (après `scripts/pull-backups.sh`).

Les copies 2 et 3 existent précisément pour survivre à la perte du VPS — la
copie 1 seule ne protège de rien dans ce cas.

## 1. Restauration normale (VPS intact)

Via `/admin/backup` (réservé `ROLE_SUPER_ADMIN`) : sélectionner une
sauvegarde locale, bouton "Restaurer". Action irréversible — écrase
l'intégralité de la base courante, alerte envoyée (succès et échec).

Sans accès à `/admin` (c'est justement le scénario où `/admin` ne
répond plus, cf. `incident-auth.md`), restauration en CLI directement :

```bash
docker compose -f docker-compose.prod.yml exec -u www-data backend \
  php bin/console app:backup:restore backup_YYYYMMDD_HHMMSS.sql
```

## 2. Reconstruction complète (VPS perdu)

### 2.1. Nouveau VPS

Reprendre `infra/README.md` depuis §1 (durcissement) jusqu'à §7
(vérifications) sur un VPS neuf — migrations comprises, mais **sans**
`app:seed-content` (§6, étape 3) : le contenu réel arrive par la
restauration ci-dessous, pas par le seed de démo.

### 2.2. Récupérer la sauvegarde la plus récente

Depuis le cloud (nécessite `age` et la clé **privée**, gardée hors du VPS
disparu — sur ta machine ou un gestionnaire de secrets) :

```bash
# Télécharger l'objet le plus récent du préfixe database/ (interface web du
# provider S3-compatible, ou son CLI/rclone) vers backup.sql.gz.age, puis :
age -d -i backup-key.txt backup.sql.gz.age | gunzip > backup.sql
```

Depuis la machine perso (déjà en clair, pas de déchiffrement) :

```bash
cp ~/hmm-backups/backup_YYYYMMDD_HHMMSS.sql backup.sql
```

### 2.3. Charger dans le nouveau VPS

```bash
DBPASS=$(sudo cat infra/secrets/database_password)
docker compose -f infra/docker-compose.prod.yml exec -T database \
  mysql -u app -p"$DBPASS" app < backup.sql
```

### 2.4. Vérifier

```bash
curl -I https://dark.horlynat.com   # 200/302 attendu, pas 500
```

Se connecter avec un compte admin connu de la sauvegarde restaurée. Si aucun
compte n'est utilisable (sauvegarde plus ancienne que le dernier
`app:admin:recover`, par ex.), repasser par `incident-auth.md` §1.

## Point de vigilance unique à ce runbook

La clé **privée** age (`backup-key.txt`) ne doit jamais avoir vécu sur le
VPS — c'est elle qui rend le §2.2 possible après sa perte. Si elle n'existe
qu'à un seul endroit (ta machine), c'est un point de défaillance unique de
plus, comme `ADMIN_PUBKEY` dans `incident-auth.md` §4 : envisager une
deuxième copie (gestionnaire de mots de passe, coffre physique) plutôt
qu'une seule.
