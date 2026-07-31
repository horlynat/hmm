import type { ReactNode } from "react";
import { getTranslations } from "next-intl/server";
import { Link, redirect } from "@/i18n/navigation";
import { LogoutButton } from "@/components/sections/LogoutButton";
import { getCurrentUser } from "@/lib/auth/session";

/**
 * Garde serveur de l'espace compte : toute page sous /compte exige une session
 * valide. Sans session (cookie absent, token expiré → 401 sur /api/me),
 * redirection vers la page de connexion.
 */
export default async function CompteLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();

  if (!user) {
    redirect({ href: "/connexion", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.account" });

  return (
    <section className="px-6 py-12">
      <div className="mx-auto grid max-w-[1120px] gap-8 lg:grid-cols-[240px_1fr] lg:items-start">
        <aside className="card space-y-2 lg:sticky lg:top-24">
          <p className="px-2 pb-2 text-xs font-bold uppercase tracking-wider opacity-40">
            {t("nav.title")}
          </p>
          <Link
            href="/compte"
            className="block rounded-lg px-3 py-2 text-sm font-semibold opacity-80 hover:bg-brand-light hover:opacity-100"
          >
            {t("nav.dashboard")}
          </Link>
          <Link
            href="/compte/profil"
            className="block rounded-lg px-3 py-2 text-sm font-semibold opacity-80 hover:bg-brand-light hover:opacity-100"
          >
            {t("nav.profile")}
          </Link>
          <div className="pt-2">
            <LogoutButton />
          </div>
        </aside>

        <div>{children}</div>
      </div>
    </section>
  );
}
