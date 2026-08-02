import { getTranslations } from "next-intl/server";
import { KeyRound } from "lucide-react";
import { PageHeader } from "@/components/ui";
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
      <PageHeader icon={KeyRound} title={t("title")} subtitle={t("subtitle")} />

      <PasswordChangeForm />
    </div>
  );
}
