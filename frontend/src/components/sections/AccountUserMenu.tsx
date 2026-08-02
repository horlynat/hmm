"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Link } from "@/i18n/navigation";
import { getAvatarUrl } from "@/lib/media";
import { LogoutButton } from "./LogoutButton";

interface AccountUserMenuProps {
  user: { fullName: string | null; email: string; profileImage: string | null };
}

/** Avatar + menu déroulant (profil, déconnexion) dans `AccountHeader` — même pattern outside-click/Escape que `LocaleSwitcher`. */
export function AccountUserMenu({ user }: AccountUserMenuProps) {
  const t = useTranslations("auth.account");
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    function onClick(e: MouseEvent) {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onClick);
    };
  }, [open]);

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={t("header.userMenu")}
        className="flex items-center gap-2 rounded-full border border-(--border-neutral) bg-bg-card py-1 pl-1 pr-2.5 transition-colors hover:border-brand-primary"
      >
        {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend, hors domaines optimisables par next/image sans config supplémentaire */}
        <img
          src={getAvatarUrl(user)}
          alt=""
          className="h-7 w-7 shrink-0 rounded-full object-cover"
        />
        <span className="hidden max-w-[140px] truncate text-sm font-semibold md:inline">
          {user.fullName ?? user.email}
        </span>
        <svg
          width="10"
          height="10"
          viewBox="0 0 10 10"
          aria-hidden="true"
          className={clsx("hidden transition-transform duration-150 md:block", open && "rotate-180")}
        >
          <path
            d="M2 3.5 5 6.5 8 3.5"
            stroke="currentColor"
            strokeWidth="1.4"
            strokeLinecap="round"
            strokeLinejoin="round"
            fill="none"
          />
        </svg>
      </button>

      {open && (
        <div
          role="menu"
          aria-label={t("header.userMenu")}
          className="absolute right-0 top-[calc(100%+6px)] z-10 min-w-[180px] overflow-hidden rounded-md border border-(--border-neutral) bg-bg-default shadow-lg"
        >
          <Link
            href="/compte/profil"
            role="menuitem"
            onClick={() => setOpen(false)}
            className="block px-3.5 py-2.5 text-left text-sm font-semibold hover:bg-(--color-surface-muted)"
          >
            {t("nav.profile")}
          </Link>
          <div className="border-t border-(--border-neutral) p-1.5">
            <LogoutButton className="w-full rounded-sm px-2 py-2 text-left text-sm font-semibold text-danger hover:bg-danger/10" />
          </div>
        </div>
      )}
    </div>
  );
}
