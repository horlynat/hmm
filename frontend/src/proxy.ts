import createMiddleware from "next-intl/middleware";
import { NextRequest, type NextResponse } from "next/server";
import { routing } from "@/i18n/routing";
import { buildCsp, needsStrictCsp } from "@/lib/csp";

const intlMiddleware = createMiddleware(routing);
const isDev = process.env.NODE_ENV === "development";

/**
 * CSP par requête (cf. src/lib/csp.ts) : posée ici plutôt que dans
 * next.config.ts pour pouvoir varier — nonce strict sur les routes
 * dynamiques sensibles, CSP par défaut (`'unsafe-inline'`, compatible
 * SSG/ISR) ailleurs.
 *
 * Le nonce doit être lisible par le layout via `headers()` ET par Next.js,
 * qui lit la CSP des *request* headers pour noncer ses propres scripts : on
 * passe donc à next-intl une requête dont les en-têtes portent déjà
 * `x-nonce` + `Content-Security-Policy`. next-intl recopie TOUS les en-têtes
 * de la requête dans son override (`NextResponse.next/rewrite({ request:
 * { headers } })`), ce qui couvre aussi bien le pass-through (/fr/compte)
 * que la réécriture des chemins traduits (/en/account → /en/compte).
 *
 * Toute valeur `x-nonce` / `Content-Security-Policy` fournie par le CLIENT
 * est supprimée au préalable, sur toutes les routes : un nonce choisi par
 * l'attaquant ne doit jamais atteindre le rendu.
 */
export function proxy(request: NextRequest): NextResponse {
  const requestHeaders = new Headers(request.headers);
  requestHeaders.delete("x-nonce");
  requestHeaders.delete("content-security-policy");

  const strict = needsStrictCsp(request.nextUrl.pathname);
  let cspHeader: string;

  if (strict) {
    const nonce = Buffer.from(crypto.randomUUID()).toString("base64");
    cspHeader = buildCsp({ isDev, nonce });
    requestHeaders.set("x-nonce", nonce);
    requestHeaders.set("Content-Security-Policy", cspHeader);
  } else {
    cspHeader = buildCsp({ isDev });
  }

  // Requête reconstruite à partir de l'URL + en-têtes seulement (sans
  // corps) : next-intl ne lit que l'URL, les en-têtes et les cookies, et
  // cloner un corps de Server Action (POST) pourrait verrouiller son flux.
  const intlRequest = new NextRequest(request.url, { method: request.method, headers: requestHeaders });
  const response = intlMiddleware(intlRequest);
  response.headers.set("Content-Security-Policy", cspHeader);
  return response;
}

/**
 * Exclut uniquement les vraies ressources statiques (extension connue en fin
 * de chemin), plus `api/`, `_next/` et `_vercel/`. L'ancien motif `.*\..*`
 * excluait tout chemin contenant un point — un slug « nextjs-16.4 » ou
 * « /fr/compte/x.y » échappait alors à la CSP et au routage i18n.
 */
export const config = {
  matcher: [
    "/((?!(?:api|_next|_vercel)(?:/|$)|.*\\.(?:ico|png|jpe?g|gif|webp|avif|svg|txt|xml|json|webmanifest|woff2?|ttf|otf|eot|map|js|mjs|css|pdf|mp4|webm)$).*)",
  ],
};
