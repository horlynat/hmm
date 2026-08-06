"use client";

import { useRef, useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { ClientForm } from "./ClientForm";
import { FreelanceForm } from "./FreelanceForm";

const TABS = ["client", "freelance"] as const;
type Tab = (typeof TABS)[number];

export function RegisterTabs() {
  const t = useTranslations("auth.register");
  const [tab, setTab] = useState<Tab>("client");
  const tabRefs = useRef<Record<Tab, HTMLButtonElement | null>>({
    client: null,
    freelance: null,
  });

  // Navigation clavier entre onglets (flèches + Home/End), conforme au motif ARIA « tabs ».
  function onKeyDown(e: React.KeyboardEvent) {
    const idx = TABS.indexOf(tab);
    let next: Tab | null = null;
    if (e.key === "ArrowRight" || e.key === "ArrowDown") next = TABS[(idx + 1) % TABS.length];
    else if (e.key === "ArrowLeft" || e.key === "ArrowUp")
      next = TABS[(idx - 1 + TABS.length) % TABS.length];
    else if (e.key === "Home") next = TABS[0];
    else if (e.key === "End") next = TABS[TABS.length - 1];
    if (next) {
      e.preventDefault();
      setTab(next);
      tabRefs.current[next]?.focus();
    }
  }

  return (
    <div>
      <div
        role="tablist"
        aria-label={t("title")}
        onKeyDown={onKeyDown}
        className="mb-6 flex gap-2 rounded-full border border-[var(--border-soft)] bg-bg-default p-1"
      >
        {TABS.map((value) => {
          const selected = tab === value;
          return (
            <button
              key={value}
              ref={(el) => {
                tabRefs.current[value] = el;
              }}
              type="button"
              role="tab"
              id={`register-tab-${value}`}
              aria-selected={selected}
              aria-controls={`register-panel-${value}`}
              tabIndex={selected ? 0 : -1}
              onClick={() => setTab(value)}
              className={clsx(
                "flex-1 rounded-full px-4 py-2.5 text-sm font-semibold transition-colors",
                selected
                  ? "bg-brand-primary text-[var(--color-on-brand-primary)]"
                  : "text-brand-primary opacity-70 hover:opacity-100",
              )}
            >
              {value === "client" ? t("tabClient") : t("tabFreelance")}
            </button>
          );
        })}
      </div>

      <div
        role="tabpanel"
        id={`register-panel-${tab}`}
        aria-labelledby={`register-tab-${tab}`}
        tabIndex={0}
      >
        {tab === "client" ? <ClientForm /> : <FreelanceForm />}
      </div>
    </div>
  );
}
