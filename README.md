horlynat/ 

├── backend/                # Symfony API + EasyAdmin 

│   ├── config/             # Config (CORS, JWT, Doctrine, EasyAdmin) 

│   ├── src/ 

│   │   ├── Controller/     # Contrôleurs REST (ArticleController, ProjectController…) 

│   │   ├── Entity/         # Entités Doctrine (Article, Project, Course, SkillCategory, AboutSection, ContactRequest) 

│   │   ├── Repository/     # Requêtes personnalisées 

│   │   ├── Security/       # Authentification JWT / rôles admin 

│   │   ├── Service/        # Logique métier (upload, mail, devis, rendez-vous) 

│   │   └── Serializer/     # Format JSON pour Next.js 

│   ├── public/             # Point d’entrée (index.php) 

│   ├── migrations/         # Migrations Doctrine 

│   ├── tests/              # Tests unitaires et fonctionnels 

│   ├── .env                # Variables d’environnement (DB, JWT_SECRET, etc.) 

│   └── composer.json       # Dépendances PHP 

│ 

├── frontend/               # Next.js (vitrine publique) 

│   ├── app/ 

│   │   ├── layout.tsx      # Layout global (Navbar, Footer) 

│   │   ├── page.tsx        # Accueil 

│   │   ├── about/page.tsx  # À propos (vision, parcours, compétences) 

│   │   ├── projects/ 

│   │   │   ├── page.tsx    # Liste des projets 

│   │   │   └── [slug]/page.tsx  # Détail projet 

│   │   ├── articles/ 

│   │   │   ├── page.tsx    # Liste des articles 

│   │   │   └── [slug]/page.tsx  # Détail article 

│   │   ├── courses/page.tsx      # Formations & certifications 

│   │   ├── skills/page.tsx       # Compétences 

│   │   ├── experiences/page.tsx  # Expériences professionnelles 

│   │   ├── contact/page.tsx      # Formulaire de contact (envoie vers API Symfony) 

│   │   └── quotes/page.tsx       # Demande de devis / rendez-vous 

│   ├── components/         # Composants UI (Cards, Modals, Timeline…) 

│   ├── lib/api.ts          # Fonctions fetch vers Symfony API 

│   ├── styles/             # Tailwind / CSS 

│   ├── public/             # Images, favicon, etc. 

│   ├── next.config.ts      # Config Next.js 

│   ├── package.json        # Dépendances JS 

│   └── .env.local          # URL API Symfony, clés publiques 

│ 

└── docs/                   # Documentation technique et schémas 

 

 

// Entités principales et relations 

User 

Champs : id, email, password, roles 

Relation : peut être lié aux QuoteRequest ou ContactMessage (OneToMany) 

Article 

Champs : id, title, slug, content, publishedAt 

Relations : 

ManyToMany avec Tag 

OneToMany avec Media (images associées) 

Project 

Champs : id, title, slug, description, link 

Relations : 

ManyToMany avec Skill (technologies utilisées) 

OneToMany avec Media 

Experience 

Champs : id, company, role, startDate, endDate, description 

Relation : liée à User (OneToMany) 

Course 

Champs : id, title, institution, startDate, endDate, description 

Relation : peut être lié à User (OneToMany) 

SkillCategory 

Champs : id, name 

Relation : OneToMany avec Skill 

Skill 

Champs : id, name, level 

Relation : ManyToOne vers SkillCategory 

AboutSection 

Champs : id, title, content, image 

Relation : simple, pas de dépendance 

QuoteRequest 

Champs : id, name, email, phone, message, status 

Relation : ManyToOne vers User (si authentifié) 

ContactMessage 

Champs : id, name, email, subject, message, createdAt 

Relation : simple, pas de dépendance 

Testimonial 

Champs : id, author, content, rating, publishedAt 

Relation : OneToMany avec Media (photo auteur) 

Media 

Champs : id, filePath, altText, type 

Relation : ManyToOne vers Article, Project, Testimonial 

Tag 

Champs : id, name 

Relation : ManyToMany avec Article 

Relations clés (UML simplifié) 

Article ↔ Tag (ManyToMany) 

Article ↔ Media (OneToMany) 

Project ↔ Skill (ManyToMany) 

Project ↔ Media (OneToMany) 

SkillCategory ↔ Skill (OneToMany) 

User ↔ Experience, Course, QuoteRequest (OneToMany) 

Testimonial ↔ Media (OneToMany) 

 

 # Checklist complète — Symfony + Tailwind + Vite → Production
# Frontend : Next.js + TurboPack

---

## 📦 PARTIE 1 — BACKEND SYMFONY + VITE

---

