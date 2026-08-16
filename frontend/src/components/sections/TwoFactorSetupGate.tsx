"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ShieldCheck, KeyRound, Copy, Check, QrCode, Smartphone } from "lucide-react";
import { Card, Alert, Skeleton } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { LogoutButton } from "@/components/sections/LogoutButton";
import { useRouter } from "@/i18n/navigation";
import {
  setupTwoFactor,
  confirmTwoFactorSetup,
  type TwoFactorSetupResult,
} from "@/lib/auth/actions";

type Step = "loading" | "ready" | "load_error" | "recovery";

/** Bouton "copier" générique : icône Copy → Check pendant 1.5s après un clic réussi. */
function CopyButton({ value, label }: { value: string; label: string }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // Presse-papiers indisponible (permissions, contexte non sécurisé) :
      // la valeur reste sélectionnable/copiable manuellement, pas d'erreur bloquante.
    }
  }

  return (
    <button
      type="button"
      onClick={handleCopy}
      aria-label={label}
      className="flex shrink-0 items-center justify-center rounded-md p-1.5 text-(--color-muted) transition hover:bg-(--color-surface-muted) hover:text-brand-primary"
    >
      {copied ? <Check size={16} className="text-success" aria-hidden="true" /> : <Copy size={16} aria-hidden="true" />}
    </button>
  );
}

/**
 * Étape obligatoire affichée par CompteLayout à la place de la page demandée
 * tant que la 2FA n'est pas activée (cf. son commentaire) — jamais une
 * redirection : quelle que soit l'URL /compte/* visée, c'est cet écran qui
 * s'affiche jusqu'à activation effective.
 */
