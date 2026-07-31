"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Skeleton } from "@/components/ui";
import { useAuthStatus } from "@/lib/auth/useAuthStatus";

export function FooterAccountLink() {
  const t = useTranslations("nav");
  const isAuthenticated = useAuthStatus();

  if (isAuthenticated === null) {
    return <Skeleton className="h-[1em] w-20" />;
  }

  return (
    <Link
      href={isAuthenticated ? "/compte" : "/connexion"}
      className="text-sm transition-colors hover:text-brand-accent"
    >
      {isAuthenticated ? t("compte") : t("connexion")}
    </Link>
  );
}
