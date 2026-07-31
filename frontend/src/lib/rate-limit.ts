import "server-only";
import { headers } from "next/headers";

/**
 * Limiteur de débit basique, en mémoire, par IP + action. Objectif : freiner le
 * spam de formulaires (inscriptions, devis, contact) en complément du honeypot.
 *
 * Limites : l'état est local à l'instance (fenêtre glissante simple). Suffisant
 * pour un déploiement mono-instance (standalone) ; pour du multi-instance,
 * remplacer par un store partagé (Redis/Upstash) sans changer l'appelant.
 */

type Bucket = { count: number; resetAt: number };

const buckets = new Map<string, Bucket>();
const MAX_BUCKETS = 10_000;

async function clientIp(): Promise<string> {
  const h = await headers();
  const forwarded = h.get("x-forwarded-for");
  if (forwarded) return forwarded.split(",")[0].trim();
  return h.get("x-real-ip") ?? "unknown";
}

/** Purge paresseuse des entrées expirées pour borner la mémoire. */
function sweep(now: number) {
  if (buckets.size < MAX_BUCKETS) return;
  for (const [key, bucket] of buckets) {
    if (now > bucket.resetAt) buckets.delete(key);
  }
}

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
  const now = Date.now();
  sweep(now);

  const key = `${action}:${await clientIp()}`;
  const bucket = buckets.get(key);

  if (!bucket || now > bucket.resetAt) {
    buckets.set(key, { count: 1, resetAt: now + windowMs });
    return true;
  }
  if (bucket.count >= limit) return false;
  bucket.count += 1;
  return true;
}
