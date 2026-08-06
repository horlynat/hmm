"use client";

import { useTransition } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { logout } from "@/lib/auth/actions";

export function LogoutButton({ className }: { className?: string }) {
  const t = useTranslations("auth.account");
  const router = useRouter();
  const [pending, startTransition] = useTransition();

  function handleLogout() {
    startTransition(async () => {
      await logout();
      router.push("/connexion");
      router.refresh();
    });
  }

  return (
    <button
      type="button"
      className={className ?? "btn-secondary w-full"}
      disabled={pending}
      onClick={handleLogout}
    >
      {pending ? "…" : t("nav.logout")}
    </button>
  );
}
