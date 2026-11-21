import "server-only";
import { isIP } from "node:net";

/**
 * Résolution de l'IP du vrai visiteur à partir des en-têtes de la requête
 * entrante — logique PURE (testable sans Next.js), partagée par le limiteur
 * en mémoire (src/lib/rate-limit.ts) et par les en-têtes transmis au backend
 * (src/lib/http/visitor.ts) : les deux doivent voir la même IP.
 *
 * Topologie de production : visiteur → Cloudflare → Traefik → ce conteneur.
 *  - Cloudflare pose `CF-Connecting-IP` (écrase toute valeur fournie par le
 *    client) et AJOUTE l'IP du visiteur à droite de `X-Forwarded-For` ;
 *  - Traefik (forwardedHeaders.trustedIPs = Cloudflare) conserve cette chaîne
 *    et ajoute à son tour l'IP du nœud Cloudflare ;
 *  - le middleware `cloudflare-only` refuse toute connexion qui ne vient pas
 *    de Cloudflare : `CF-Connecting-IP` ne peut donc pas être forgé.
 *
 * Le PREMIER élément de `X-Forwarded-For` est, lui, entièrement contrôlé par
 * le client (`curl -H 'X-Forwarded-For: 1.2.3.4'` → « 1.2.3.4, <vraie IP>,
 * <nœud CF> ») : il ne doit JAMAIS servir de clé (contournement trivial de
 * tout limiteur, cf. audit 2026-11-20 point 12).
 */

/** Longueur maximale d'une IP textuelle (IPv6 complète avec IPv4 embarquée = 45). */
const MAX_IP_LENGTH = 45;

export interface HeaderReader {
  get(name: string): string | null;
}

export interface ResolveClientIpOptions {
  /**
   * Nombre de proxys de confiance devant ce conteneur qui AJOUTENT un maillon
   * à `X-Forwarded-For` (Traefik = 1 par défaut). Seul l'élément ajouté par le
   * dernier proxy de confiance est retenu, jamais un élément plus à gauche
   * (fourni par le client). Utilisé uniquement en l'absence de
   * `CF-Connecting-IP` (dev, ou Cloudflare désactivé).
   */
  trustedProxyHops?: number;
}

/** Valeur par défaut, surchargeable par `TRUSTED_PROXY_HOPS` (entier ≥ 1). */
export function trustedProxyHopsFromEnv(env: Record<string, string | undefined> = process.env): number {
  const raw = Number.parseInt(env.TRUSTED_PROXY_HOPS ?? "", 10);
  return Number.isInteger(raw) && raw >= 1 && raw <= 10 ? raw : 1;
}

/** Normalise une IP candidate : supprime crochets/zone IPv6, refuse tout ce qui n'est pas une IP. */
export function normalizeIp(candidate: string | null | undefined): string | null {
  if (!candidate) return null;
  let value = candidate.trim();
  if (value.length === 0 || value.length > MAX_IP_LENGTH + 2) return null;
  // "[2001:db8::1]" (forme entre crochets) → "2001:db8::1".
  if (value.startsWith("[") && value.endsWith("]")) value = value.slice(1, -1);
  // IPv4 mappée en IPv6 ("::ffff:1.2.3.4") → IPv4 pure : une même adresse ne
  // doit pas produire deux clés de limitation différentes.
  const mapped = /^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i.exec(value);
  if (mapped) value = mapped[1];
  if (value.length > MAX_IP_LENGTH) return null;
  return isIP(value) === 0 ? null : value.toLowerCase();
}

/**
 * IP du visiteur, ou `null` si aucune source fiable n'est disponible.
 * Ordre : `CF-Connecting-IP` (non forgeable derrière Cloudflare), puis
 * l'élément de `X-Forwarded-For` ajouté par le dernier proxy de confiance.
 * `X-Real-IP` n'est jamais lu : posé tel quel par le client si aucun proxy ne
 * l'écrase.
 */
export function resolveClientIp(h: HeaderReader, options: ResolveClientIpOptions = {}): string | null {
  const cf = normalizeIp(h.get("cf-connecting-ip"));
  if (cf) return cf;

  const forwarded = h.get("x-forwarded-for");
  if (!forwarded) return null;

  // Borne défensive : on ne découpe pas un en-tête de plusieurs Ko.
  const chain = forwarded.slice(-1024).split(",").map((part) => part.trim()).filter(Boolean);
  if (chain.length === 0) return null;

  const hops = Math.max(1, options.trustedProxyHops ?? trustedProxyHopsFromEnv());
  // Élément ajouté par le dernier proxy de confiance : le `hops`-ième en
  // partant de la droite. Chaîne plus courte que prévu → l'élément le plus à
  // gauche est alors lui-même ajouté par un proxy de confiance.
  const index = Math.max(0, chain.length - hops);
  return normalizeIp(chain[index]);
}

/**
 * Développe une IPv6 textuelle en 8 groupes de 16 bits. `null` si invalide.
 * (Ne gère pas l'IPv4 embarquée hors forme mappée, déjà convertie par normalizeIp.)
 */
function expandIpv6(ip: string): number[] | null {
  const withoutZone = ip.split("%")[0];
  const halves = withoutZone.split("::");
  if (halves.length > 2) return null;
  const parse = (part: string) => (part === "" ? [] : part.split(":").map((g) => Number.parseInt(g, 16)));
  const head = parse(halves[0]);
  const tail = halves.length === 2 ? parse(halves[1]) : [];
  const missing = 8 - head.length - tail.length;
  if (halves.length === 1 && head.length !== 8) return null;
  if (missing < 0) return null;
  const groups = [...head, ...new Array<number>(halves.length === 2 ? missing : 0).fill(0), ...tail];
  if (groups.length !== 8 || groups.some((g) => !Number.isInteger(g) || g < 0 || g > 0xffff)) return null;
  return groups;
}

/**
 * Clé de limitation pour une IP : IPv4 telle quelle, IPv6 ramenée à son /64
 * (un FAI attribue au minimum un /64 à un abonné : faire tourner l'adresse
 * dans ce préfixe ne doit pas réinitialiser le compteur). Toujours bornée.
 */
export function rateLimitKeyForIp(ip: string | null): string {
  if (!ip) return "unknown";
  if (isIP(ip) === 6) {
    const groups = expandIpv6(ip);
    if (groups) {
      return `${groups.slice(0, 4).map((g) => g.toString(16)).join(":")}::/64`;
    }
  }
  return ip.slice(0, MAX_IP_LENGTH);
}