### 1.1 — Prérequis système

```bash
# PHP 8.2+
php -v

# Composer
composer -V

# Node.js 20+ et npm 10+
node -v
npm -v

# Symfony CLI (optionnel mais recommandé)
curl -sS https://get.symfony.com/cli/installer | bash
symfony version
```

---

### 1.2 — Création du projet Symfony

```bash
# Nouveau projet Symfony
symfony new backend --version="7.*" --webapp
cd backend

# OU sur un projet existant
composer install
```

---

### 1.3 — Configuration de la base de données

```bash
# Copier et configurer .env.local
cp .env .env.local

# Éditer DATABASE_URL dans .env.local
# DATABASE_URL="mysql://user:password@127.0.0.1:3306/db_name?serverVersion=8.0.32"

# Créer la base
php bin/console doctrine:database:create

# Lancer les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Vérifier le schéma
php bin/console doctrine:schema:validate
```

---

### 1.4 — Installation Vite + Tailwind CSS v4

```bash
# Bundle Vite pour Symfony
composer require pentatrion/vite-bundle

# Packages npm — Vite + Tailwind v4 + PostCSS
npm install --save-dev \
  vite \
  vite-plugin-symfony \
  tailwindcss \
  @tailwindcss/postcss \
  postcss \
  sass \
  sass-loader

# Vérification
npm list vite tailwindcss
```

---

### 1.5 — Fichiers de configuration à créer

```bash
# vite.config.js — à la racine du projet
cat > vite.config.js << 'EOF'
import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";

export default defineConfig({
    plugins: [
        symfonyPlugin({
            stimulus: "./assets/controllers.json",
        }),
    ],
    build: {
        rollupOptions: {
            input: {
                app:       "./assets/app.js",
                login:     "./assets/js/login.js",
                register:  "./assets/js/register.js",
                dashboard: "./assets/js/dashboard.js",
                profile:   "./assets/js/profile.js",
            },
        },
        manifest: true,
        outDir:   "public/build",
        emptyOutDir: true,
    },
});
EOF

# postcss.config.cjs
cat > postcss.config.cjs << 'EOF'
module.exports = {
    plugins: {
        "@tailwindcss/postcss": {},
    },
};
EOF

# assets/styles/app.css
cat > assets/styles/app.css << 'EOF'
@import "tailwindcss";
EOF
```

---

### 1.6 — Mise à jour package.json scripts

```bash
npm pkg set scripts.dev="vite"
npm pkg set scripts.watch="vite"
npm pkg set scripts.build="vite build"
npm pkg set scripts.preview="vite preview"
```

---

### 1.7 — Mise à jour base.html.twig

```twig
{# Remplacer encore_entry_link_tags par vite_entry_link_tags #}
{{ vite_entry_link_tags('app') }}
{{ vite_entry_script_tags('app') }}
```

---

### 1.8 — Variables d'environnement (.env.local)

```bash
cat >> .env.local << 'EOF'
APP_ENV=dev
APP_SECRET=votre_secret_ici

DATABASE_URL="mysql://user:password@127.0.0.1:3306/db_name?serverVersion=8.0.32&charset=utf8mb4"

MAILER_DSN=smtp://localhost:1025
MESSENGER_TRANSPORT_DSN=sync://

APP_DEFAULT_SENDER=no-reply@votre-domaine.com
APP_JWTSECRET=votre_jwt_secret
EOF
```

---

### 1.9 — Test du build en développement

```bash
# Terminal 1 — Symfony
symfony server:start
# ou
php -S localhost:8000 -t public/

# Terminal 2 — Vite (HMR)
npm run dev

# Vérification des assets compilés
ls public/build/
```

---

### 1.10 — Commandes Symfony utiles en dev

```bash
# Cache
php bin/console cache:clear
php bin/console cache:warmup

# Migrations
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Debug
php bin/console debug:router
php bin/console debug:container
php bin/console messenger:consume async -vv

# Fixtures (si installées)
php bin/console doctrine:fixtures:load --no-interaction
```

---

### 1.11 — Vérifications avant production

```bash
# Audit de sécurité npm
npm audit

# Vérification Symfony
php bin/console security:check

# Vérification du schéma
php bin/console doctrine:schema:validate

# Test du build production
npm run build
ls -la public/build/
```

---

## 🚀 PARTIE 2 — MISE EN PRODUCTION BACKEND

---

### 2.1 — Serveur (Ubuntu 22.04 LTS recommandé)

```bash
# Mise à jour système
sudo apt update && sudo apt upgrade -y

# PHP 8.3 + extensions
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
  php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl \
  php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath

# Nginx
sudo apt install -y nginx

# MySQL 8
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Supervisor (pour Messenger)
sudo apt install -y supervisor
```

