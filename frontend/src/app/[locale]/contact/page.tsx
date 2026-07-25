"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Badge } from "@/components/ui";
import { QuoteWizard, AppointmentForm } from "@/components/sections";

export default function ContactPage() {
  const t = useTranslations("contact");
  const [mode, setMode] = useState<"devis" | "rdv">("devis");

  const activeStyle = {
    background:
      "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 70%)",
  };

  return (
    <>
      <section className="px-6 pt-14 pb-8 text-center">
        <div className="mx-auto max-w-[1120px]">
          <Badge variant="accent" className="mb-4">
            {t("eyebrow")}
          </Badge>
          <h1 className="mx-auto mb-5 max-w-[24ch] text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p className="mx-auto max-w-[56ch] text-[1.05rem] opacity-75">{t("sub")}</p>

          <div className="mx-auto mt-8 flex flex-wrap justify-center gap-3">
            <button
              type="button"
              onClick={() => setMode("devis")}
              className={clsx(
                "flex items-center gap-2 rounded-[var(--radius-md)] border px-6 py-3.5 text-left font-semibold",
                mode === "devis"
                  ? "border-transparent text-white"
                  : "border-[var(--border-soft)] bg-bg-card",
              )}
              style={mode === "devis" ? activeStyle : undefined}
            >
              <span aria-hidden="true">📝</span>
              <span>
                {t("modeDevis")}
                <span className="block text-xs font-normal opacity-75">
                  {t("modeDevisSub")}
                </span>
              </span>
            </button>
            <button
              type="button"
              onClick={() => setMode("rdv")}
              className={clsx(
                "flex items-center gap-2 rounded-[var(--radius-md)] border px-6 py-3.5 text-left font-semibold",
                mode === "rdv"
                  ? "border-transparent text-white"
                  : "border-[var(--border-soft)] bg-bg-card",
              )}
              style={mode === "rdv" ? activeStyle : undefined}
            >
              <span aria-hidden="true">🗓️</span>
              <span>
                {t("modeRdv")}
                <span className="block text-xs font-normal opacity-75">
                  {t("modeRdvSub")}
                </span>
              </span>
            </button>
          </div>
        </div>
      </section>

      <section className="px-6 py-10">
        {mode === "devis" ? (
          <QuoteWizard />
        ) : (
          <div className="mx-auto max-w-[640px]">
            <AppointmentForm />
          </div>
        )}
      </section>
    </>
  );
}
