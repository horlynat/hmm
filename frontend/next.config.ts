import type { NextConfig } from "next";
import path from "path";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const isDev = process.env.NODE_ENV === "development";

// Origine publique des médias (article.media[].filePath) — cf. src/lib/media.ts.
const mediaUrl = new URL(process.env.NEXT_PUBLIC_MEDIA_URL ?? "http://127.0.0.1:8000");

/**
 * Content-Security-Policy : générée par requête dans `src/proxy.ts` (seule
 * source de vérité — deux en-têtes CSP sur la même réponse seraient
 * ambigus), qui applique la variante stricte par nonce sur `/contact` et la
 * variante par défaut (`'unsafe-inline'`, compatible SSG/ISR) partout
 * ailleurs. Voir `src/lib/csp.ts` et
 * node_modules/next/dist/docs/01-app/02-guides/content-security-policy.md.
 */
const securityHeaders = [
  // Anti-clickjacking (doublon de frame-ancestors, meilleure couverture navigateurs anciens).
  { key: "X-Frame-Options", value: "DENY" },
  // Empêche le navigateur de « deviner » un type MIME (protège contre certains XSS).
  { key: "X-Content-Type-Options", value: "nosniff" },
  // Ne fuite pas l'URL complète vers les origines tierces.
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  // Désactive des APIs sensibles inutilisées par le site.
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=(), browsing-topics=(), interest-cohort=()",
  },
  { key: "X-DNS-Prefetch-Control", value: "on" },
  // HSTS uniquement en production (éviter de verrouiller le HTTP local).
  ...(isDev
    ? []
    : [
        {
          key: "Strict-Transport-Security",
          value: "max-age=63072000; includeSubDomains; preload",
        },
      ]),
];

const nextConfig: NextConfig = {
  output: "standalone",
  // Ne pas divulguer la stack via l'en-tête X-Powered-By.
  poweredByHeader: false,
  turbopack: {
    root: path.join(__dirname),
  },
  images: {
    remotePatterns: [
      {
        protocol: mediaUrl.protocol.replace(":", "") as "http" | "https",
        hostname: mediaUrl.hostname,
        port: mediaUrl.port,
        pathname: "/uploads/**",
      },
    ],
    // Next.js 16 bloque par défaut l'optimisation d'images depuis une IP privée
    // (protection anti-SSRF) — nécessaire en dev tant que NEXT_PUBLIC_MEDIA_URL
    // n'est pas défini et retombe sur 127.0.0.1:8000 ; jamais activé en prod, où
    // l'origine media est un vrai domaine public.
    dangerouslyAllowLocalIP: isDev,
  },
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
};

export default withNextIntl(nextConfig);