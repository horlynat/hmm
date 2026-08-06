import { getTranslations } from "next-intl/server";
import { ResendVerificationEmailForm } from "@/components/sections/ResendVerificationEmailForm";
import { getCurrentUser } from "@/lib/auth/session";
import { redirect } from "@/i18n/navigation";

export default async function VerificationEmailPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  const user = await getCurrentUser();
  if (user) {
    redirect({ href: "/compte", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.verificationEmail" });

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[440px]">
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
        <p className="mb-6 opacity-70">{t("subtitle")}</p>
        <ResendVerificationEmailForm />
      </div>
    </section>
  );
}
