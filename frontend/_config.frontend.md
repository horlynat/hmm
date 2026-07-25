# Architecture globale — Frontend (vitrine Next.js)

Contexte : vitrine publique en lecture seule (projets, articles, compétences, parcours,
témoignages) + formulaires contact/devis. Pas de compte utilisateur côté public — le
back-office reste dans `backend/` (Twig/Stimulus). Bilingue FR (défaut) / EN dès le départ.
Déploiement prévu sur le VPS existant, à côté du backend Symfony.

## ⚠️ Prérequis à lever côté backend avant intégration

`backend/config/packages/security.yaml` contient :

```yaml
access_control:
    - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

Cette règle de firewall s'applique **avant** les attributs `security:` définis sur chaque
opération API Platform (`ArticleApiResource`, `SkillApiResource`, `ContactMessageApiResource`,
`QuoteRequestApiResource`, etc.), qui prévoient pourtant explicitement des `GetCollection`/`Get`
publics et des `Post` publics pour le contact/devis. Tant que cette ligne reste telle quelle,
**aucun appel public (même server-to-server) ne pourra passer** : tout /api renverra 401.

➜ À corriger : retirer cette entrée globale (ou la restreindre aux verbes d'écriture) et laisser
API Platform gérer la sécurité par opération, comme c'est déjà fait dans chaque ApiResource.
Je peux faire ce correctif si tu veux, mais je ne l'ai pas touché sans confirmation car ce n'est
pas dans le périmètre "frontend".

## Décision : VPS auto-hébergé plutôt que Vercel

Le VPS existant (4 vCores, 8 Go RAM, 150 Go, 200 Mbps) héberge déjà le backend Symfony et
tourne très peu chargé. Next.js y est ajouté sans coût supplémentaire.

- **Coût** : Vercel en usage commercial nécessite le plan Pro (~20 $/mois/utilisateur) — une
  dépense récurrente pour une capacité déjà payée et disponible sur le VPS.
- **Latence** : l'architecture ici fait passer 100 % des appels vers l'API Symfony par le
  serveur Next.js (Server Components/Actions), jamais par le navigateur (cf. section
  "Pourquoi 100 % server-side"). Sur le VPS ces appels restent en `127.0.0.1` (quasi
  instantané). Sur Vercel, chaque rendu de page traverserait l'internet public pour atteindre
  l'API, avec en plus l'obligation d'exposer celle-ci publiquement (surface d'attaque accrue).
- **Opérationnel** : un seul serveur à surveiller/déployer/sauvegarder plutôt que deux
  prestataires avec des workflows distincts.
- **Ce qu'on perd** : le CDN edge global et l'auto-scaling massif de Vercel — compensé par la
  mise en place de Cloudflare devant le VPS (cf. section dédiée ci-dessous).

## Domaines

| Domaine                | Sert                                              |
|-------------------------|----------------------------------------------------|
| `horlynat.com` / `www`  | Vitrine publique Next.js                            |
| `api.horlynat.com`      | API Symfony (API Platform)                          |
| `dark.horlynat.com`     | Back-office / espace VIP (gestion des projets clients : budgets, dépenses, historique, devis) |

`dark.horlynat.com` est l'espace le plus sensible : c'est là que sont gérés les projets et
données des clients. Il a le niveau de protection le plus élevé dans le plan Cloudflare
ci-dessous (Zero Trust Access en plus de l'authentification Symfony existante).

## Cloudflare — mise en place et sécurisation

Le domaine n'est pas encore sur Cloudflare : il faut basculer les nameservers avant toute
autre étape. Une fois fait, tout le trafic (vitrine, API, espace VIP) passe par Cloudflare
avant d'atteindre le VPS.

### 1. Activation

1. Ajouter `horlynat.com` sur Cloudflare (plan **Free** suffisant pour démarrer : proxy,
   DNS, SSL, WAF managé de base, Bot Fight Mode, Access/Zero Trust jusqu'à 50 utilisateurs).
2. Chez le registrar du domaine, remplacer les nameservers actuels par ceux fournis par
   Cloudflare. Propagation : quelques minutes à 24 h.
3. Une fois la zone active, créer les enregistrements DNS, **tous proxied (nuage orange)** —
   y compris `api.` et `dark.` : on veut le WAF/la protection Cloudflare partout, pas
   seulement le cache sur la vitrine.

   | Type | Nom    | Valeur (IP du VPS) | Proxy |
   |------|--------|----------------------|-------|
   | A    | @      | `<IP_VPS>`           | ✅ Proxied |
   | A    | www    | `<IP_VPS>`           | ✅ Proxied |
   | A    | api    | `<IP_VPS>`           | ✅ Proxied |
   | A    | dark   | `<IP_VPS>`           | ✅ Proxied |

### 2. TLS

- Mode SSL/TLS : **Full (Strict)**.
- Générer un **Cloudflare Origin Certificate** (gratuit, valable 15 ans, dans
  Cloudflare → SSL/TLS → Origin Server) couvrant `horlynat.com` + `*.horlynat.com`, et
  l'installer dans Nginx sur les 3 `server{}` (vitrine, api, dark) à la place / en
  complément du certificat Let's Encrypt actuel.
- Activer "Always Use HTTPS" + HSTS (après avoir vérifié que les 3 sous-domaines
  répondent bien en HTTPS).

### 3. Restauration de l'IP réelle du visiteur (important)

Le backend a `LoginHistory` (ip, userAgent) et un `GeolocationService` : une fois derrière
Cloudflare, Nginx/Symfony verront par défaut l'IP de Cloudflare, pas celle du visiteur, si
rien n'est configuré. À faire côté Nginx (pas besoin de toucher `trusted_proxies` Symfony
si c'est géré ici) :

```nginx
# Liste à tenir à jour depuis https://www.cloudflare.com/ips-v4 et /ips-v6
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
# … (toutes les plages Cloudflare)
real_ip_header CF-Connecting-IP;
```

À placer dans les 3 vhosts (vitrine, api, dark) — sinon `LoginHistory` et la géolocalisation
enregistreront systématiquement des IP Cloudflare.

### 4. Verrouillage de l'origine

Une fois Cloudflare actif, configurer le pare-feu du VPS (ufw/iptables) pour n'accepter le
trafic 80/443 que depuis les plages IP Cloudflare (mêmes listes que ci-dessus). Objectif :
empêcher quiconque de contourner Cloudflare (WAF, Access) en appelant directement l'IP du VPS.
Le SSH et l'accès admin serveur restent séparés (par IP/clé), pas concernés par cette règle.

### 5. Espace VIP (`dark.horlynat.com`) — protection renforcée

C'est le point le plus important : cet espace contient les projets, budgets et historiques
des clients, donc on ajoute une couche d'authentification **avant** Symfony :

- **Cloudflare Access (Zero Trust)**, gratuit jusqu'à 50 utilisateurs : créer une
  application Access sur `dark.horlynat.com`, avec une policy limitée à ton email (et à
  ceux de clients si certains doivent y accéder). Un visiteur devra passer un code à usage
  unique (ou SSO Google/GitHub) envoyé par Cloudflare avant même d'atteindre la page de
  login Symfony. Si Symfony est un jour compromis ou mal configuré, cette couche reste un
  filtre indépendant.
- **WAF managé + rate limiting** sur les endpoints d'authentification
  (`dark.horlynat.com/login`, `api.horlynat.com/login` ou équivalent JWT) pour bloquer le
  brute force. Vérifier les quotas exacts du plan Free au moment de la configuration (le
  rate limiting personnalisé peut être limité en nombre de règles hors plan payant).
- **Bot Fight Mode** activé (gratuit) en protection additionnelle basique.

### 6. Cache

- `horlynat.com` : comportement par défaut (Cloudflare respecte les en-têtes
  `Cache-Control` déjà envoyés par Next.js/ISR) — pas de règle spéciale nécessaire au
  départ.
- `api.horlynat.com` et `dark.horlynat.com` : ajouter une **Cache Rule "Bypass Cache"**
  explicite — aucune réponse dynamique/authentifiée ne doit être mise en cache à l'edge.

## Stack retenue

| Domaine       | Choix                                                        |
|---------------|---------------------------------------------------------------|
| Framework     | Next.js 16 (App Router), React 19, TypeScript strict          |
| Styles        | Tailwind CSS v4 (déjà en place)                                |
| i18n          | `next-intl` — segment `[locale]`, FR par défaut, EN en second  |
| Data fetching | 100 % server-side (Server Components + Server Actions), zéro appel client direct à l'API |
| Revalidation  | ISR + `revalidateTag`, déclenchée par un webhook Symfony       |
| Déploiement   | VPS existant, `next build` standalone + PM2/systemd derrière Nginx (reverse proxy à côté de Symfony) |
| Tests         | Vitest + Testing Library (unitaire/composants), Playwright (e2e parcours clés) |

### Pourquoi 100 % server-side pour la donnée

Toutes les lectures (Server Components) et écritures (Server Actions pour contact/devis)
passent par le serveur Next.js, jamais par le navigateur. Conséquences :
- Pas besoin d'exposer l'URL de l'API au client (`API_URL` reste une env var serveur uniquement).
- Pas de configuration CORS à maintenir côté Symfony pour le site public (nelmio_cors ne sert
  qu'au back-office/API explorer si besoin).
- Facilite l'ISR : chaque page peut être mise en cache et re-générée à la demande.

## Arborescence proposée

```
frontend/src/
├── app/
│   ├── [locale]/
│   │   ├── layout.tsx              # Header/Footer, providers i18n
│   │   ├── page.tsx                # Accueil
│   │   ├── projects/
│   │   │   ├── page.tsx            # Liste (GetCollection Project)
│   │   │   └── [slug]/page.tsx     # Détail (Get Project)
│   │   ├── articles/
│   │   │   ├── page.tsx
│   │   │   └── [slug]/page.tsx
│   │   ├── skills/page.tsx         # Groupé par SkillCategory
│   │   ├── experience/page.tsx     # Experience + Course fusionnés (timeline CV)
│   │   ├── testimonials/page.tsx
│   │   ├── contact/page.tsx        # Server Action -> POST /api/contact_messages
│   │   ├── quote/page.tsx          # Server Action -> POST /api/quote_requests
│   │   ├── legal-notice/page.tsx
│   │   └── privacy/page.tsx
│   ├── api/
│   │   └── revalidate/route.ts     # Webhook appelé par Symfony après publication
│   ├── sitemap.ts
│   ├── robots.ts
│   └── globals.css
├── i18n/
│   ├── routing.ts                  # locales, defaultLocale
│   ├── request.ts
│   └── navigation.ts               # Link/useRouter localisés
├── messages/
│   ├── fr.json
│   └── en.json
├── lib/
│   ├── api/
│   │   ├── client.ts               # apiFetch<T>(path, init) — base URL, tags, erreurs
│   │   ├── articles.ts
│   │   ├── projects.ts
│   │   ├── skills.ts
│   │   ├── experiences.ts
│   │   ├── courses.ts
│   │   └── testimonials.ts
│   └── types/                      # DTOs miroir des groupes de sérialisation `api_public`
├── actions/
│   ├── contact.ts                  # 'use server'
│   └── quote.ts                    # 'use server'
├── components/
│   ├── ui/                         # Button, Card, Badge, Field… (design system minimal)
│   ├── layout/                     # Header, Footer, Nav, LocaleSwitcher
│   └── sections/                   # ProjectCard, ArticleCard, SkillBar, Timeline, TestimonialCard
├── config/
│   └── site.ts                     # nav, réseaux sociaux, métadonnées par défaut
└── middleware.ts                   # next-intl (détection + redirection de locale)
```

## Mapping routes ↔ ressources API

| Route publique          | Ressource API Platform          | Rendu           |
|--------------------------|----------------------------------|-----------------|
| `/[locale]`               | Project, Article, Testimonial (extraits) | ISR (revalidate: 3600) |
| `/[locale]/projects`      | `GetCollection` Project           | ISR + tag `projects` |
| `/[locale]/projects/[slug]` | `Get` Project                  | ISR + tag `project:{slug}` |
| `/[locale]/articles`      | `GetCollection` Article           | ISR + tag `articles` |
| `/[locale]/articles/[slug]` | `Get` Article                  | ISR + tag `article:{slug}` |
| `/[locale]/skills`        | `GetCollection` Skill (+ SkillCategory) | ISR long (rarement modifié) |
| `/[locale]/experience`    | `GetCollection` Experience + Course | ISR long |
| `/[locale]/testimonials`  | `GetCollection` Testimonial       | ISR |
| `/[locale]/contact`       | `Post` ContactMessage (Server Action) | dynamique |
| `/[locale]/quote`         | `Post` QuoteRequest (Server Action) | dynamique |

## Revalidation à la demande

1. Chaque `apiFetch` de liste/détail passe un tag (`{ next: { tags: ['articles'] } }`).
2. `backend` appelle `POST https://<site>/api/revalidate` (secret partagé en header) après
   publication/mise à jour d'un Article/Project/etc. — via un `EventSubscriber` Doctrine
   (postUpdate/postPersist) ou directement dans les controllers Admin existants.