---

### 2.2 — Déploiement du code

```bash
# Cloner le projet
git clone https://github.com/votre-repo/backend.git /var/www/backend
cd /var/www/backend

# Permissions
sudo chown -R www-data:www-data /var/www/backend
sudo chmod -R 755 /var/www/backend
sudo chmod -R 777 var/ public/uploads/

# Dépendances PHP (sans dev)
composer install --no-dev --optimize-autoloader

# Dépendances npm et build
npm ci
npm run build

# Variables d'environnement production
cp .env .env.local
# Éditer .env.local avec les vraies valeurs
nano .env.local
```

---

### 2.3 — Configuration production .env.local

```bash
cat > .env.local << 'EOF'
APP_ENV=prod
APP_SECRET=CHANGEZ_CE_SECRET_TRES_LONG_ET_ALEATOIRE
APP_DEBUG=0

DATABASE_URL="mysql://prod_user:prod_password@127.0.0.1:3306/prod_db?serverVersion=8.0.32"

MAILER_DSN=smtp://user:password@smtp.votre-domaine.com:587?encryption=tls
MESSENGER_TRANSPORT_DSN=doctrine://default

APP_DEFAULT_SENDER=no-reply@votre-domaine.com
APP_JWTSECRET=CHANGEZ_CE_JWT_SECRET

CORS_ALLOW_ORIGIN='^https?://(votre-domaine\.com)(:[0-9]+)?$'
EOF
```

---

### 2.4 — Symfony en production

```bash
# Compiler les secrets
php bin/console secrets:encrypt-from-local -e prod

# Optimisation du container
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Migrations
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Dump autoloader optimisé
composer dump-autoload --optimize --no-dev
```

---

### 2.5 — Configuration Nginx

```bash
sudo nano /etc/nginx/sites-available/backend
```

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
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Assets Vite avec cache long
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
# Activer le site
sudo ln -s /etc/nginx/sites-available/backend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

### 2.6 — SSL avec Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.votre-domaine.com
sudo systemctl reload nginx
```

---

### 2.7 — Supervisor pour Messenger

```bash
sudo nano /etc/supervisor/conf.d/messenger-worker.conf
```

```ini
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
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start messenger-worker:*
sudo supervisorctl status
```

---

## ⚡ PARTIE 3 — FRONTEND NEXT.JS + TURBOPACK

---

### 3.1 — Création du projet Next.js

```bash
# Créer le projet avec Turbopack activé
npx create-next-app@latest frontend \
  --typescript \
  --tailwind \
  --eslint \
  --app \
  --src-dir \
  --import-alias "@/*"

cd frontend
```

---

### 3.2 — Installation des dépendances

```bash
# Dépendances principales
npm install \
  axios \
  @tanstack/react-query \
  zustand \
  react-hook-form \
  zod \
  @hookform/resolvers

# Dépendances dev
npm install --save-dev \
  @types/node \
  typescript \
  eslint \
  prettier \
  @typescript-eslint/eslint-plugin
```

---

### 3.3 — Configuration next.config.ts avec Turbopack

```bash
cat > next.config.ts << 'EOF'
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
    // ✅ Turbopack activé (stable depuis Next.js 15)
    experimental: {
        turbo: {
            rules: {
                "*.svg": {
                    loaders: ["@svgr/webpack"],
                    as: "*.js",
                },
            },
        },
    },

    // API Symfony
    env: {
        NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
    },

    // Proxy vers le backend Symfony
    async rewrites() {
        return [
            {
                source: "/api/:path*",
                destination: `${process.env.NEXT_PUBLIC_API_URL}/api/:path*`,
            },
        ];
    },

    // Images
    images: {
        domains: ["api.votre-domaine.com", "localhost"],
    },
};

export default nextConfig;
EOF
```

---

### 3.4 — Variables d'environnement Next.js

```bash
# .env.local
cat > .env.local << 'EOF'
# URL du backend Symfony
NEXT_PUBLIC_API_URL=http://localhost:8000

# En production
# NEXT_PUBLIC_API_URL=https://api.votre-domaine.com
EOF
```

---

### 3.5 — Scripts package.json Next.js

```bash
# Développement avec Turbopack
npm pkg set scripts.dev="next dev --turbopack"
npm pkg set scripts.build="next build"
npm pkg set scripts.start="next start"
npm pkg set scripts.lint="next lint"
```

---

### 3.6 — Test en développement

```bash
# Démarrer avec Turbopack
npm run dev

