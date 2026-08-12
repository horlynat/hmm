import { getTranslations } from "next-intl/server";
import { SupportTicketForm } from "@/components/sections/SupportTicketForm";

export default async function SupportTicketNewPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "supportTicket.form" });

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[560px]">
        <p className="mb-2 text-sm font-semibold uppercase tracking-wide text-brand-primary">
          {t("eyebrow")}
        </p>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">
          {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
        </h1>
        <p className="mb-6 opacity-70">{t("sub")}</p>
        <SupportTicketForm />
      </div>
    </section>
  );
}
