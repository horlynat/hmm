"use client";

import { useEffect, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { MailWarning, X } from "lucide-react";
import { Card } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { loginSchema, type LoginValues } from "@/lib/validation/schemas";
import { Link, useRouter } from "@/i18n/navigation";
import { login } from "@/lib/auth/actions";

export function LoginForm() {
  const t = useTranslations("auth.login");
  const tv = useTranslations("validation");
  const router = useRouter();
  const [serverError, setServerError] = useState("");
  const [notVerifiedOpen, setNotVerifiedOpen] = useState(false);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema(tv)),
    defaultValues: { email: "", password: "" },
  });

  // Fermeture uniquement par action du client (Échap, clic hors modale, bouton
  // fermer ou clic sur le lien de renvoi) — pas de fermeture automatique.
  // Bloque le défilement du fond pendant que la modale est ouverte (même
  // logique que le tiroir de nav de AccountShell).
  useEffect(() => {
    if (!notVerifiedOpen) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setNotVerifiedOpen(false);
    }
    document.addEventListener("keydown", onKeyDown);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    closeButtonRef.current?.focus();

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [notVerifiedOpen]);

  async function onSubmit(values: LoginValues) {
    setServerError("");
    const result = await login(values.email, values.password);
    if (result.ok) {
      // push() vers /compte récupère déjà des données serveur fraîches pour
      // cette nouvelle route (donc la session vient d'être posée). Un
      // router.refresh() juste après faisait courir une seconde requête RSC
      // en parallèle qui perturbait la remise à zéro du scroll de Next.js,
      // laissant la page arriver déjà défilée (le bouton du tiroir mobile,
      // tout en haut du contenu, se retrouvait hors écran).
      router.push({ pathname: "/compte", query: { welcome: "1" } });
    } else if (result.error === "not_verified") {
      // Distingué du cas "identifiants invalides" par login() — n'est
      // atteignable qu'avec le bon mot de passe (cf. commentaire de
      // ACCOUNT_NOT_VERIFIED_MESSAGE dans lib/auth/actions.ts), donc cette
      // modale et son lien de renvoi ne fuitent rien à un tiers.
      setNotVerifiedOpen(true);
    } else {
      setServerError(
        result.error === "invalid_credentials"
          ? t("invalidCredentials")
          : t("error"),
      );
    }
  }

  return (
    <>
      <Card variant="soft" className="p-8">
        <div
          aria-hidden="true"
          className="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-brand-primary/10 text-2xl"
        >
          🔒
        </div>

        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <TextInput
            label={t("emailLabel")}
            type="email"
            autoComplete="email"
            placeholder={t("emailPlaceholder")}
            error={errors.email?.message}
            {...register("email")}
          />

          <TextInput
            label={t("passwordLabel")}
            type="password"
            autoComplete="current-password"
            placeholder={t("passwordPlaceholder")}
            error={errors.password?.message}
            {...register("password")}
          />

          <SubmitButton
            className="mt-2 w-full"
            pending={isSubmitting}
            pendingLabel={t("submit")}
          >
            {t("submit")}
          </SubmitButton>

          {serverError && (
            <FormMessage variant="error">{serverError}</FormMessage>
          )}

          <p className="mt-4 mb-4 text-right text-xs">
            <Link
              href="/mot-de-passe-oublie"
              className="font-semibold text-brand-primary hover:underline"
            >
              {t("forgotPasswordLink")}
            </Link>
          </p>
        </form>

        {/* <div className="my-6 border-t border-[var(--border-softer)]" /> */}

        <p className="text-center text-sm opacity-70">
          {t("noAccount")}{" "}
          <Link
            href="/inscription"
            className="font-semibold text-brand-primary hover:underline"
          >
            {t("registerLink")}
          </Link>
        </p>
      </Card>

      {notVerifiedOpen && (
        <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
          <div
            className="absolute inset-0 bg-black/40"
            onClick={() => setNotVerifiedOpen(false)}
            aria-hidden="true"
          />
          <div
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="not-verified-modal-title"
            className="relative w-full max-w-[380px] rounded-[var(--radius-lg)] border border-(--border-neutral) bg-bg-card p-6 shadow-2xl"
          >
            <button
              ref={closeButtonRef}
              type="button"
              onClick={() => setNotVerifiedOpen(false)}
              aria-label={t("notVerifiedDismiss")}
              className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full text-(--color-muted) hover:bg-(--color-surface-muted)"
            >
              <X size={16} aria-hidden="true" />
            </button>

            <div className="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-full bg-warning/15 text-(--color-badge-warning-text)">
              <MailWarning size={20} aria-hidden="true" />
            </div>

            <h2 id="not-verified-modal-title" className="mb-1.5 text-center text-base font-semibold">
              {t("notVerifiedError")}
            </h2>
            <p className="mb-5 text-center text-sm text-(--color-muted)">{t("notVerifiedHint")}</p>

            <Link
              href="/verification-email"
              onClick={() => setNotVerifiedOpen(false)}
              className="btn-primary block w-full text-center text-sm"
            >
              {t("resendVerificationLink")}
            </Link>
          </div>
        </div>
      )}
    </>
  );
}
