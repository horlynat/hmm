import { getTranslations } from "next-intl/server";
import { ButtonLink } from "@/components/ui";

export default async function NotFound() {
  const t = await getTranslations("notFound");

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
