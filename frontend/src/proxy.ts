import createMiddleware from "next-intl/middleware";
import { NextResponse, type NextRequest } from "next/server";
import { routing } from "@/i18n/routing";
import { buildCsp } from "@/lib/csp";

const intlMiddleware = createMiddleware(routing);
const isDev = process.env.NODE_ENV === "development";

// /contact n'a pas de traduction de chemin (routing.pathnames), donc une
// requête déjà bien formée (/fr/contact, /en/contact) ne déclenche ni
// redirect ni rewrite côté next-intl — seul ce cas permet d'injecter le
// nonce dans les request headers avant le rendu de la page (cf. plus bas).
const strictNoncePathPattern = new RegExp(`^/(${routing.locales.join("|")})/contact/?$`);

/**
 * CSP par requête (cf. src/lib/csp.ts) : posée ici plutôt que dans
 * next.config.ts pour pouvoir varier — nonce strict sur /contact (rendu
 * dynamique, cf. `force-dynamic` sur cette page), CSP par défaut ailleurs.
 */
export function proxy(request: NextRequest) {
  const intlResponse = intlMiddleware(request);
  const isRedirectOrRewrite =
    (intlResponse.status >= 300 && intlResponse.status < 400) ||
    intlResponse.headers.has("x-middleware-rewrite");

  const needsStrictCsp =
    !isRedirectOrRewrite && strictNoncePathPattern.test(request.nextUrl.pathname);

  if (!needsStrictCsp) {
    intlResponse.headers.set("Content-Security-Policy", buildCsp({ isDev }));
    return intlResponse;
  }

  // Le nonce doit être lisible côté layout via `headers()` — il faut donc
  // qu'il arrive dans les *request* headers vus par le rendu de la page, pas
  // seulement dans la réponse. Next.js lit lui-même le nonce qu'il applique à
  // ses propres scripts depuis le header CSP des *request* headers (pas la
  // réponse) — la CSP doit donc être posée sur les deux (cf. doc officielle).
  // On reconstruit la réponse next-intl (ici un simple pass-through, cf.
  // commentaire ci-dessus) en conservant ses effets (cookie de locale) mais
  // avec les request headers modifiés.
  
  const nonce = Buffer.from(crypto.randomUUID()).toString("base64");
  const cspHeader = buildCsp({ isDev, nonce });
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("Content-Security-Policy", cspHeader);

  const response = NextResponse.next({ request: { headers: requestHeaders } });

  // `NextResponse.next({ request: { headers } })` encode ses changements de
  // *request* headers dans `x-middleware-override-headers` (liste des noms) +
  // `x-middleware-request-<nom>` (valeurs) — next-intl produit sa propre
  // liste (locale, cookie...). Un simple `.set()` par-dessus écraserait cette
  // liste au lieu de la fusionner, et Next.js ignorerait alors silencieusement
  // les request headers qu'on vient d'ajouter (nonce, CSP) car absents de la
  // liste effective.

  const overrideNames = new Set(
    (response.headers.get("x-middleware-override-headers") ?? "")
      .split(",")
      .map((n) => n.trim())
      .filter(Boolean),
  );
  intlResponse.headers.forEach((value, key) => {
    const lowerKey = key.toLowerCase();
    if (lowerKey === "content-security-policy") return;
    if (lowerKey === "x-middleware-override-headers") {
      value.split(",").forEach((n) => overrideNames.add(n.trim()));
      return;
    }
    response.headers.set(key, value);
  });
  if (overrideNames.size > 0) {
    response.headers.set("x-middleware-override-headers", [...overrideNames].join(","));
  }

  intlResponse.cookies.getAll().forEach((cookie) => response.cookies.set(cookie));
  response.headers.set("Content-Security-Policy", cspHeader);

  return response;
}

export const config = {
  matcher: ["/((?!api|_next|_vercel|.*\\..*).*)"],
};
