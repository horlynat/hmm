import "server-only";
import { headers } from "next/headers";
import { rateLimitKeyForIp, resolveClientIp } from "@/lib/http/client-ip";

/**
 * Limiteur de débit basique, en mémoire, par IP + action. Objectif : freiner le
 * spam de formulaires (inscriptions, devis, contact) en complément du honeypot
 * et des limiteurs backend (qui reçoivent la même IP, cf. src/lib/http/visitor.ts).
 *
 * Limites : l'état est local à l'instance (fenêtre fixe simple). Suffisant
 * pour un déploiement mono-instance (standalone) ; pour du multi-instance,
 * remplacer par un store partagé (Redis/Upstash) sans changer l'appelant.
 *
 * Clé = IP résolue par `resolveClientIp()` (CF-Connecting-IP, sinon le maillon
 * X-Forwarded-For ajouté par le dernier proxy de confiance — jamais le premier,
 * forgeable), IPv6 ramenée au /64, longueur bornée.
 *
 * Mémoire strictement bornée : au plus `MAX_BUCKETS` entrées. Les entrées
 * expirées sont purgées périodiquement (quelle que soit la taille de la Map,
 * y compris pour des fenêtres d'une heure) ; si la Map est encore pleine,
 * l'entrée la moins récemment utilisée est évincée (LRU, ordre d'insertion de
 * la Map). Choix « fail-open » assumé : sous inondation de clés distinctes,
 * un compteur peut être perdu, mais le service n'est jamais refusé à tous.
 */

type Bucket = { count: number; resetAt: number };

export const MAX_BUCKETS = 20_000;
const SWEEP_INTERVAL_MS = 30_000;
const MAX_ACTION_LENGTH = 64;

export interface RateLimiter {
  /** `true` si autorisé, `false` si la limite est atteinte pour `key`. */
  hit(key: string, limit: number, windowMs: number, now?: number): boolean;
  readonly size: number;
}

/** Fabrique exposée pour les tests (état isolé). */
export function createRateLimiter(maxBuckets = MAX_BUCKETS): RateLimiter {
  const buckets = new Map<string, Bucket>();
  let lastSweep = 0;

  function sweep(now: number) {
    for (const [key, bucket] of buckets) {
      if (now >= bucket.resetAt) buckets.delete(key);
    }
    lastSweep = now;
  }

  return {
    get size() {
      return buckets.size;
    },
    hit(key, limit, windowMs, now = Date.now()) {
      // Purge à intervalle fixe seulement : balayer à chaque requête quand la
      // Map est pleine coûterait O(n) par appel sous inondation (DoS CPU) —
      // le plafond est tenu par l'éviction LRU en O(1) ci-dessous.
      if (now - lastSweep >= SWEEP_INTERVAL_MS) sweep(now);

      const bucket = buckets.get(key);
      if (bucket && now < bucket.resetAt) {
        // Rafraîchit la position LRU (réinsertion en fin d'ordre).
        buckets.delete(key);
        buckets.set(key, bucket);
        if (bucket.count >= limit) return false;
        bucket.count += 1;
        return true;
      }

      if (bucket) buckets.delete(key);
      while (buckets.size >= maxBuckets) {
        const oldest = buckets.keys().next().value;
        if (oldest === undefined) break;
        buckets.delete(oldest);
      }
      buckets.set(key, { count: 1, resetAt: now + windowMs });
      return limit >= 1;
    },
  };
}

const limiter = createRateLimiter();

/**
 * Renvoie `true` si la requête est autorisée, `false` si la limite est atteinte.
 * @param action identifiant de l'action (ex: "register-client")
 * @param limit  nombre de requêtes autorisées par fenêtre
 * @param windowMs durée de la fenêtre en millisecondes
 */
export async function rateLimit(
  action: string,
  limit = 5,
  windowMs = 60_000,
): Promise<boolean> {
  const ip = resolveClientIp(await headers());
  const key = `${action.slice(0, MAX_ACTION_LENGTH)}:${rateLimitKeyForIp(ip)}`;
  return limiter.hit(key, limit, windowMs);
}
