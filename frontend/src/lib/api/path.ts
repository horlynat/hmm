import "server-only";
import { z } from "zod";

/**
 * Construction sûre des chemins d'API (anti-traversée, audit 2026-11-20 F1).
 *
 * Une Server Action est un endpoint HTTP public : ses arguments arrivent du
 * client en JSON, sans aucune garantie de type malgré la signature
 * TypeScript. Interpolé tel quel, `projectId = "1/../../admin/x"` ou un jeton
 * `"../users"` ferait viser au fetch serveur un autre endpoint de l'API
 * interne (avec le Bearer de l'utilisateur, ou depuis l'IP de confiance du
 * conteneur). Deux barrières indépendantes :
 *  1. validation zod stricte de chaque paramètre (entier positif, jeton hex…) ;
 *  2. `encodeURIComponent` systématique de chaque segment via `apiPath`.
 */

/** Identifiant de ressource : entier strictement positif (borne INT signé MySQL). */
export const resourceIdSchema = z.number().int().positive().max(2_147_483_647);

/** Jeton de ticket support invité : 64 caractères hexadécimaux (bin2hex(random_bytes(32)) côté backend). */
export const supportTicketTokenSchema = z.string().regex(/^[a-f0-9]{64}$/);

/** Valide un identifiant ; `null` si invalide (l'appelant renvoie alors invalid_input / notFound). */
export function parseResourceId(value: unknown): number | null {
  const parsed = resourceIdSchema.safeParse(value);
  return parsed.success ? parsed.data : null;
}

/**
 * Paramètre de route `[id]` (toujours une chaîne) → identifiant validé, ou
 * `null`. Refuse tout ce qui n'est pas une suite de chiffres décimaux
 * (`Number("0x1F")`, `Number("1e3")`, `Number(" 4 ")` sont acceptés par
 * `Number()` mais ne désignent pas un identifiant canonique).
 */
export function parseRouteId(value: string): number | null {
  if (!/^[1-9][0-9]{0,9}$/.test(value)) return null;
  return parseResourceId(Number(value));
}

/**
 * Tag de gabarit qui encode chaque valeur interpolée comme UN segment de
 * chemin : `apiPath\`/me/projects/${id}/comments\``. Les parties littérales
 * (écrites dans le code) restent telles quelles.
 */
export function apiPath(strings: TemplateStringsArray, ...values: Array<string | number>): string {
  return strings.reduce((path, literal, i) => {
    if (i === 0) return literal;
    const raw = String(values[i - 1]);
    // `encodeURIComponent` laisse `.` intact, et le parseur d'URL WHATWG
    // (fetch) normalise aussi bien `..` que `%2e%2e` en « dossier parent » :
    // un segment vide, `.` ou `..` est donc refusé explicitement.
    if (raw === "" || raw === "." || raw === "..") {
      throw new Error("apiPath: segment de chemin invalide");
    }
    return `${path}${encodeURIComponent(raw)}${literal}`;
  }, "");
}
