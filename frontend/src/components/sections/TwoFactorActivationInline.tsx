"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { KeyRound, Copy, Check, Keyboard, Smartphone, ShieldCheck } from "lucide-react";
import { Alert, Skeleton } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import {
  setupTwoFactor,
  confirmTwoFactorSetup,
  type TwoFactorSetupResult,
} from "@/lib/auth/actions";

type Step = "collapsed" | "loading" | "ready" | "load_error" | "recovery";

/**
 * Activation manuelle de la 2FA, embarquée dans /compte/securite — pendant
 * "à la demande" de TwoFactorSetupGate (écran plein, déclenché automatiquement
 * pour les comptes où la 2FA est obligatoire). Les comptes ROLE_EDITOR en sont
 * exemptés (cf. CompteLayout) mais doivent quand même pouvoir l'activer
 * volontairement — jusqu'ici, rien sur cette page ne le permettait, la 2FA
 * désactivée n'y était qu'un badge informatif sans aucune action possible.
 */
export function TwoFactorActivationInline() {
  const t = useTranslations("auth.profile.security.twoFactorSetup");
  const router = useRouter();

  const [step, setStep] = useState<Step>("collapsed");
  const [setupData, setSetupData] = useState<{ secret: string; qrCodeDataUri: string } | null>(null);
  const [showSecret, setShowSecret] = useState(false);
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [copiedAll, setCopiedAll] = useState(false);
  const [copiedSecret, setCopiedSecret] = useState(false);

  async function start() {
    setStep("loading");
    const result: TwoFactorSetupResult = await setupTwoFactor();
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
    const result = await confirmTwoFactorSetup(setupData.secret, code, password);
    setSubmitting(false);

    if (result.ok) {
      setRecoveryCodes(result.recoveryCodes);
      setStep("recovery");
      return;
    }

    setError(
      result.error === "invalid_code"
        ? t("invalidCode")
        : result.error === "invalid_password"
          ? t("invalidPassword")
          : result.error === "too_many_attempts"
            ? t("tooManyAttempts")
            : t("error"),
    );
  }

  async function onCopySecret() {
    if (!setupData) return;
    try {
      await navigator.clipboard.writeText(setupData.secret);
      setCopiedSecret(true);
      setTimeout(() => setCopiedSecret(false), 1500);
    } catch {
      // Presse-papiers indisponible : la clé reste sélectionnable manuellement.
    }
  }

  async function onCopyAll() {
    try {
      await navigator.clipboard.writeText(recoveryCodes.join("\n"));
      setCopiedAll(true);
      setTimeout(() => setCopiedAll(false), 1500);
    } catch {
      // Voir onCopySecret ci-dessus.
    }
  }

  function onDone() {
    // La page /compte/securite relit isTwoFactorEnabled côté serveur au
    // prochain rendu — même mécanique que TwoFactorSetupGate.
    router.refresh();
  }

  if (step === "collapsed") {
    return (
      <button type="button" onClick={() => void start()} className="btn-primary gap-2 text-sm">
        <ShieldCheck size={16} aria-hidden="true" />
        {t("activateButton")}
      </button>
    );
  }

  return (
    <div className="mt-4 max-w-[26rem] rounded-[var(--radius-md)] border border-(--border-softer) bg-(--color-bg-card) p-5">
      {step === "loading" && (
        <div className="flex flex-col items-center gap-4 py-4">
          <Skeleton className="h-[176px] w-[176px] rounded-2xl" />
          <p className="text-sm text-(--color-muted)">{t("loading")}</p>
        </div>
      )}

      {step === "load_error" && (
        <div className="text-center py-2">
          <FormMessage variant="error">{t("loadError")}</FormMessage>
          <button type="button" className="btn-secondary mt-4 text-sm" onClick={() => void start()}>
            {t("retry")}
          </button>
        </div>
      )}

      {step === "ready" && setupData && (
        <>
          <p className="mb-3 text-center text-[11px] font-semibold tracking-wider text-brand-primary uppercase">
            {t("scanEyebrow")}
          </p>

          <div className="flex justify-center">
            <div className="rounded-2xl border border-(--border-softer) bg-white p-3 shadow-sm">
              {/* eslint-disable-next-line @next/next/no-img-element -- data URI générée côté serveur */}
              <img src={setupData.qrCodeDataUri} alt={t("qrAlt")} width={176} height={176} />
            </div>
          </div>

          <div className="mt-4 flex flex-col items-center gap-2">
            <button
              type="button"
              onClick={() => setShowSecret((v) => !v)}
              className="inline-flex items-center gap-1.5 text-xs text-(--color-muted) transition hover:text-brand-primary"
            >
              <Keyboard size={14} aria-hidden="true" />
              <span>{t("cantScan")}</span>
              <span className="font-semibold text-brand-primary">{t("enterManually")}</span>
            </button>

            {showSecret && (
              <div className="flex w-full items-center justify-between gap-2 rounded-[var(--radius-sm)] border border-(--border-softer) bg-(--color-surface-muted) py-2 pr-2 pl-4">
                <code className="select-all overflow-x-auto font-mono text-sm tracking-wide whitespace-nowrap">
                  {setupData.secret}
                </code>
                <button
                  type="button"
                  onClick={() => void onCopySecret()}
                  aria-label={t("copySecret")}
                  className="flex shrink-0 items-center justify-center rounded-md p-1.5 text-(--color-muted) transition hover:bg-(--color-surface-muted) hover:text-brand-primary"
                >
                  {copiedSecret ? <Check size={16} className="text-success" aria-hidden="true" /> : <Copy size={16} aria-hidden="true" />}
                </button>
              </div>
            )}
          </div>

          <div className="my-5 h-px bg-(--border-softer)" />

          <p className="mb-3 text-center text-sm font-semibold">{t("codeInstruction")}</p>

          <form onSubmit={onSubmit} noValidate>
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
              className="text-center font-mono text-xl tracking-[0.55em]"
            />

            <TextInput
              label={t("passwordLabel")}
              type="password"
              autoComplete="current-password"
              hint={t("passwordHint")}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />

            <SubmitButton className="mt-4 w-full" pending={submitting} pendingLabel={t("submit")}>
              {t("submit")}
            </SubmitButton>

            {error && <FormMessage variant="error">{error}</FormMessage>}
          </form>

          <div className="mt-4 flex items-center justify-center gap-1.5 text-xs text-(--color-muted)">
            <Smartphone size={14} aria-hidden="true" />
            <span>{t("appHint")}</span>
          </div>
        </>
      )}

      {step === "recovery" && (
        <>
          <div className="mb-4 flex flex-col items-center text-center">
            <div
              aria-hidden="true"
              className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-warning/15 text-(--color-badge-warning-text)"
            >
              <KeyRound size={22} />
            </div>
            <h3 className="text-base font-bold tracking-tight">{t("recoveryTitle")}</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-(--color-muted)">{t("recoverySubtitle")}</p>
          </div>

          <Alert variant="warning" icon={KeyRound} className="mb-4">
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

          <button
            type="button"
            onClick={() => void onCopyAll()}
            className="mb-3 flex w-full items-center justify-center gap-2 rounded-[var(--radius-sm)] border border-(--border-input) py-2.5 text-sm font-semibold transition hover:bg-(--color-surface-muted)"
          >
            {copiedAll ? (
              <>
                <Check size={16} className="text-success" aria-hidden="true" />
                {t("copied")}
              </>
            ) : (
              <>
                <Copy size={16} aria-hidden="true" />
                {t("copyAll")}
              </>
            )}
          </button>

          <button type="button" className="btn-primary w-full" onClick={onDone}>
            {t("recoveryAck")}
          </button>
        </>
      )}
    </div>
  );
}
