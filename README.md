# hmm — horlynat.com

Monorepo portfolio + back-office : API/back-office Symfony (`backend/`),
vitrine publique Next.js (`frontend/`), infrastructure de déploiement
(`infra/`).

**→ Architecture complète, modèle de données, sécurité, CI/CD et plan de
continuité : [`ARCHITECTURE.md`](ARCHITECTURE.md).**
**→ Procédure d'exécution infra pas-à-pas (VPS, secrets, déploiement) :
[`infra/README.md`](infra/README.md).**

Ce fichier ne couvre que la mise en route du backend en local.

## Stack technique (backend)

| Domaine       | Techno                                                              |
|---------------|----------------------------------------------------------------------|
| Framework     | Symfony 8.1 (PHP ≥ 8.4)                                              |
| API           | API Platform 4 (Doctrine ORM)                                        |
| Base de données | MySQL 8 (production et développement — cf. note ci-dessous)        |
| Auth          | JWT (`lexik/jwt-authentication-bundle`) + session + 2FA TOTP (Voters)|
| Front admin   | Twig, Symfony UX (Stimulus, Turbo, Live Component), Tailwind CSS v4   |
| Build assets  | Vite (`pentatrion/vite-bundle`)                                       |
| Mail          | Symfony Mailer                                                        |
| Tests         | PHPUnit, PHPStan, PHP-CS-Fixer                                        |

> Le frontend public (vitrine Next.js) consomme l'API exposée ici mais vit
> dans `frontend/` de ce même monorepo (branche `frontend`), pas un dépôt
> séparé.

## ⚠️ Note base de données locale

`backend/compose.yaml` (recette par défaut Symfony Flex) provisionne encore
un conteneur **PostgreSQL**, mais les migrations committées sont écrites en
dialecte **MySQL** (`AUTO_INCREMENT` — échouent sur Postgres) et `.env`
pointe par défaut vers MySQL. Utiliser une instance MySQL 8 locale (Docker ou
autre) plutôt que `docker compose up -d database` tel quel, ou adapter le
service avant de démarrer. La production tourne exclusivement en MySQL 8
(cf. `ARCHITECTURE.md` §3).

## Développement local

```bash
# Prérequis
php -v            # 8.4+
composer -V
node -v && npm -v # 20+
docker -v         # pour MySQL

cd backend

# Base de données (MySQL 8 — cf. note ci-dessus, pas compose.yaml tel quel)
docker run -d --name hmm-mysql -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=database_portofolio -p 3306:3306 mysql:8.0

# Dépendances
composer install
npm install

# Variables d'environnement
cp .env .env.local
# éditer DATABASE_URL, APP_SECRET, MAILER_DSN, JWT_* dans .env.local

# Base + migrations
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate

# Lancer l'app (2 terminaux)
symfony server:start          # ou: php -S localhost:8000 -t public/
npm run dev                   # Vite (HMR)
```

### Commandes utiles en dev

```bash
php bin/console cache:clear
php bin/console make:migration
php bin/console debug:router
php bin/console debug:container
php bin/console messenger:consume async -vv
```

## Tests & qualité

```bash
php bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
npm audit
```

## Déploiement

La production ne se déploie **jamais** en poussant du code à la main sur un
serveur — uniquement via merge sur `main` (déclenche
`.github/workflows/deploy.yml`, avec validation manuelle obligatoire) ou
rollback manuel (`workflow_dispatch`). Détail complet : `ARCHITECTURE.md`
§8 et `infra/README.md`.

## Premier compte admin

Aucun mécanisme de seed ne crée de compte admin — le tout premier doit être
inséré directement en base (`security:hash-password` + `INSERT` SQL). Voir
`infra/README.md` §6.5 pour la procédure exacte (VPS) ; en local, la même
logique s'applique via la base de dev.
