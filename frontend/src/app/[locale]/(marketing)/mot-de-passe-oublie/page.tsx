import { getTranslations } from "next-intl/server";
import { Badge, HeroBackground, Reveal } from "@/components/ui";
import { ForgotPasswordForm } from "@/components/sections/ForgotPasswordForm";
import { getCurrentUser } from "@/lib/auth/session";
import { redirect } from "@/i18n/navigation";

export default async function ForgotPasswordPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  const user = await getCurrentUser();
  if (user) {
    redirect({ href: "/compte", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.forgotPassword" });

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-12">
        <HeroBackground />
        <div className="relative mx-auto max-w-[1120px]">
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          <h1
            className="hero-in mb-5 max-w-[24ch] text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]"
            style={{ animationDelay: "0.08s" }}
          >
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p
            className="hero-in max-w-[56ch] text-[1.05rem] opacity-75"
            style={{ animationDelay: "0.16s" }}
          >
            {t("subtitle")}
          </p>
        </div>
      </section>

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[440px]">
          <Reveal delay={0}>
            <ForgotPasswordForm />
          </Reveal>
        </div>
      </section>
    </>
  );
}
