# Horlynat — Portfolio & Admin Platform

Application Symfony exposant une API REST (API Platform) pour un site vitrine, avec un back-office d'administration (Twig + Stimulus + Vite/Tailwind) pour gérer projets, articles, compétences, formations, expériences, devis et messages de contact.

## Stack technique

| Domaine       | Techno                                                              |
|---------------|----------------------------------------------------------------------|
| Framework     | Symfony 8.1 (PHP ≥ 8.4)                                              |
| API           | API Platform 4 (Doctrine ORM)                                        |
| Base de données | PostgreSQL 16 (via Docker Compose)                                 |
| Auth          | `lexik/jwt-authentication-bundle` + authenticator custom + Voters     |
| Front admin   | Twig, Symfony UX (Stimulus, Turbo, Live Component), Tailwind CSS v4   |
| Build assets  | Vite (`pentatrion/vite-bundle`)                                       |
| Mail          | Symfony Mailer                                                        |
| Tests         | PHPUnit, PHPStan, PHP-CS-Fixer                                        |

## Structure du projet (`backend/`)

```
backend/
├── src/
│   ├── ApiResource/     # Ressources API Platform (Article, Project, Skill, Course, Experience,
│   │                    #   Tag, Testimonial, QuoteRequest, ContactMessage, LoginHistory, User…)
│   ├── Controller/
│   │   ├── Admin/       # Back-office (Dashboard, Article, Course, Experience, Project,
│   │   │                #   SkillCategory, Skill, Tag, ProjectHistory)
│   │   ├── SecurityController.php / RegistrationController.php
│   │   ├── ProfileController.php / UserController.php
│   ├── Entity/          # Article, Project, ProjectExpense, ProjectHistory, Course, Experience,
│   │                    #   Skill, SkillCategory, Tag, Testimonial, QuoteRequest, ContactMessage,
│   │                    #   LoginHistory, User (+ Traits: Slug, CreatedAt, UpdatedAt)
│   ├── Repository/      # Requêtes Doctrine personnalisées (1 par entité)
│   ├── Security/        # SecurityAuthenticator, EmailVerifier, ProjectVoter
│   └── Service/         # EmailManager, MediaUploader, GeolocationService, JWTService,
│                        #   ProfileCompletionService, ProjectStatisticsService
├── templates/           # Vues Twig du back-office (admin/…)
├── assets/              # JS/CSS source (Stimulus controllers, styles Tailwind) compilés par Vite
├── migrations/          # Migrations Doctrine
├── tests/               # Tests unitaires et fonctionnels
├── compose.yaml         # Service PostgreSQL 16 pour le développement
└── composer.json / package.json
```

> Le frontend public (vitrine Next.js) consomme l'API exposée ici mais vit dans un dépôt séparé — il n'est pas inclus dans ce repo.

## Modèle de données (aperçu)

| Entité          | Champs clés                                              | Relations principales                          |
|-----------------|-----------------------------------------------------------|-------------------------------------------------|
| `User`          | email, password, roles                                    | 1—N `Project` (owner), `QuoteRequest`            |
| `Project`       | title, slug, description, link, status, budget, spent      | N—N `Skill` · N—N `collaborators` (User) · 1—N `Media`, `ProjectExpense`, `ProjectHistory` |
| `ProjectExpense`| amount, description, date                                  | N—1 `Project`                                    |
| `ProjectHistory`| action, details, createdAt                                  | N—1 `Project`, N—1 `User`                        |
| `Article`       | title, slug, content, publishedAt                          | N—N `Tag` · 1—N `Media`                          |
| `Course`        | title, institution, startDate, endDate, description         | N—1 `User`                                       |
| `Experience`    | company, role, startDate, endDate, description               | N—1 `User`                                       |
| `SkillCategory` | name                                                        | 1—N `Skill`                                      |
| `Skill`         | name, level                                                 | N—1 `SkillCategory` · N—N `Project`              |
| `Tag`           | name                                                        | N—N `Article`                                    |
| `Testimonial`   | author, content, rating, publishedAt                        | 1—N `Media`                                      |
| `Media`         | filePath, altText, type                                     | N—1 `Article` / `Project` / `Testimonial`        |
| `QuoteRequest`  | name, email, phone, message, status                         | N—1 `User` (si authentifié)                      |
| `ContactMessage`| name, email, subject, message, createdAt                     | —                                                 |
| `LoginHistory`  | ip, userAgent, createdAt                                     | N—1 `User`                                       |