export function TwoFactorSetupGate() {
  const t = useTranslations("auth.profile.security.twoFactorSetup");
  const router = useRouter();

  const [step, setStep] = useState<Step>("loading");
  const [setupData, setSetupData] = useState<{ secret: string; qrCodeDataUri: string } | null>(null);
  const [code, setCode] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      const result: TwoFactorSetupResult = await setupTwoFactor();
      if (cancelled) return;

      if (result.ok) {
        setSetupData({ secret: result.secret, qrCodeDataUri: result.qrCodeDataUri });
        setStep("ready");
      } else {
        setStep("load_error");
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  async function retryLoad() {
    setStep("loading");
    const result = await setupTwoFactor();
    if (result.ok) {
      setSetupData({ secret: result.secret, qrCodeDataUri: result.qrCodeDataUri });
      setStep("ready");
    } else {
      setStep("load_error");
    }
  }

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!setupData) return;

    setError("");
    setSubmitting(true);
    const result = await confirmTwoFactorSetup(setupData.secret, code);
    setSubmitting(false);

    if (result.ok) {
      setRecoveryCodes(result.recoveryCodes);
      setStep("recovery");
      return;
    }

    setError(
      result.error === "invalid_code"
        ? t("invalidCode")
        : result.error === "too_many_attempts"
          ? t("tooManyAttempts")
          : t("error"),
    );
  }

  function onDone() {
    // La page initialement demandée se rend normalement au prochain rendu :
    // getCurrentUser() (server) verra isTwoFactorEnabled=true, CompteLayout
    // n'affichera donc plus cet écran.
    router.refresh();
  }

  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-4 py-12">
      <Card variant="soft" className="w-full overflow-hidden">
        <div className="flex flex-col items-center border-b border-(--border-softer) bg-gradient-to-b from-brand-primary/5 to-transparent px-8 pt-8 pb-6 text-center">
          <div
            aria-hidden="true"
            className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-brand-primary/10"
          >
            <ShieldCheck className="text-brand-primary" size={24} />
          </div>
          <h1 className="text-lg font-semibold">{step === "recovery" ? t("recoveryTitle") : t("title")}</h1>
          <p className="mt-1.5 max-w-xs text-sm text-(--color-muted)">
            {step === "recovery" ? t("recoverySubtitle") : t("subtitle")}
          </p>
        </div>

        <div className="px-8 py-6">
          {step === "recovery" && (
            <>
              <Alert variant="warning" icon={KeyRound} className="mb-5">
                <div className="grid grid-cols-2 gap-2">
                  {recoveryCodes.map((recoveryCode) => (
                    <div
                      key={recoveryCode}
                      className="rounded-md border border-(--border-softer) bg-(--color-bg-card) px-2.5 py-1.5 text-center font-mono text-sm tracking-wide"
                    >
                      {recoveryCode}
                    </div>
                  ))}
                </div>
              </Alert>

              <button type="button" className="btn-primary w-full" onClick={onDone}>
                {t("recoveryAck")}
              </button>
            </>
          )}

          {step === "loading" && (
            <div className="flex flex-col items-center gap-4 pb-2">
              <Skeleton className="h-[240px] w-[240px] rounded-lg" />
              <p className="text-sm text-(--color-muted)">{t("loading")}</p>
            </div>
          )}

          {step === "load_error" && (
            <div className="text-center">
              <FormMessage variant="error">{t("loadError")}</FormMessage>
              <button type="button" className="btn-secondary mt-4" onClick={() => void retryLoad()}>
                {t("retry")}
              </button>
            </div>
          )}

          {step === "ready" && setupData && (
            <>
              <div className="mb-5 flex items-start gap-2.5">
                <span
                  aria-hidden="true"
                  className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-primary text-[11px] font-semibold text-(--color-on-brand-primary)"
                >
                  1
                </span>
                <p className="pt-0.5 text-sm font-medium">{t("scanStep")}</p>
              </div>

              <div className="flex flex-col items-center gap-4">
                <div className="rounded-lg border border-(--border-softer) bg-white p-4 shadow-sm">
                  {/* eslint-disable-next-line @next/next/no-img-element -- data URI générée côté serveur, jamais une URL distante à optimiser */}
                  <img src={setupData.qrCodeDataUri} alt={t("qrAlt")} width={200} height={200} />
                </div>

                <details className="w-full text-center">
                  <summary className="inline-flex cursor-pointer list-none items-center gap-1.5 text-xs font-medium text-(--color-muted) hover:text-brand-primary [&::-webkit-details-marker]:hidden">
                    <QrCode size={14} aria-hidden="true" />
                    {t("cantScan")}
                  </summary>
                  <div className="mt-3 flex items-center justify-between gap-2 rounded-md border border-(--border-softer) bg-(--color-surface-muted) py-1.5 pr-1.5 pl-3">
                    <code className="select-all overflow-x-auto font-mono text-sm tracking-wide whitespace-nowrap">
                      {setupData.secret}
                    </code>
                    <CopyButton value={setupData.secret} label={t("copySecret")} />
                  </div>
                </details>
              </div>

              <div className="my-6 flex items-start gap-2.5">
                <span
                  aria-hidden="true"
                  className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-primary text-[11px] font-semibold text-(--color-on-brand-primary)"
                >
                  2
                </span>
                <p className="pt-0.5 text-sm font-medium">{t("verifyStep")}</p>
              </div>

              <form onSubmit={onSubmit} className="space-y-4" noValidate>
                <TextInput
                  label={t("codeLabel")}
                  hideLabel
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  placeholder={t("codePlaceholder")}
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  autoFocus
                  className="text-center font-mono text-lg tracking-[0.5em]"
                />

                <SubmitButton className="w-full" pending={submitting} pendingLabel={t("submit")}>
                  {t("submit")}
                </SubmitButton>

                {error && <FormMessage variant="error">{error}</FormMessage>}
              </form>
            </>
          )}
        </div>
      </Card>

      <div className="mt-5 flex items-center gap-1.5 text-xs text-(--color-muted)">
        <Smartphone size={14} aria-hidden="true" />
        <span>{t("appHint")}</span>
      </div>

      {step !== "recovery" && (
        <div className="mt-3">
          <LogoutButton className="text-sm text-(--color-muted) underline decoration-dotted underline-offset-2" />
        </div>
      )}
    </div>
  );
}
