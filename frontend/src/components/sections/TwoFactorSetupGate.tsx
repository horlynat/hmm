"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { KeyRound, Copy, Check, Keyboard, Smartphone } from "lucide-react";
import { Card, Alert, Skeleton, Logo } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { LogoutButton } from "@/components/sections/LogoutButton";
import { useRouter } from "@/i18n/navigation";
import {
  setupTwoFactor,
  confirmTwoFactorSetup,
  type TwoFactorSetupResult,
} from "@/lib/auth/actions";

type Step = "loading" | "ready" | "load_error" | "recovery";

/** Bouton "copier" générique : icône Copy → Check pendant 1,5s après un clic réussi. */
function CopyButton({ value, label, className }: { value: string; label: string; className?: string }) {
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
      className={className ?? "flex shrink-0 items-center justify-center rounded-md p-1.5 text-(--color-muted) transition hover:bg-(--color-surface-muted) hover:text-brand-primary"}
    >
      {copied ? <Check size={16} className="text-success" aria-hidden="true" /> : <Copy size={16} aria-hidden="true" />}
    </button>
  );
}

/** Emblème de marque : même composition que Header/Footer (Logo dans un badge brand-primary). */
function BrandMark() {
  return (
    <span className="flex h-11 w-11 items-center justify-center rounded-[var(--radius-md)] bg-brand-primary text-[var(--color-on-brand-primary)]">
      <Logo className="h-6 w-6" />
    </span>
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
  const [showSecret, setShowSecret] = useState(false);
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [copiedAll, setCopiedAll] = useState(false);

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

  async function onCopyAll() {
    try {
      await navigator.clipboard.writeText(recoveryCodes.join("\n"));
      setCopiedAll(true);
      setTimeout(() => setCopiedAll(false), 1500);
    } catch {
      // Voir CopyButton ci-dessus : échec silencieux, les codes restent sélectionnables.
    }
  }

  function onDone() {
    // La page initialement demandée se rend normalement au prochain rendu :
    // getCurrentUser() (server) verra isTwoFactorEnabled=true, CompteLayout
    // n'affichera donc plus cet écran.
    router.refresh();
  }

  return (
    <div className="mx-auto flex max-w-[30rem] flex-col items-center px-4 py-12">
      <Card variant="soft" className="w-full overflow-hidden shadow-sm">
        <div className="h-1 bg-gradient-to-r from-[var(--cta-gradient-from)] to-[var(--cta-gradient-to)]" />

        <div className="flex flex-col items-center px-8 pt-8 pb-6 text-center sm:px-10">
          {step === "recovery" ? (
            <div
              aria-hidden="true"
              className="mb-4 flex h-11 w-11 items-center justify-center rounded-full bg-warning/15 text-(--color-badge-warning-text)"
            >
              <KeyRound size={22} />
            </div>
          ) : (
            <div className="mb-4">
              <BrandMark />
            </div>
          )}
          <h1 className="text-xl font-bold tracking-tight text-balance">
            {step === "recovery" ? t("recoveryTitle") : t("title")}
          </h1>
          <p className="mt-2 max-w-[19rem] text-sm leading-relaxed text-(--color-muted)">
            {step === "recovery" ? t("recoverySubtitle") : t("subtitle")}
          </p>
        </div>

        <div className="px-8 pb-8 sm:px-10">
          {step === "recovery" && (
            <>
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

          {step === "loading" && (
            <div className="flex flex-col items-center gap-4 pb-2">
              <Skeleton className="h-[208px] w-[208px] rounded-2xl" />
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
              <p className="mb-3.5 text-center text-[11px] font-semibold tracking-wider text-brand-primary uppercase">
                {t("scanEyebrow")}
              </p>

              <div className="flex justify-center">
                <div className="rounded-2xl border border-(--border-softer) bg-white p-4 shadow-sm">
                  {/* eslint-disable-next-line @next/next/no-img-element -- data URI générée côté serveur, jamais une URL distante à optimiser */}
                  <img src={setupData.qrCodeDataUri} alt={t("qrAlt")} width={208} height={208} />
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
                    <CopyButton value={setupData.secret} label={t("copySecret")} />
                  </div>
                )}
              </div>

              <div className="my-6 h-px bg-(--border-softer)" />

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

              <div className="mt-5 flex items-center justify-center gap-1.5 text-xs text-(--color-muted)">
                <Smartphone size={14} aria-hidden="true" />
                <span>{t("appHint")}</span>
              </div>
            </>
          )}
        </div>
      </Card>

      {step !== "recovery" && (
        <div className="mt-5">
          <LogoutButton className="text-sm text-(--color-muted) underline decoration-dotted underline-offset-2" />
        </div>
      )}
    </div>
  );
}
