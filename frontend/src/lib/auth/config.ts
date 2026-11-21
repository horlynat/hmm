import "server-only";

/**
 * Constantes partagées de l'authentification "comptes public" (client,
 * freelance, pro, collaborateur). L'authentification passe par le JWT émis par
 * le backend Symfony (POST /api/login_check) ; le token n'est stocké QUE dans
 * un cookie httpOnly, jamais exposé au JS client — toute lecture/écriture se
 * fait côté serveur (Server Actions / Server Components).
 */

/** Base de l'API Symfony (inclut déjà le préfixe `/api`). */
export const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api";

/** Ancien nom du cookie de session (avant le préfixe `__Host-`) — encore lu, plus jamais écrit. */
export const LEGACY_SESSION_COOKIE = "hmm_token";

/**
 * Fin de la migration douce (secondes Unix, 2026-12-01T00:00:00Z) : en
 * production, l'ancien cookie n'est accepté que s'il porte un JWT émis
 * (`iat`) AVANT cette date. Sans borne, le repli resterait une porte ouverte
 * à la fixation de session que le préfixe `__Host-` doit fermer (un
 * sous-domaine *.horlynat.com peut poser `hmm_token` avec Domain=, pas
 * `__Host-hmm_token`). Les JWT expirant vite, ce repli devient inerte peu
 * après le déploiement — à supprimer ensuite.
 */
export const LEGACY_SESSION_COOKIE_ISSUED_BEFORE = Date.UTC(2026, 11, 1) / 1000;

/**
 * Nom du cookie httpOnly portant le JWT. En production : préfixe `__Host-`,
 * qui oblige le navigateur à n'accepter le cookie que s'il est `Secure`, posé
 * avec `Path=/` et SANS attribut `Domain` — un sous-domaine compromis (ou un
 * service tiers sur *.horlynat.com) ne peut donc ni l'écraser ni le fixer
 * (session fixation / cookie tossing). En dev (HTTP simple), un cookie
 * `__Host-` non Secure serait refusé : on garde le nom historique.
 */
export function sessionCookieName(env: string | undefined = process.env.NODE_ENV): string {
  return env === "production" ? "__Host-hmm_token" : LEGACY_SESSION_COOKIE;
}
