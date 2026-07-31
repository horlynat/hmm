import { getTranslations } from "next-intl/server";
import { ProfileForm } from "@/components/sections/ProfileForm";
import { ThemeToggle } from "@/components/layout";
import { getCurrentUser } from "@/lib/auth/session";

export default async function ProfilPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  const t = await getTranslations({ locale, namespace: "auth.profile" });

  if (!user) return null;

  return (
    <div className="max-w-[640px]">
      <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
      <p className="mb-6 opacity-70">{t("subtitle")}</p>

      <div className="mb-8 flex items-center justify-between gap-4 rounded-[var(--radius-md)] border border-[var(--border-soft)] bg-bg-card p-4">
        <div>
          <p className="text-sm font-semibold">{t("appearanceLabel")}</p>
          <p className="text-xs opacity-60">{t("appearanceHint")}</p>
        </div>
        <ThemeToggle />
      </div>

      <ProfileForm user={user} />
    </div>
  );
}
