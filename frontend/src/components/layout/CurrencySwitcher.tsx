"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import clsx from "clsx";
import { CURRENCIES, CURRENCY_COOKIE, DEFAULT_CURRENCY, isCurrency, type Currency } from "@/lib/currency/config";
import { setCurrency } from "@/lib/currency/actions";

/** Lit le cookie côté client (non httpOnly, volontairement — cf. actions.ts). */
function readCurrencyCookie(): Currency {
  const match = document.cookie.match(new RegExp(`(?:^|; )${CURRENCY_COOKIE}=([^;]*)`));
  const value = match ? decodeURIComponent(match[1]) : undefined;
  return isCurrency(value) ? value : DEFAULT_CURRENCY;
}

export function CurrencySwitcher() {
  const router = useRouter();
  const [currency, setCurrencyState] = useState<Currency>(DEFAULT_CURRENCY);
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    // Différé au frame suivant plutôt qu'un setState synchrone dans l'effet
    // (cascading render) : cf. règle react-hooks/set-state-in-effect, même
    // idiome que template.tsx.
    const raf = requestAnimationFrame(() => setCurrencyState(readCurrencyCookie()));
    return () => cancelAnimationFrame(raf);
  }, []);

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

  async function switchTo(next: Currency) {
    setOpen(false);
    setCurrencyState(next);
    await setCurrency(next);
    router.refresh();
  }

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={`Devise : ${currency}`}
        className="flex items-center gap-1 rounded-full border border-[var(--border-soft)] bg-bg-card px-2.5 py-1.5 font-mono text-xs uppercase text-brand-primary transition-colors hover:border-brand-primary"
      >
        {currency}
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
          aria-label="Devise d'affichage"
          className="absolute right-0 top-[calc(100%+6px)] z-10 min-w-[100px] overflow-hidden rounded-[var(--radius-md)] border border-[var(--border-softer)] bg-bg-default shadow-lg"
        >
          {CURRENCIES.map((c) => {
            const active = c === currency;
            return (
              <button
                key={c}
                type="button"
                role="menuitemradio"
                aria-checked={active}
                onClick={() => switchTo(c)}
                className={clsx(
                  "flex w-full items-center justify-between px-3.5 py-2.5 text-left text-sm transition-colors",
                  active
                    ? "bg-brand-primary/10 font-semibold text-brand-primary"
                    : "hover:bg-brand-dark/5 dark:hover:bg-white/5",
                )}
              >
                {c}
                {active && <span aria-hidden="true">✓</span>}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
