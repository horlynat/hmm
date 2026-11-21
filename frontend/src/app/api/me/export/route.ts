import { NextResponse, type NextRequest } from "next/server";
import { hasLocale } from "next-intl";
import { routing } from "@/i18n/routing";
import { getPathname } from "@/i18n/navigation";
import { backendFetch, isTwoFactorRequired } from "@/lib/api/backend";
import { getToken } from "@/lib/auth/session";
import { mustSetUpTwoFactor } from "@/lib/auth/two-factor";
import type { SessionUser } from "@/lib/types";

/** Jamais de cache (navigateur ni intermédiaire) pour des données personnelles. */
const NO_STORE = { "Cache-Control": "no-store, private", Pragma: "no-cache" } as const;

/**
 * Export RGPD des données du compte courant : proxy authentifié vers
 * GET /api/me (Bearer token lu depuis le cookie httpOnly, jamais exposé au
 * client), renvoyé en téléchargement direct plutôt qu'en JSON brut affiché.
 *
 * 2FA obligatoire : GET /api/me est exempté de la règle `2fa_required` côté
 * backend (le layout /compte en a besoin pour afficher l'écran d'activation),
 * donc ce relais ne peut pas compter sur un 403 du backend — il applique
 * lui-même la règle (mustSetUpTwoFactor) et redirige vers la mise en place
 * de la 2FA, comme le ferait le layout. Le 403 `2fa_required` reste géré si
 * le backend venait à l'appliquer aussi ici.
 */
export async function GET(request: NextRequest) {
  const token = await getToken();
  if (!token) {
    return NextResponse.json({ message: "unauthenticated" }, { status: 401, headers: NO_STORE });
  }

  const requestedLocale = request.nextUrl.searchParams.get("locale");
  const locale = hasLocale(routing.locales, requestedLocale) ? requestedLocale : routing.defaultLocale;
  const twoFactorSetupUrl = new URL(getPathname({ href: "/compte/securite", locale }), request.nextUrl.origin);

  let res: Response;
  try {
    res = await backendFetch("/me", { token, timeoutMs: 15_000 });
  } catch (error) {
    console.error("[export] GET /me failed", error);
    return NextResponse.json({ message: "fetch_failed" }, { status: 502, headers: NO_STORE });
  }

  if (await isTwoFactorRequired(res)) {
    return NextResponse.redirect(twoFactorSetupUrl, { status: 303, headers: NO_STORE });
  }

  if (!res.ok) {
    return NextResponse.json({ message: "fetch_failed" }, { status: res.status === 401 ? 401 : 502, headers: NO_STORE });
  }

  let data: SessionUser;
  try {
    data = (await res.json()) as SessionUser;
  } catch (error) {
    console.error("[export] GET /me invalid JSON", error);
    return NextResponse.json({ message: "fetch_failed" }, { status: 502, headers: NO_STORE });
  }

  if (!Array.isArray(data.roles) || mustSetUpTwoFactor(data)) {
    return NextResponse.redirect(twoFactorSetupUrl, { status: 303, headers: NO_STORE });
  }

  return new NextResponse(JSON.stringify(data, null, 2), {
    headers: {
      ...NO_STORE,
      "Content-Type": "application/json; charset=utf-8",
      "Content-Disposition": 'attachment; filename="mes-donnees.json"',
      "X-Content-Type-Options": "nosniff",
    },
  });
}
