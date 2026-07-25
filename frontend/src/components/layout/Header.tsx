"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Link, usePathname } from "@/i18n/navigation";
import { navItems } from "@/config/site";
import { Logo, ButtonLink } from "@/components/ui";
import { ThemeToggle } from "./ThemeToggle";
import { LocaleSwitcher } from "./LocaleSwitcher";

export function Header() {
  const t = useTranslations("nav");
  const tc = useTranslations("common");
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-50 border-b border-[var(--border-softer)] bg-bg-default/85 backdrop-blur-md">
      <nav className="mx-auto flex max-w-[1120px] items-center justify-between px-6 py-4">
        <Link
          href="/"
          className="flex items-center gap-2 text-lg font-extrabold"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          <Logo />
          {tc("siteName")}
        </Link>

        <ul
          className={clsx(
            "absolute inset-x-0 top-full flex-col gap-0 border-b border-[var(--border-softer)] bg-bg-default px-6 md:static md:flex md:flex-row md:gap-7 md:border-0 md:bg-transparent md:px-0",
            open ? "flex pb-4" : "hidden md:flex",
          )}
        >
          {navItems.map((item) => (
            <li key={item.href} className="w-full list-none md:w-auto">
              <Link
                href={item.href}
                onClick={() => setOpen(false)}
                className={clsx(
                  "block border-b border-[var(--border-softer)] py-4 text-sm font-semibold md:border-0 md:py-0",
                  pathname === item.href
                    ? "text-brand-primary opacity-100"
                    : "opacity-70 hover:text-brand-primary hover:opacity-100",
                )}
              >
                {t(item.key)}
              </Link>
            </li>
          ))}
        </ul>

        <div className="flex items-center gap-3">
          <ThemeToggle />
          <LocaleSwitcher />
          <button
            type="button"
            aria-label={tc("openMenu")}
            aria-expanded={open}
            onClick={() => setOpen((v) => !v)}
            className="flex h-10 w-10 shrink-0 flex-col items-center justify-center gap-1.5 rounded-full border border-[var(--border-soft)] bg-bg-card md:hidden"
          >
            <span className="h-0.5 w-[18px] rounded bg-brand-dark" />
            <span className="h-0.5 w-[18px] rounded bg-brand-dark" />
            <span className="h-0.5 w-[18px] rounded bg-brand-dark" />
          </button>
          <ButtonLink href="/contact" className="hidden md:inline-flex">
            {tc("ctaConfierProjet")}
          </ButtonLink>
        </div>
      </nav>
    </header>
  );
}
