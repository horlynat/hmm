"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Badge, Card, HeroBackground, Reveal } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import { QuoteWizard } from "@/components/sections/QuoteWizard";
import { AppointmentForm } from "@/components/sections/AppointmentForm";
import { FreelanceForm } from "@/components/sections/FreelanceForm";

type Mode = "devis" | "rdv" | "projet" | "freelance";
type ModeMessageKey = "modeDevis" | "modeRdv" | "modeProjet" | "modeFreelance";
type ModeSubMessageKey = "modeDevisSub" | "modeRdvSub" | "modeProjetSub" | "modeFreelanceSub";

const MODES: { value: Mode; icon: string; labelKey: ModeMessageKey; subKey: ModeSubMessageKey }[] = [
  { value: "devis", icon: "📝", labelKey: "modeDevis", subKey: "modeDevisSub" },
  { value: "rdv", icon: "🗓️", labelKey: "modeRdv", subKey: "modeRdvSub" },
  { value: "projet", icon: "🤝", labelKey: "modeProjet", subKey: "modeProjetSub" },
  { value: "freelance", icon: "💼", labelKey: "modeFreelance", subKey: "modeFreelanceSub" },
];

export function ContactPageClient() {
  const t = useTranslations("contact");
  const [mode, setMode] = useState<Mode>("devis");

  const activeStyle = {
    background:
      "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 70%)",
  };

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />
        <div className="relative mx-auto max-w-[1120px]">
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          {/* Pas d'animation ici : candidat LCP le plus probable de la page. */}
          <h1 className="mb-5 max-w-[24ch] text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p
            className="hero-in mb-7 max-w-[56ch] text-[1.05rem] opacity-75"
            style={{ animationDelay: "0.16s" }}
          >
            {t("sub")}
          </p>
          <div className="hero-in flex flex-wrap gap-2.5" style={{ animationDelay: "0.24s" }}>
            <Badge variant="accent">Symfony</Badge>
            <Badge variant="accent">API Platform</Badge>
            <Badge variant="accent">Next.js</Badge>
            <Badge variant="outline">Assistant IA</Badge>
            <Badge variant="outline">Cybersécurité</Badge>
          </div>

          <div
            className="hero-in mt-8 flex flex-wrap gap-3"
            style={{ animationDelay: "0.32s" }}
          >
            {MODES.map(({ value, icon, labelKey, subKey }) => (
              <button
                key={value}
                type="button"
                onClick={() => setMode(value)}
                className={clsx(
                  "flex items-center gap-2 rounded-[var(--radius-md)] border px-6 py-3.5 text-left font-semibold",
                  mode === value
                    ? "border-transparent text-white"
                    : "border-[var(--border-soft)] bg-bg-card",
                )}
                style={mode === value ? activeStyle : undefined}
              >
                <span aria-hidden="true">{icon}</span>
                <span>
                  {t(labelKey)}
                  <span className="block text-xs font-normal opacity-75">{t(subKey)}</span>
                </span>
              </button>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-10">
        {mode === "devis" && (
          <Reveal delay={0}>
            <QuoteWizard />
          </Reveal>
        )}

        {mode === "rdv" && (
          <Reveal delay={0} className="mx-auto max-w-[640px]">
            <AppointmentForm />
          </Reveal>
        )}

        {mode === "projet" && (
          <Reveal delay={0} className="mx-auto max-w-[760px]">
            <Card variant="soft" className="p-8 text-center">
              <Badge variant="accent">{t("projet.badge")}</Badge>
              <h3
                className="mb-3 mt-3.5 text-lg font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("projet.title")}
              </h3>
              <p className="mx-auto mb-8 max-w-[52ch] text-sm opacity-70">{t("projet.text")}</p>
              <ol className="mb-8 grid grid-cols-1 gap-4 text-left sm:grid-cols-2">
                {[
                  [t("projet.step1Title"), t("projet.step1Desc")],
                  [t("projet.step2Title"), t("projet.step2Desc")],
                  [t("projet.step3Title"), t("projet.step3Desc")],
                  [t("projet.step4Title"), t("projet.step4Desc")],
                ].map(([title, desc], i) => (
                  <li
                    key={title}
                    className="rounded-[var(--radius-md)] border border-[var(--border-softer)] bg-bg-default p-4"
                  >
                    <div
                      className="mb-1.5 text-xl font-extrabold text-[var(--color-accent-text)]"
                      style={{ fontFamily: "var(--font-heading)" }}
                    >
                      {String(i + 1).padStart(2, "0")}
                    </div>
                    <div className="mb-1 text-sm font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                      {title}
                    </div>
                    <div className="text-xs opacity-70">{desc}</div>
                  </li>
                ))}
              </ol>
              <Link href="/inscription" className="btn-primary w-full">
                {t("projet.cta")}
              </Link>
              <p className="mt-4 text-sm opacity-70">
                {t("projet.haveAccount")}{" "}
                <Link href="/connexion" className="font-semibold text-brand-primary hover:underline">
                  {t("projet.loginLink")}
                </Link>
              </p>
            </Card>
          </Reveal>
        )}

        {mode === "freelance" && (
          <Reveal delay={0} className="mx-auto max-w-[640px]">
            <Card variant="soft" className="mb-6 p-8">
              <Badge variant="accent">{t("freelance.badge")}</Badge>
              <h3
                className="mb-3 mt-3.5 text-lg font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("freelance.title")}
              </h3>
              <p className="text-sm opacity-70">{t("freelance.text")}</p>
              <Link
                href="/freelances"
                className="mt-3 inline-block text-sm font-semibold text-brand-primary hover:underline"
              >
                {t("freelance.linkMore")}
              </Link>
            </Card>
            <FreelanceForm />
          </Reveal>
        )}
      </section>
    </>
  );
}
