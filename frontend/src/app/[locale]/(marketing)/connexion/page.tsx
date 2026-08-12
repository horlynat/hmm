import { getTranslations } from "next-intl/server";
import { Card, Reveal } from "@/components/ui";
import { PageHero } from "@/components/sections/PageHero";
import { LoginForm } from "@/components/sections/LoginForm";
import { getCurrentUser } from "@/lib/auth/session";
import { redirect } from "@/i18n/navigation";

export default async function ConnexionPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  // Déjà connecté → espace compte.
  const user = await getCurrentUser();
  if (user) {
    redirect({ href: "/compte", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.login" });

  const trustItems: [string, string][] = [
    ["🔒", t("secureBadge1")],
    ["🛡️", t("secureBadge2")],
    ["🔏", t("secureBadge3")],
  ];

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        titleAccent={t("titleAccent")}
        subtitle={t("subtitle")}
        aside={
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
        }
      />

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[440px]">
          <Reveal delay={0}>
            <LoginForm />
          </Reveal>
        </div>
      </section>
    </>
  );
}
