# syntax=docker/dockerfile:1

# ---- Stage 1 : dépendances ----
FROM node:22-alpine AS deps
WORKDIR /app
# Le npm embarqué dans node:22-alpine (10.x) résout ce lockfile différemment
# de npm 11 (utilisé pour le générer) et fait échouer `npm ci` à tort
# ("Missing: @swc/helpers@... from lock file") — on aligne la version.
RUN npm install -g npm@11
COPY package.json package-lock.json ./
RUN npm ci

# ---- Stage 2 : build (standalone) ----
FROM node:22-alpine AS builder
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
FROM node:22-alpine AS runner
WORKDIR /app

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

# su-exec : permet à docker-entrypoint.sh de démarrer en root (nécessaire
# pour lire les secrets Docker, cf. commentaire dans ce script) puis de
# basculer sur nextjs juste avant d'exécuter node.
RUN apk add --no-cache su-exec \
    && addgroup --system --gid 1001 nodejs \
    && adduser --system --uid 1001 nextjs

COPY --from=builder --chown=nextjs:nodejs /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Pas de USER ici : l'ENTRYPOINT doit démarrer en root pour lire les secrets
# Docker (cf. docker-entrypoint.sh), qui bascule ensuite explicitement sur
# nextjs via su-exec avant d'exécuter node — le process applicatif final ne
# tourne jamais en root.

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -qO- http://127.0.0.1:3000/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["node", "server.js"]
