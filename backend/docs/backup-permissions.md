# Droits des sauvegardes locales (`var/backups` → `/opt/hmm/infra/backups`)

Audit 2026-11-20, P1 #15. Les dumps `backup_*.sql` sont un export complet de la
base **en clair**. `App\Service\DatabaseBackupService` les crée désormais :

- dossier créé (s'il n'existe pas) en `0700` ;
- `mysqldump` / `age` lancés sous `umask 0077` → fichiers `0600`, propriétaire
  `www-data` du conteneur (uid/gid **82**, image Alpine) ;
- **sans** `chmod` explicite après coup (voir « Pourquoi pas de chmod »).

## Conséquence pour `infra/scripts/pull-backups.sh`

Le script lit le dossier par SSH en tant que `ubuntu` : avec des fichiers `0600`
appartenant à l'uid 82, il ne pourrait plus rien lire. L'accès se donne **par
ACL POSIX côté hôte**, à poser une fois sur le VPS (paquet `acl`) :

```sh
cd /opt/hmm/infra
sudo chown 82:82 backups
# 1) droits restrictifs D'ABORD (un chmod ultérieur réécrirait le masque ACL)
sudo chmod 0700 backups
sudo chmod 0600 backups/backup_*.sql          # dumps déjà présents (créés en 0644)
# 2) puis l'ACL : traversée + lecture pour ubuntu, rien pour les autres
sudo setfacl -m u:ubuntu:rx backups
sudo setfacl -d -m u:ubuntu:r,g::-,o::- backups   # ACL par défaut des futurs dumps
sudo setfacl -m u:ubuntu:r backups/backup_*.sql   # dumps déjà présents
# Vérification : getfacl doit montrer user:ubuntu:r-- SANS « #effective:--- »
getfacl backups/$(ls backups | tail -1)
```

Séquence vérifiée en local (umask 0077 + ACL par défaut → `user:ubuntu:r--`,
`mask::r--`, `group::---`, `other::---`).

## Pourquoi pas de `chmod 0600` dans le code

Sous ACL, les bits « groupe » d'un fichier sont le **masque** ACL. Un fichier
créé (ou `chmod`é) en `0600` a un masque vide : l'entrée `user:ubuntu:r` devient
`#effective:---` et `pull-backups.sh` échoue. À l'inverse, un fichier créé avec
le mode demandé par `mysqldump` (0660) dans un dossier à ACL par défaut ignore
l'umask et reçoit exactement l'ACL par défaut ci-dessus. Sans ACL (dev, CI),
l'umask 0077 donne bien `0600`.

## Hors code (lot infra)

- `infra/scripts/pull-backups.sh` / `infra/README.md` : documenter ce prérequis
  ACL et l'échec attendu (`Permission denied`) s'il manque.
- `infra/.gitignore` : ignorer `backups/` et `*.sql`.
