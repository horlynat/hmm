# syntax=docker/dockerfile:1

# Image de base épinglée (version exacte + digest de l'index multi-arch) :
# un tag flottant (`node:22-alpine`) pouvait changer de contenu d'un build à
# l'autre sans aucune trace dans le dépôt. Node ≥ 22.12 requis
# (sanitize-html ≥ 2.17.6 dépend d'un htmlparser2 ESM-only). Mise à jour :
#   docker buildx imagetools inspect node:22-alpine   (version + digest)
ARG NODE_IMAGE=node:22.23.5-alpine3.24@sha256:1314fb30ec37592d5845e5a160d522c0e680972aa534549940b8d73bde88481a

# ---- Stage 1 : dépendances ----
FROM ${NODE_IMAGE} AS deps
WORKDIR /app
# Le npm embarqué dans node:22-alpine (10.x) résout ce lockfile différemment
# de npm 11 (utilisé pour le générer) et fait échouer `npm ci` à tort
# ("Missing: @swc/helpers@... from lock file") — on aligne la version, ÉPINGLÉE
# (même valeur que .github/workflows/frontend-ci.yml).
RUN npm install -g npm@11.21.2
COPY package.json package-lock.json ./
# `--ignore-scripts` : aucun script install/postinstall d'une dépendance
# (transitive comprise) ne s'exécute pendant le build — étage qui tourne avec
# `network: host` côté compose, donc avec accès aux services de l'hôte.
# Inventaire des paquets à scripts du lockfile (`hasInstallScript`) :
#  - @swc/core, @parcel/watcher (via next-intl) : leurs scripts ne sont que
#    des REPLIS (compilation depuis les sources / @swc/wasm) si le binaire
#    précompilé manque ; celui-ci arrive par une dépendance optionnelle
#    (@swc/core-linux-x64-musl, @parcel/watcher-linux-x64-musl), installée
#    sans script. Vérifié explicitement ci-dessous : échec franc du build
#    plutôt qu'une erreur au runtime si un binaire venait à manquer.
#  - unrs-resolver (eslint, dev) : non utilisé par `next build`.
#  - fsevents : macOS uniquement, jamais installé ici.
# next et sharp n'ont pas de script d'installation (binaires précompilés
# @next/swc-*, @img/sharp-* en dépendances optionnelles).
RUN npm ci --ignore-scripts \
    && node -e "require('@swc/core'); require('@parcel/watcher'); require('sharp')"

# ---- Stage 2 : build (standalone) ----
FROM ${NODE_IMAGE} AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# NEXT_PUBLIC_* est inliné dans le bundle client au build — seules ces deux
# variables sont publiques. API_URL reste server-only : jamais inliné côté
# client (seules les variables NEXT_PUBLIC_* le sont), mais doit quand même
# être disponible ici en ARG car plusieurs layouts/pages font des fetch
# server-side VERS L'API PENDANT LE BUILD (génération statique/ISR), certaines
# (page d'accueil, "À propos") échouant même la génération si le contenu
# piloté par le back-office est absent (cf. commentaires dans ces pages) —
# sans backend joignable au build, `next build` échoue.
#
# `--network=host` (nécessite `docker build --allow network.host`, déjà posé
# dans docker-compose.prod.yml) donne à ce RUN un accès direct à la stack
# réseau de l'hôte : le conteneur backend doit donc publier son port sur
# 127.0.0.1 (loopback uniquement, jamais exposé publiquement — cf. compose)
# le temps du build. Cf. infra/README.md "Build en deux temps".
ARG NEXT_PUBLIC_SITE_URL
ARG NEXT_PUBLIC_MEDIA_URL
ARG API_URL
ENV NEXT_PUBLIC_SITE_URL=${NEXT_PUBLIC_SITE_URL} \
    NEXT_PUBLIC_MEDIA_URL=${NEXT_PUBLIC_MEDIA_URL} \
    API_URL=${API_URL} \
    NEXT_TELEMETRY_DISABLED=1

RUN --network=host npm run build

# ---- Stage 3 : runtime minimal ----
FROM ${NODE_IMAGE} AS runner
WORKDIR /app

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

# su-exec : conservé uniquement pour le cas où le conteneur serait lancé
# explicitement en root (`user: "0"`), cf. docker-entrypoint.sh.
RUN apk add --no-cache su-exec \
    && addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 --ingroup nodejs nextjs

COPY --from=builder --chown=nextjs:nodejs /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Utilisateur NON-root pour toute l'image finale : l'ENTRYPOINT, le process
# Node ET le HEALTHCHECK (qui s'exécute avec l'utilisateur de l'image)
# tournent sous nextjs (uid 1001). Conséquence : les secrets Docker montés
# dans /run/secrets doivent être lisibles par l'uid/gid 1001 — `docker
# compose` hors Swarm ignore `uid`/`gid`/`mode` et garde les permissions du
# fichier hôte. Action à faire une fois sur le VPS (cf. docker-entrypoint.sh) :
#   chgrp 1001 infra/secrets/revalidate_secret && chmod 0440 infra/secrets/revalidate_secret
# (le backend, qui lit le même fichier en root avant de basculer sur
# www-data, n'est pas affecté).
USER nextjs:nodejs

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -qO- http://127.0.0.1:3000/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["node", "server.js"]
