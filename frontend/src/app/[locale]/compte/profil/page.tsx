import { getTranslations } from "next-intl/server";
import { ProfileForm } from "@/components/sections/ProfileForm";
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
    <div className="max-w-160">
      <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
      <p className="mb-6 opacity-70">{t("subtitle")}</p>

      <ProfileForm user={user} />
    </div>
  );
}
