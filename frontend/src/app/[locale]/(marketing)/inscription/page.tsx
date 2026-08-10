import { getTranslations } from "next-intl/server";
import { Badge, Card, HeroBackground, Reveal } from "@/components/ui";
import { RegisterTabs } from "@/components/sections/RegisterTabs";
import { Link } from "@/i18n/navigation";
import { getCurrentUser } from "@/lib/auth/session";
import { redirect } from "@/i18n/navigation";

export default async function InscriptionPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  const user = await getCurrentUser();
  if (user) {
    redirect({ href: "/compte", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.register" });

  const trustItems: [string, string][] = [
    ["🔒", t("secureBadge1")],
    ["🛡️", t("secureBadge2")],
    ["🔏", t("secureBadge3")],
  ];

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />
        <div className="relative mx-auto grid max-w-[1120px] gap-12 md:grid-cols-[1.05fr_0.95fr] md:items-center">
          <div>
            <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
              {t("eyebrow")}
            </Badge>
            {/* Pas d'animation ici : candidat LCP le plus probable de la page. */}
            <h1 className="mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
              {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
            </h1>
            <p
              className="hero-in max-w-[48ch] text-[1.05rem] opacity-75"
              style={{ animationDelay: "0.16s" }}
            >
              {t("subtitle")}
            </p>
          </div>
          <Card variant="soft" className="hero-in p-7" style={{ animationDelay: "0.16s" }}>
            <ul className="list-none space-y-4 p-0">
              {trustItems.map(([icon, label]) => (
                <li key={label} className="flex items-start gap-3">
                  <span
                    aria-hidden="true"
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-lg"
                  >
                    {icon}
                  </span>
                  <span className="pt-1.5 text-sm font-medium">{label}</span>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      </section>

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[560px]">
          <Reveal delay={0}>
            <RegisterTabs />

            <p className="mt-6 text-center text-sm opacity-70">
              {t("haveAccount")}{" "}
              <Link href="/connexion" className="font-semibold text-brand-primary hover:underline">
                {t("loginLink")}
              </Link>
            </p>
          </Reveal>
        </div>
      </section>
    </>
  );
}
