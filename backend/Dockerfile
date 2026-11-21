# syntax=docker/dockerfile:1

# Image PHP unique, VERSION FIGÉE, partagée par le build Composer et l'image
# finale. Auparavant : `dunglas/frankenphp:1-php8.4-alpine` (tag flottant) +
# `composer:2` — la prod tournait en PHP 8.4.25 (build du 2026-08-29) et
# l'étage Composer en PHP 8.5.6, tous deux vulnérables à PHP2Shell
# (CVE-2026-109618 / CVE-2026-109619, RCE non authentifiée exploitée, corrigée
# en 8.4.27 / 8.5.12). Monter de version = changer CETTE ligne. Garde-fous :
# la contrainte "php" de composer.json (~8.4.27 || >=8.5.12) fait échouer
# `composer install` ci-dessous — donc le build — sur tout PHP non corrigé
# (l'étage vendor utilise la même image que la prod), et
# vendor/composer/platform_check.php refuse en plus de démarrer l'app sous
# 8.4.27 (borne basse seulement : un 8.5.0–8.5.11 n'y est pas détecté, seul
# le `composer install` du build l'arrête).
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13.6-php8.4.28-alpine@sha256:bacc982b5c926a5adee882cd245fc96fb79c316fe9ea8d845e2e3f3863113adb

# ---- Stage 1 : dépendances Composer (prod, sans dev) ----
# Même PHP que la prod (et non l'image composer:2, dont le PHP embarqué suit
# son propre calendrier) : seul le binaire composer est repris de l'image
# officielle, à version figée.
FROM ${FRANKENPHP_IMAGE} AS vendor
USER root
COPY --from=composer:2.9.8@sha256:b09bccd91a78fe8a9ab4b33d707b862e8fe54fec17782e32683ad2a69c46867d /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /build
# ext-xsl : requis par twig/inky-extra (templates email). ext-bcmath : requis
# par composer.json (App\Entity\Project et consorts). ext-zip : extraction
# des archives --prefer-dist (pas d'unzip dans l'image de base).
RUN install-php-extensions xsl bcmath zip
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
FROM node:26-alpine@sha256:6bd9ab43c0ec0190ca7b2d193906cb8b7edf6e7f4afa43ddeccd6452710bffb5 AS assets
WORKDIR /app
COPY --from=vendor /build/vendor ./vendor
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 3 : image finale FrankenPHP ----
FROM ${FRANKENPHP_IMAGE} AS app

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
    # mariadb-client : fournit les binaires `mysqldump`/`mysql` que
    # DatabaseBackupService shelle out — l'extension PHP pdo_mysql
    # ci-dessus ne fournit QUE le driver PDO, pas ces outils CLI. Absent
    # jusqu'ici : app:backup:create échouait en prod avec "mysqldump: not
    # found" (exit 127), jamais remarqué faute d'avoir testé la
    # fonctionnalité de bout en bout après déploiement.
    # mariadb-connector-c : mariadb-client seul ne fournit AUCUN plugin
    # d'authentification (dossier /usr/lib/mariadb/plugin/ vide) — sans ce
    # paquet, mysqldump échoue contre MySQL 8 ("Plugin caching_sha2_password
    # could not be loaded"), l'authentification par défaut de MySQL 8 (cf.
    # infra/README.md §4). Constaté en testant contre la vraie base de prod.
    && apk add --no-cache su-exec age mariadb-client mariadb-connector-c

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=":80"

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /build /app
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
