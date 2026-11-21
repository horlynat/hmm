import "server-only";
import { headers } from "next/headers";
import { resolveClientIp, type HeaderReader } from "./client-ip";

/** User-Agent transmis au backend : borné (colonnes VARCHAR côté backend, en-têtes démesurés). */
const MAX_USER_AGENT_LENGTH = 255;

/**
 * En-têtes du vrai visiteur à joindre à un appel backend : construits à
 * partir d'en-têtes déjà lus (testable). `X-Forwarded-For` ne contient QUE
 * l'IP résolue (cf. client-ip.ts) — jamais la chaîne brute du client, dont le
 * premier élément est forgeable. Le backend (SYMFONY_TRUSTED_PROXIES couvre
 * le réseau Docker interne) prend alors cette IP pour celle du visiteur.
 */
export function visitorHeadersFrom(h: HeaderReader): Record<string, string> {
  const ip = resolveClientIp(h);
  const userAgent = (h.get("user-agent") ?? "").replace(/[\r\n]/g, " ").slice(0, MAX_USER_AGENT_LENGTH).trim();

  return {
    ...(ip ? { "X-Forwarded-For": ip } : {}),
    ...(userAgent ? { "User-Agent": userAgent } : {}),
  };
}

/**
 * IP + User-Agent du vrai visiteur, à transmettre à TOUT appel backend émis
 * pendant une requête (Server Action, Route Handler, rendu dynamique). Sans
 * ça, le backend ne voit que l'IP de CE conteneur pour tous les visiteurs :
 * ses limiteurs par IP (forgot-password, vérification d'email, tickets
 * invités…) deviennent globaux — quelques requêtes d'un seul visiteur
 * bloquent la fonctionnalité pour tout le site — et LoginHistory/UserSession
 * enregistrent une IP interne et un User-Agent de robot.
 *
 * À NE PAS appeler depuis un contexte de build/ISR (génération statique) :
 * `headers()` y rendrait la page dynamique (ou lèverait). Les lectures de
 * contenu public mises en cache (apiFetch) n'envoient donc rien.
 */
export async function forwardedVisitorHeaders(): Promise<Record<string, string>> {
  return visitorHeadersFrom(await headers());
}
