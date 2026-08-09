"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Logo } from "@/components/ui";
import { ThemeToggle, LocaleSwitcher, CurrencySwitcher } from "@/components/layout";
import { AccountUserMenu } from "./AccountUserMenu";

interface AccountHeaderProps {
  user: { fullName: string | null; email: string; profileImage: string | null };
  menuOpen: boolean;
  onToggleMenu: () => void;
}

/**
 * Header dédié à l'espace compte (client/freelance) — distinct du header du
 * site vitrine (`components/layout/Header.tsx`) : plat, non sticky-shrink,
 * porte le déclencheur du tiroir de nav mobile en plus des contrôles compte.
 */
export function AccountHeader({ user, menuOpen, onToggleMenu }: AccountHeaderProps) {
  const t = useTranslations("auth.account");
  const tc = useTranslations("common");

  return (
    <header className="sticky top-0 z-40 border-b border-(--border-neutral) bg-bg-default">
      <div className="mx-auto flex max-w-[1120px] items-center justify-between gap-3 px-6 py-3">
        <div className="flex items-center gap-3">
          <button
            type="button"
            aria-expanded={menuOpen}
            aria-controls="account-drawer"
            onClick={onToggleMenu}
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-(--border-neutral) bg-bg-card md:hidden"
          >
            <span aria-hidden="true">☰</span>
            <span className="sr-only">{tc("openMenu")}</span>
          </button>
          <Link
            href="/"
            aria-label={t("header.backToSite")}
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-sm bg-brand-primary text-(--color-on-brand-primary)"
          >
            <Logo className="h-5 w-5" />
          </Link>
          <span className="hidden text-sm font-semibold sm:inline">{t("nav.title")}</span>
        </div>

        <div className="flex items-center gap-2">
          <ThemeToggle />
          <LocaleSwitcher />
          <CurrencySwitcher />
          <AccountUserMenu user={user} />
        </div>
      </div>
    </header>
  );
}
