import { getTranslations } from "next-intl/server";
import { routing } from "@/i18n/routing";
import { ButtonLink } from "@/components/ui";

// `params` peut être `undefined` ici (fichier spécial, cf. loading.tsx) —
// repli sur la locale par défaut plutôt qu'un `await params` non protégé.
export default async function NotFound({
  params,
}: {
  params?: Promise<{ locale?: string }>;
}) {
  const locale = (await params)?.locale ?? routing.defaultLocale;
  const t = await getTranslations({ locale, namespace: "notFound" });

  return (
    <section className="px-6 py-24 text-center">
      <div className="mx-auto max-w-[560px]">
        <div className="mb-4 font-mono text-sm uppercase tracking-wide text-brand-primary">
          {t("code")}
        </div>
        <h1 className="mb-4 text-[clamp(1.8rem,4vw,2.6rem)]">{t("title")}</h1>
        <p className="mb-8 opacity-70">{t("sub")}</p>
        <ButtonLink href="/">{t("backHome")}</ButtonLink>
      </div>
    </section>
  );
}