3. Le handler `app/api/revalidate/route.ts` vérifie le secret puis appelle `revalidateTag(tag)`.

## Variables d'environnement (`.env.example`)

```
API_URL=http://127.0.0.1:8000/api        # interne, serveur uniquement (pas NEXT_PUBLIC_)
REVALIDATE_SECRET=change-me
NEXT_PUBLIC_SITE_URL=https://horlynat.com
```

## Déploiement (VPS partagé avec le backend)

- `next.config.ts` : `output: "standalone"`.
- Build : `npm ci && npm run build`, exécution via `node .next/standalone/server.js`
  (piloté par PM2 ou un service systemd `frontend.service`, écoute sur `127.0.0.1:3000`).
- Nginx : un `server{}` pour `horlynat.com`/`www` → `proxy_pass http://127.0.0.1:3000;`,
  et les vhosts existants pour `api.horlynat.com` et `dark.horlynat.com` → PHP-FPM/Symfony.
  Les 3 vhosts reçoivent le certificat Origin Cloudflare et le `real_ip_header` (cf. section
  Cloudflare ci-dessus).
- Une seule instance Node sur ce VPS ⇒ le cache ISR par défaut (système de fichiers) suffit,
  pas besoin de cache handler externe (Redis) tant qu'on ne scale pas horizontalement.
- Déploiement : `git pull && npm ci && npm run build && pm2 reload frontend` (zero-downtime).

## Tests & qualité

```bash
npm run lint
npx vitest run          # composants/lib
npx playwright test     # parcours: accueil, fiche projet, envoi contact/devis
```

## Prochaines étapes suggérées

1. Corriger `security.yaml` côté backend (cf. prérequis ci-dessus).
2. Basculer `horlynat.com` sur Cloudflare (nameservers) et suivre les étapes 1 à 6 de la
   section Cloudflare — en priorité le verrouillage de `dark.horlynat.com` (Access) et la
   restauration de l'IP réelle (Nginx `real_ip`), avant d'ouvrir largement le trafic.
3. Installer `next-intl`, poser `middleware.ts` + `i18n/routing.ts`.
4. Écrire `lib/api/client.ts` + un module par ressource, avec les DTOs `api_public`.
5. Scaffolder les pages dans l'ordre : accueil → projects → articles → skills/experience →
   testimonials → contact/quote → sitemap/robots.
6. Brancher le webhook de revalidation depuis les controllers Admin du backend.
