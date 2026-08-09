"use client";

import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Link, usePathname } from "@/i18n/navigation";
import { navItems } from "@/config/site";
import { Logo, Skeleton } from "@/components/ui";
import { useScrolled } from "@/lib/useScrolled";
import { useAuthStatus } from "@/lib/auth/useAuthStatus";
import { LocaleSwitcher } from "./LocaleSwitcher";

export function Header() {
  const t = useTranslations("nav");
  const tc = useTranslations("common");
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const navRef = useRef<HTMLElement>(null);
  const toggleButtonRef = useRef<HTMLButtonElement>(null);
  const firstLinkRef = useRef<HTMLAnchorElement>(null);
  const scrolled = useScrolled();
  const isAuthenticated = useAuthStatus();

  const accountHref = isAuthenticated ? "/compte" : "/connexion";
  const accountLabel = isAuthenticated ? t("compte") : t("connexion");

  // Ferme par la touche Échap et au clic en dehors du menu (mobile).
  // La navigation via les liens ferme déjà le menu (onClick ci-dessous).
  useEffect(() => {
    if (!open) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setOpen(false);
        // Au clavier uniquement : on rend la main au bouton qui a ouvert le menu.
        toggleButtonRef.current?.focus();
      }
    }
    function onClick(e: MouseEvent) {
      if (navRef.current && !navRef.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onClick);
    };
  }, [open]);

  // À l'ouverture du panneau mobile, déplace le focus sur son premier lien.
  useEffect(() => {
    if (open) firstLinkRef.current?.focus();
  }, [open]);

  return (
    <header
      className={clsx(
        "sticky top-0 z-50 border-b transition-colors duration-300",
        scrolled || open
          ? "border-[var(--border-softer)] bg-bg-default/85 shadow-sm backdrop-blur-md"
          : "border-transparent bg-transparent",
      )}
    >
      <nav
        ref={navRef}
        aria-label={tc("siteName")}
        className="mx-auto flex max-w-[1120px] items-center justify-between px-6 py-4"
      >
        <Link href="/" aria-label={tc("siteName")} className="flex items-center">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-brand-primary text-[var(--color-on-brand-primary)]">
            <Logo className="h-6 w-6" />
          </span>
        </Link>

        <ul
          id="primary-nav"
          className={clsx(
            "absolute inset-x-0 top-full flex-col gap-0 border-b border-[var(--border-softer)] bg-bg-default px-6 md:static md:flex md:flex-row md:items-center md:gap-1 md:border-0 md:bg-transparent md:px-0",
            open ? "flex pb-4" : "hidden md:flex",
          )}
        >
          {navItems.map((item, index) => {
            const active = pathname === item.href;
            const isContact = item.key === "contact";
            return (
              <li key={item.href} className="w-full list-none md:w-auto">
                <Link
                  href={item.href}
                  ref={index === 0 ? firstLinkRef : undefined}
                  onClick={() => setOpen(false)}
                  aria-current={active ? "page" : undefined}
                  className={clsx(
                    "block border-b border-[var(--border-softer)] py-4 text-sm font-semibold transition-colors md:border-0 md:py-2",
                    isContact ? "md:ml-2 md:rounded-full md:px-4" : "md:rounded-md md:px-3",
                    active
                      ? isContact
                        ? "text-[var(--color-badge-accent-text)] opacity-100 md:bg-brand-primary md:text-[var(--color-on-brand-primary)]"
                        : "text-[var(--color-badge-accent-text)] opacity-100 md:bg-brand-primary/10"
                      : isContact
                        ? "opacity-70 hover:text-brand-primary hover:opacity-100 md:border md:border-brand-primary/40 md:bg-brand-primary/10 md:text-brand-primary md:hover:bg-brand-primary/15"
                        : "opacity-70 hover:text-brand-primary hover:opacity-100 md:hover:bg-brand-dark/5 md:dark:hover:bg-white/5",
                  )}
                >
                  {t(item.key)}
                </Link>
              </li>
            );
          })}
          <li className="w-full list-none md:hidden">
            {isAuthenticated === null ? (
              <div className="py-4">
                <Skeleton className="h-4 w-24" />
              </div>
            ) : (
              <Link
                href={accountHref}
                onClick={() => setOpen(false)}
                className="block py-4 text-sm font-semibold text-brand-primary"
              >
                {accountLabel}
              </Link>
            )}
          </li>
        </ul>

        <div className="flex items-center gap-3">
          {isAuthenticated === null ? (
            <Skeleton className="hidden h-4 w-16 md:block" />
          ) : (
            <Link
              href={accountHref}
              className="hidden text-sm font-semibold opacity-70 hover:text-brand-primary hover:opacity-100 md:inline-flex"
            >
              {accountLabel}
            </Link>
          )}
          <LocaleSwitcher />
          <button
            ref={toggleButtonRef}
            type="button"
            aria-label={open ? tc("closeMenu") : tc("openMenu")}
            aria-expanded={open}
            aria-controls="primary-nav"
            onClick={() => setOpen((v) => !v)}
            className="flex h-10 w-10 shrink-0 flex-col items-center justify-center gap-1.5 rounded-full border border-[var(--border-soft)] bg-bg-card md:hidden"
          >
            <span
              className={clsx(
                "h-0.5 w-[18px] rounded bg-brand-dark transition-transform duration-200",
                open && "translate-y-2 rotate-45",
              )}
            />
            <span
              className={clsx(
                "h-0.5 w-[18px] rounded bg-brand-dark transition-opacity duration-200",
                open && "opacity-0",
              )}
            />
            <span
              className={clsx(
                "h-0.5 w-[18px] rounded bg-brand-dark transition-transform duration-200",
                open && "-translate-y-2 -rotate-45",
              )}
            />
          </button>
        </div>
      </nav>
    </header>
  );
}
