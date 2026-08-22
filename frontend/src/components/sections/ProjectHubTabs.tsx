"use client";

import { useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";

type TabKey = "mine" | "open" | "requests";

/**
 * Onglets internes du hub "Gestion de projet" (cf. la maquette de refonte
 * validée) : remplace les trois liens d'aside distincts (Mes projets /
 * Gestion de projet / Projets disponibles) par une seule page, le contenu de
 * chaque section restant un Server Component (passé ici en enfant, pas
 * ré-implémenté côté client) — seul le bouton de bascule est interactif.
 */
export function ProjectHubTabs({
  initialTab = "mine",
  mine,
  open,
  requests,
  counts,
}: {
  initialTab?: TabKey;
  mine: ReactNode;
  open: ReactNode;
  requests: ReactNode;
  counts: { mine: number; open: number; requests: number };
}) {
  const t = useTranslations("auth.projectManagement.tabs");
  const [tab, setTab] = useState<TabKey>(initialTab);

  const tabs: { key: TabKey; label: string; count: number }[] = [
    { key: "mine", label: t("mine"), count: counts.mine },
    { key: "open", label: t("open"), count: counts.open },
    { key: "requests", label: t("requests"), count: counts.requests },
  ];

  return (
    <div>
      <div role="tablist" className="mb-6 inline-flex gap-1 rounded-full bg-(--color-surface-muted) p-1">
        {tabs.map((tabItem) => (
          <button
            key={tabItem.key}
            type="button"
            role="tab"
            aria-selected={tab === tabItem.key}
            onClick={() => setTab(tabItem.key)}
            className={clsx(
              "flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition-colors",
              tab === tabItem.key ? "bg-bg-card text-brand-primary shadow-sm" : "text-(--color-muted) hover:text-brand-dark",
            )}
          >
            {tabItem.label}
            {tabItem.count > 0 && (
              <span
                className={clsx(
                  "rounded-full px-1.5 py-0.5 text-[11px] font-bold",
                  tab === tabItem.key ? "bg-brand-primary/10 text-brand-primary" : "bg-(--border-neutral) text-(--color-muted)",
                )}
              >
                {tabItem.count}
              </span>
            )}
          </button>
        ))}
      </div>

      <div role="tabpanel" hidden={tab !== "mine"}>
        {mine}
      </div>
      <div role="tabpanel" hidden={tab !== "open"}>
        {open}
      </div>
      <div role="tabpanel" hidden={tab !== "requests"}>
        {requests}
      </div>
    </div>
  );
}
