"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ShieldCheck, KeyRound } from "lucide-react";
import { Card, Alert } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { LogoutButton } from "@/components/sections/LogoutButton";
import { useRouter } from "@/i18n/navigation";
import {
  setupTwoFactor,
  confirmTwoFactorSetup,
  type TwoFactorSetupResult,
} from "@/lib/auth/actions";

type Step = "loading" | "ready" | "load_error" | "recovery";

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
      <Card variant="soft" className="w-full p-8">
        <div
          aria-hidden="true"
          className="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-brand-primary/10 text-2xl"
        >
          <ShieldCheck className="text-brand-primary" size={24} />
        </div>

        {step === "recovery" ? (
          <>
            <h1 className="mb-1.5 text-center text-base font-semibold">{t("recoveryTitle")}</h1>
            <p className="mb-5 text-center text-sm text-(--color-muted)">{t("recoverySubtitle")}</p>

            <Alert variant="warning" icon={KeyRound} className="mb-5">
              <ul className="grid grid-cols-2 gap-x-4 gap-y-1.5 font-mono text-sm">
                {recoveryCodes.map((recoveryCode) => (
                  <li key={recoveryCode}>{recoveryCode}</li>
                ))}
              </ul>
            </Alert>

            <button type="button" className="btn-primary w-full" onClick={onDone}>
              {t("recoveryAck")}
            </button>
          </>
        ) : (
          <>
            <h1 className="mb-1.5 text-center text-base font-semibold">{t("title")}</h1>
            <p className="mb-5 text-center text-sm text-(--color-muted)">{t("subtitle")}</p>

            {step === "loading" && (
              <p className="text-center text-sm text-(--color-muted)">{t("loading")}</p>
            )}

            {step === "load_error" && (
              <div className="text-center">
                <FormMessage variant="error">{t("loadError")}</FormMessage>
                <button
                  type="button"
                  className="btn-secondary mt-4"
                  onClick={() => {
                    setStep("loading");
                    void setupTwoFactor().then((result) => {
                      if (result.ok) {
                        setSetupData({ secret: result.secret, qrCodeDataUri: result.qrCodeDataUri });
                        setStep("ready");
                      } else {
                        setStep("load_error");
                      }
                    });
                  }}
                >
                  {t("retry")}
                </button>
              </div>
            )}

            {step === "ready" && setupData && (
              <>
                <div className="flex flex-col items-center gap-4 border-b border-(--border-softer) pb-5">
                  {/* eslint-disable-next-line @next/next/no-img-element -- data URI générée côté serveur, jamais une URL distante à optimiser */}
                  <img
                    src={setupData.qrCodeDataUri}
                    alt={t("qrAlt")}
                    className="rounded-lg border border-(--border-softer) shadow-sm"
                    width={240}
                    height={240}
                  />
                  <div className="text-center">
                    <p className="mb-1 text-xs text-(--color-muted)">{t("cantScan")}</p>
                    <code className="select-all rounded-md bg-(--color-surface-muted) px-3 py-1.5 font-mono text-sm tracking-wider">
                      {setupData.secret}
                    </code>
                  </div>
                </div>

                <form onSubmit={onSubmit} className="mt-5 space-y-4" noValidate>
                  <TextInput
                    label={t("codeLabel")}
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    placeholder={t("codePlaceholder")}
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    autoFocus
                  />

                  <SubmitButton className="w-full" pending={submitting} pendingLabel={t("submit")}>
                    {t("submit")}
                  </SubmitButton>

                  {error && <FormMessage variant="error">{error}</FormMessage>}
                </form>
              </>
            )}
          </>
        )}
      </Card>

      {step !== "recovery" && (
        <div className="mt-5">
          <LogoutButton className="text-sm text-(--color-muted) underline decoration-dotted underline-offset-2" />
        </div>
      )}
    </div>
  );
}
