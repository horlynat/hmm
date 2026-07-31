"use client";

import { useEffect, useRef, useState } from "react";
import { useLocale } from "next-intl";
import { useParams } from "next/navigation";
import clsx from "clsx";
import { usePathname, useRouter } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

const LOCALE_NAMES: Record<string, string> = { fr: "Français", en: "English" };

export function LocaleSwitcher() {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const params = useParams();
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

  function switchTo(nextLocale: (typeof routing.locales)[number]) {
    setOpen(false);
    router.replace(
      // @ts-expect-error -- pathname vient d'une route connue de next-intl
      { pathname, params },
      { locale: nextLocale },
    );
  }

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={LOCALE_NAMES[locale] ?? locale}
        className="flex items-center gap-1 rounded-full border border-[var(--border-soft)] bg-bg-card px-2.5 py-1.5 font-mono text-xs uppercase text-brand-primary transition-colors hover:border-brand-primary"
      >
        {locale}
        <svg
          width="10"
          height="10"
          viewBox="0 0 10 10"
          aria-hidden="true"
          className={clsx("transition-transform duration-150", open && "rotate-180")}
        >
          <path d="M2 3.5 5 6.5 8 3.5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" fill="none" />
        </svg>
      </button>

      {open && (
        <div
          role="menu"
          aria-label={LOCALE_NAMES[locale] ?? locale}
          className="absolute right-0 top-[calc(100%+6px)] z-10 min-w-[140px] overflow-hidden rounded-[var(--radius-md)] border border-[var(--border-softer)] bg-bg-default shadow-lg"
        >
          {routing.locales.map((l) => {
            const active = l === locale;
            return (
              <button
                key={l}
                type="button"
                role="menuitemradio"
                aria-checked={active}
                lang={l}
                onClick={() => switchTo(l)}
                className={clsx(
                  "flex w-full items-center justify-between px-3.5 py-2.5 text-left text-sm transition-colors",
                  active
                    ? "bg-brand-primary/10 font-semibold text-brand-primary"
                    : "hover:bg-brand-dark/5 dark:hover:bg-white/5",
                )}
              >
                {LOCALE_NAMES[l] ?? l}
                {active && <span aria-hidden="true">✓</span>}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
