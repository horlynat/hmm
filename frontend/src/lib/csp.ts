import { routing } from "@/i18n/routing";

/**
 * Chemins EXTERNES (tels que tapés dans la barre d'adresse) servis avec la
 * CSP stricte par nonce (cf. src/proxy.ts) : /contact, et tout l'espace
 * compte — /fr/compte/** et son équivalent anglais /en/account/**
 * (routing.pathnames). Ces pages sont toutes rendues dynamiquement
 * (`force-dynamic` sur /contact et sur le layout /compte), condition pour
 * qu'un nonce par requête puisse y être injecté. Les variantes « croisées »
 * (/en/compte, /fr/account) sont redirigées par next-intl : elles reçoivent
 * aussi la CSP stricte, sans effet sur une 3xx.
 */
const STRICT_SECTIONS = ["contact", "compte", "account"];
const strictNoncePathPattern = new RegExp(
  `^/(?:${routing.locales.join("|")})/(?:${STRICT_SECTIONS.join("|")})(?:/.*)?$`,
);

export function needsStrictCsp(pathname: string): boolean {
  return strictNoncePathPattern.test(pathname);
}

interface BuildCspOptions {
  isDev: boolean;
  /** Présent uniquement sur les routes forcées en dynamique (/contact, /compte/**). */
  nonce?: string;
}

/**
 * Construit la valeur de l'en-tête Content-Security-Policy.
 *
 * Sans `nonce` : `script-src` garde `'unsafe-inline'` (compatible SSG/ISR,
 * cas par défaut de tout le site).
 * Avec `nonce` : `script-src` devient strict (`nonce` + `strict-dynamic`,
 * sans `unsafe-inline`) — réservé aux routes en rendu dynamique.
 *
 * `style-src` garde `'unsafe-inline'` dans tous les cas : un nonce ne peut
 * pas s'appliquer à un attribut `style=""` (seulement à des éléments
 * `<script>`/`<style>`), et de nombreux composants partagés (Header, Card,
 * Badge...) utilisent `style={{...}}`. Durcir uniquement `script-src` — là où
 * se joue le risque XSS réel — est le compromis usuel des CSP strictes.
 */
export function buildCsp({ isDev, nonce }: BuildCspOptions): string {
  const scriptSrc = nonce
    ? `'self' 'nonce-${nonce}' 'strict-dynamic'${isDev ? " 'unsafe-eval'" : ""}`
    : `'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""}`;

  return [
    "default-src 'self'",
    `script-src ${scriptSrc}`,
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob: https:",
    "font-src 'self' data:",
    `connect-src 'self'${isDev ? " ws:" : ""}`,
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "frame-src 'none'",
    "manifest-src 'self'",
    ...(isDev ? [] : ["upgrade-insecure-requests"]),
  ].join("; ");
}
