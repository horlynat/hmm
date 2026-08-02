import { getTranslations } from "next-intl/server";
import { PasswordChangeForm } from "@/components/sections/PasswordChangeForm";
import { getCurrentUser } from "@/lib/auth/session";

export default async function MotDePassePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.changePassword" });

  return (
    <div className="max-w-160 space-y-6">
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
        <p className="opacity-70">{t("subtitle")}</p>
      </div>

      <PasswordChangeForm />
    </div>
  );
}
