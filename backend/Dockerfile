# syntax=docker/dockerfile:1

# ---- Stage 1 : dépendances Composer (prod, sans dev) ----
FROM composer:2 AS vendor
WORKDIR /app
# ext-xsl : requis par twig/inky-extra (templates email). ext-bcmath : requis
# par composer.json (App\Entity\Project et consorts) depuis qu'on l'a
# déclaré explicitement — les deux absents de l'image composer:2 de base.
RUN apk add --no-cache libxslt-dev $PHPIZE_DEPS && docker-php-ext-install xsl bcmath
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-progress
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm -f .env.dev .env.test .env.test.local .env.local
# Note : .env (défauts non sensibles, committé) est conservé — Symfony ne
# l'utilise que pour les clés absentes de l'environnement réel. Les vrais
# secrets prod sont injectés par docker-compose (Docker secrets), qui priment
# toujours sur .env sans qu'on ait besoin de composer dump-env.

# ---- Stage 2 : assets back-office (Vite/Stimulus/Tailwind) ----
# Dépend du stage vendor : package.json référence @symfony/ux-live-component
# et @symfony/ux-turbo en dépendances npm "file:vendor/symfony/.../assets"
# (pont Composer -> npm des bundles Symfony UX) — npm ci échoue sans vendor/
# déjà présent.
FROM node:22-alpine AS assets
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 3 : image finale FrankenPHP ----
FROM dunglas/frankenphp:1-php8.4-alpine AS app

# L'image de base bascule déjà sur www-data (son propre USER) — remonter en
# root explicitement, sinon les RUN/COPY qui suivent (et in fine l'ENTRYPOINT,
# cf. commentaire plus bas) héritent silencieusement de www-data.
USER root

# pdo_mysql, pas pdo_pgsql : les migrations committées sont écrites en
# dialecte MySQL (cf. infra/README.md §4).
# gd : requis par endroid/qr-code (QR code d'activation 2FA, cf.
# TwoFactorController/ApiTwoFactorSetupController) — absent jusqu'ici, ce qui
# faisait planter en 500 tout utilisateur essayant d'activer sa 2FA en prod
# ("Unable to generate image: please check if the GD extension is enabled").
# su-exec : permet à docker-entrypoint.sh de démarrer en root (nécessaire
# pour lire les secrets Docker, cf. commentaire dans ce script) puis de
# basculer sur www-data juste avant d'exécuter frankenphp.
RUN install-php-extensions \
    pdo_mysql \
    intl \
    zip \
    xsl \
    opcache \
    apcu \
    bcmath \
    gd \
    # age : chiffrement des dumps avant copie hors-site (cf.
    # App\Service\DatabaseBackupService::shipOffsite) — clé publique
    # seulement embarquée ici via AGE_RECIPIENT, jamais la clé privée.
    && apk add --no-cache su-exec age

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=":80"

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /app /app
COPY --from=assets --chown=www-data:www-data /app/public/build /app/public/build
COPY --chown=www-data:www-data frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R u+rwX var

# Caddy (sous FrankenPHP) écrit son état (config autosave, storage TLS) dans
# les dossiers XDG /config/caddy et /data/caddy, appartenant à root par
# défaut dans l'image de base — inaccessibles à www-data (le process final
# tourne sous cet utilisateur, cf. docker-entrypoint.sh), ce qui spamme les
# logs d'erreurs "permission denied" à chaque démarrage. Inoffensif
# fonctionnellement (pas de TLS/ACME ici, Traefik s'en charge en amont) mais
# bruite les logs consommés par fail2ban (cf. infra/scripts).
RUN mkdir -p /config/caddy /data/caddy \
    && chown -R www-data:www-data /config /data

# Pas de USER ici : l'ENTRYPOINT doit démarrer en root pour lire les secrets
# Docker (cf. docker-entrypoint.sh), qui bascule ensuite explicitement sur
# www-data via su-exec avant d'exécuter frankenphp — le process applicatif
# final ne tourne jamais en root.

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1:2019/config/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
