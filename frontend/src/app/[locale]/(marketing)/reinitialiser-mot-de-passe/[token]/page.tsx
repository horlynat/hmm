import { getTranslations } from "next-intl/server";
import { ResetPasswordForm } from "@/components/sections/ResetPasswordForm";

export default async function ResetPasswordPage({
  params,
}: {
  params: Promise<{ locale: string; token: string }>;
}) {
  const { locale, token } = await params;
  const t = await getTranslations({ locale, namespace: "auth.resetPassword" });

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[440px]">
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
        <p className="mb-6 opacity-70">{t("subtitle")}</p>
        <ResetPasswordForm token={token} />
      </div>
    </section>
  );
}
