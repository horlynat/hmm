/**
 * Constantes partagées de l'authentification "comptes public" (client,
 * freelance, pro, collaborateur). L'authentification passe par le JWT émis par
 * le backend Symfony (POST /api/login_check) ; le token n'est stocké QUE dans
 * un cookie httpOnly, jamais exposé au JS client — toute lecture/écriture se
 * fait côté serveur (Server Actions / Server Components).
 */

/** Base de l'API Symfony (inclut déjà le préfixe `/api`). */
export const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api";

/** Nom du cookie httpOnly portant le JWT. */
export const SESSION_COOKIE = "hmm_token";
