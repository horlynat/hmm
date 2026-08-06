import { getTranslations } from "next-intl/server";
import { VerifyEmailStatus } from "@/components/sections/VerifyEmailStatus";

export default async function VerifyEmailTokenPage({
  params,
}: {
  params: Promise<{ locale: string; token: string }>;
}) {
  const { locale, token } = await params;
  const t = await getTranslations({ locale, namespace: "auth.verificationEmail" });

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[440px]">
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("confirmTitle")}</h1>
        <p className="mb-6 opacity-70">{t("confirmSubtitle")}</p>
        <VerifyEmailStatus token={token} />
      </div>
    </section>
  );
}
