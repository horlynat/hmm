import { getTranslations } from "next-intl/server";
import { routing } from "@/i18n/routing";
import { Skeleton } from "@/components/ui";

/**
 * Fallback de chargement affiché pendant le rendu/fetch des pages (Suspense).
 * Squelette générique (hero + grille de cartes) annoncé aux lecteurs d'écran.
 * `params` n'est pas fiable ici : ce fichier spécial peut être rendu avant
 * que les segments dynamiques ne soient résolus (constaté au build :
 * `params` est `undefined`, pas juste vide) — d'où le repli sur la locale
 * par défaut plutôt qu'un `await params` non protégé.
 */
export default async function Loading({
  params,
}: {
  params?: Promise<{ locale?: string }>;
}) {
  const locale = (await params)?.locale ?? routing.defaultLocale;
  const t = await getTranslations({ locale, namespace: "common" });

  return (
    <div role="status" aria-busy="true" className="px-6 py-16">
      <span className="sr-only">{t("loading")}</span>
      <div className="mx-auto max-w-[1120px]">
        {/* Hero */}
        <Skeleton className="mb-4 h-6 w-32" />
        <Skeleton className="mb-3 h-12 w-3/4 max-w-[560px]" />
        <Skeleton className="mb-8 h-5 w-2/3 max-w-[460px]" />

        {/* Grille de cartes */}
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="soft-card p-6">
              <Skeleton className="mb-4 h-40 w-full" />
              <Skeleton className="mb-2 h-5 w-2/3" />
              <Skeleton className="h-4 w-full" />
              <Skeleton className="mt-2 h-4 w-4/5" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