Sécurité des projets gérée par `ProjectVoter` : `VIEW` (owner + collaborateurs), `EDIT`/`DELETE` (owner uniquement).

## Développement local

```bash
# Prérequis
php -v            # 8.4+
composer -V
node -v && npm -v # 20+
docker -v         # pour PostgreSQL

cd backend

# Base de données (PostgreSQL via Docker)
docker compose up -d database

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

## Déploiement en production

### 1. Prérequis serveur (Ubuntu 22.04 LTS)

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.4 + extensions
sudo apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common \
  php8.4-pgsql php8.4-xml php8.4-mbstring php8.4-curl \
  php8.4-zip php8.4-intl php8.4-gd php8.4-bcmath

sudo apt install -y nginx supervisor postgresql-16

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Déploiement du code

```bash
git clone <repo-url> /var/www/backend && cd /var/www/backend

sudo chown -R www-data:www-data /var/www/backend
sudo chmod -R 755 /var/www/backend
sudo chmod -R 775 var/ public/uploads/

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env .env.local
nano .env.local   # renseigner les vraies valeurs (voir ci-dessous)
```

### 3. `.env.local` production

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<secret_unique_et_aleatoire>

DATABASE_URL="postgresql://prod_user:prod_password@127.0.0.1:5432/prod_db?serverVersion=16&charset=utf8"

MAILER_DSN=smtp://user:password@smtp.votre-domaine.com:587?encryption=tls
MESSENGER_TRANSPORT_DSN=doctrine://default

JWT_SECRET_KEY='%kernel.project_dir%/config/jwt/private.pem'
JWT_PUBLIC_KEY='%kernel.project_dir%/config/jwt/public.pem'
JWT_PASSPHRASE=<passphrase>

CORS_ALLOW_ORIGIN='^https?://(votre-domaine\.com)(:[0-9]+)?$'
```

### 4. Mise en production Symfony

```bash
php bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
composer dump-autoload --optimize --no-dev
```

### 5. Nginx

```nginx
server {
    listen 80;
    server_name api.votre-domaine.com;
    root /var/www/backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/backend /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api.votre-domaine.com
```

### 6. Supervisor (Messenger worker)

```ini
# /etc/supervisor/conf.d/messenger-worker.conf
[program:messenger-worker]
command=php /var/www/backend/bin/console messenger:consume async --time-limit=3600
directory=/var/www/backend
user=www-data
numprocs=2
autostart=true
autorestart=true
stdout_logfile=/var/www/backend/var/log/messenger.log
stderr_logfile=/var/www/backend/var/log/messenger-error.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start messenger-worker:*
```

## CI/CD (GitHub Actions)

```yaml
# .github/workflows/backend.yml
name: Backend CI/CD

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.4"

      - run: composer install --no-dev --optimize-autoloader

      - uses: actions/setup-node@v4
        with:
          node-version: "20"

      - run: npm ci && npm run build

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/backend
            git pull origin main
            composer install --no-dev --optimize-autoloader
            npm ci && npm run build
            php bin/console doctrine:migrations:migrate --no-interaction --env=prod
            php bin/console cache:clear --env=prod
            php bin/console cache:warmup --env=prod
            sudo supervisorctl restart messenger-worker:*
```

## Checklist avant mise en ligne

```
[ ] APP_ENV=prod, APP_DEBUG=0
[ ] APP_SECRET / JWT_PASSPHRASE régénérés et uniques
[ ] DATABASE_URL pointant vers la BDD PostgreSQL de production
[ ] MAILER_DSN configuré (vrai SMTP)
[ ] CORS_ALLOW_ORIGIN limité au(x) domaine(s) du frontend
[ ] php bin/console doctrine:schema:validate → OK
[ ] php bin/console cache:warmup --env=prod → OK
[ ] npm run build → public/build/ généré
[ ] Nginx configuré, testé (nginx -t) et rechargé
[ ] SSL Let's Encrypt actif
[ ] Supervisor messenger-worker actif (supervisorctl status)
[ ] var/log/prod.log → aucune erreur critique
```

## Maintenance courante

```bash
# Symfony
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
sudo supervisorctl restart messenger-worker:*
tail -f var/log/prod.log

# Nginx
sudo nginx -t && sudo systemctl reload nginx

# Système
sudo journalctl -u nginx -f
sudo journalctl -u php8.4-fpm -f
```
