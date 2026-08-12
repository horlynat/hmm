"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Badge, Card, Reveal } from "@/components/ui";
import { PageHero } from "@/components/sections/PageHero";
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

function isMode(value: string | undefined): value is Mode {
  return MODES.some((m) => m.value === value);
}

export function ContactPageClient({ initialMode }: { initialMode?: string }) {
  const t = useTranslations("contact");
  // `initialMode` vient de `?mode=` (cf. contact/page.tsx) — permet à un lien
  // externe (ex. "Proposer mon projet" sur /realisations) d'ouvrir directement
  // le bon panneau plutôt que de retomber sur "Demander un devis" par défaut.
  const [mode, setMode] = useState<Mode>(isMode(initialMode) ? initialMode : "devis");

  const activeStyle = {
    background:
      "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 70%)",
  };

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        titleAccent={t("titleAccent")}
        titleClassName="max-w-[24ch]"
        subtitle={t("sub")}
        subtitleClassName="max-w-[56ch]"
        aside={
          <div className="hero-in grid grid-cols-2 gap-3" style={{ animationDelay: "0.16s" }}>
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
        }
      >
        <div className="hero-in flex flex-wrap gap-2.5" style={{ animationDelay: "0.24s" }}>
          <Badge variant="accent">Symfony</Badge>
          <Badge variant="accent">API Platform</Badge>
          <Badge variant="accent">Next.js</Badge>
          <Badge variant="outline">Assistant IA</Badge>
          <Badge variant="outline">Cybersécurité</Badge>
        </div>
      </PageHero>

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