# Vérification
# → http://localhost:3000
```

---

### 3.7 — Configuration Tailwind dans Next.js

```bash
# tailwind.config.ts (généré automatiquement par create-next-app)
# Vérifier le content
cat tailwind.config.ts
```

```ts
import type { Config } from "tailwindcss";

export default {
    content: [
        "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
        "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
        "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
} satisfies Config;
```

---

## 🌐 PARTIE 4 — MISE EN PRODUCTION FRONTEND

---

### 4.1 — Build de production

```bash
cd frontend

# Variables d'environnement production
cat > .env.production << 'EOF'
NEXT_PUBLIC_API_URL=https://api.votre-domaine.com
EOF

# Build
npm run build

# Test local du build de production
npm run start
```

---

### 4.2 — Déploiement sur le serveur

```bash
# Sur le serveur
git clone https://github.com/votre-repo/frontend.git /var/www/frontend
cd /var/www/frontend

npm ci
npm run build
```

---

### 4.3 — PM2 pour Next.js (process manager)

```bash
# Installer PM2 globalement
npm install -g pm2

# Démarrer Next.js avec PM2
pm2 start npm --name "frontend" -- start

# Démarrage automatique au boot
pm2 startup
pm2 save

# Monitoring
pm2 status
pm2 logs frontend
```

---

### 4.4 — Nginx pour Next.js

```bash
sudo nano /etc/nginx/sites-available/frontend
```

```nginx
server {
    listen 80;
    server_name votre-domaine.com www.votre-domaine.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # Assets Next.js avec cache
    location /_next/static/ {
        proxy_pass http://localhost:3000;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/frontend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# SSL
sudo certbot --nginx -d votre-domaine.com -d www.votre-domaine.com
```

---

## 🔄 PARTIE 5 — CI/CD (GitHub Actions)

---

### 5.1 — Workflow backend Symfony

```bash
mkdir -p .github/workflows
cat > .github/workflows/backend.yml << 'EOF'
name: Backend CI/CD

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Build assets
        run: |
          npm ci
          npm run build

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
EOF
```

---

### 5.2 — Workflow frontend Next.js

```bash
cat > .github/workflows/frontend.yml << 'EOF'
name: Frontend CI/CD

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Install & Build
        env:
          NEXT_PUBLIC_API_URL: ${{ secrets.API_URL }}
        run: |
          npm ci
          npm run build

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/frontend
            git pull origin main
            npm ci
            npm run build
            pm2 restart frontend
EOF
```

---

## ✅ PARTIE 6 — CHECKLIST FINALE AVANT MISE EN LIGNE

```bash
# ── BACKEND ──────────────────────────────────────────────────────────────────
[ ] APP_ENV=prod dans .env.local
[ ] APP_DEBUG=0
[ ] APP_SECRET modifié (valeur unique et sécurisée)
[ ] DATABASE_URL pointant vers la BDD de production
[ ] MAILER_DSN configuré (vrai SMTP)
[ ] MESSENGER_TRANSPORT_DSN=doctrine://default
[ ] php bin/console doctrine:schema:validate → OK
[ ] php bin/console cache:warmup --env=prod → OK
[ ] composer install --no-dev --optimize-autoloader
[ ] npm run build → public/build/ généré
[ ] Nginx configuré et redémarré
[ ] SSL Let's Encrypt activé
[ ] Supervisor messenger-worker actif
[ ] Logs Symfony dans var/log/prod.log → aucune erreur critique
[ ] php bin/console security:check → 0 vulnérabilité

# ── FRONTEND ─────────────────────────────────────────────────────────────────
[ ] NEXT_PUBLIC_API_URL pointant vers l'API Symfony en prod
[ ] npm run build → .next/ généré sans erreur
[ ] PM2 démarré et configuré au boot
[ ] Nginx proxy configuré pour le port 3000
[ ] SSL Let's Encrypt activé
[ ] CORS configuré côté Symfony pour accepter le domaine Next.js
[ ] Test des routes API depuis le frontend
[ ] pm2 status → online
```

---

## 🛠️ COMMANDES DE MAINTENANCE COURANTES

```bash
# ── Symfony ──────────────────────────────────────────────────────────────────
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
sudo supervisorctl restart messenger-worker:*
tail -f var/log/prod.log

# ── Next.js ──────────────────────────────────────────────────────────────────
pm2 restart frontend
pm2 logs frontend --lines 50

# ── Nginx ────────────────────────────────────────────────────────────────────
sudo nginx -t
sudo systemctl reload nginx

# ── Logs système ─────────────────────────────────────────────────────────────
sudo journalctl -u nginx -f
sudo journalctl -u php8.3-fpm -f
```