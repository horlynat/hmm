import { getTranslations } from "next-intl/server";
import { Badge } from "@/components/ui";
import { ProfileForm } from "@/components/sections/ProfileForm";
import { getCurrentUser } from "@/lib/auth/session";
import { getAvatarUrl } from "@/lib/media";

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
    <div className="max-w-160 space-y-6">
      <div className="flex items-start gap-3.5">
        {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend, hors domaines optimisables par next/image sans config supplémentaire */}
        <img
          src={getAvatarUrl(user)}
          alt=""
          className="h-11 w-11 shrink-0 rounded-full border border-(--border-neutral) object-cover"
        />
        <div className="min-w-0">
          <h1 className="text-[clamp(1.5rem,2.8vw,2.05rem)]">{t("title")}</h1>
          <p className="mt-1 text-sm text-(--color-muted)">{t("subtitle")}</p>
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <span className="text-sm text-(--color-muted)">{user.email}</span>
            <Badge variant="neutral">{user.isVerified ? t("security.emailVerified") : t("security.emailUnverified")}</Badge>
          </div>
        </div>
      </div>

      <ProfileForm user={user} />
    </div>
  );
}
