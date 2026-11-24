"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link, useRouter } from "@/i18n/navigation";
import { login, verifyLoginTwoFactor } from "@/lib/auth/actions";
import { loginSchema, type LoginValues } from "@/lib/validation/schemas";
import { FieldWrap } from "./FieldWrap";
import { PasswordVisibilityToggle } from "./PasswordVisibilityToggle";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { useFocusHeadingOnChange } from "@/lib/useFocusHeadingOnChange";

/**
 * Panneau « Se connecter » (prototype `authBox()`, `L.tab==='in'`), étapes
 * email/mot de passe puis double authentification — markup `.auth .fl2
 * .icw .pwd .row3 .asec`, logique identique à `LoginForm` (login,
 * verifyLoginTwoFactor, mêmes messages d'erreur).
 */
export function LoginPanel({ onForgot }: { onForgot: () => void }) {
  const t = useTranslations("authPage");
  const tl = useTranslations("auth.login");
  const t2fa = useTranslations("auth.login.twoFactor");
  const tv = useTranslations("validation");
  const router = useRouter();
  const [serverError, setServerError] = useState("");
  const [notVerified, setNotVerified] = useState(false);
  const [challengeToken, setChallengeToken] = useState<string | null>(null);
  const [twoFactorCode, setTwoFactorCode] = useState("");
  const [twoFactorSubmitting, setTwoFactorSubmitting] = useState(false);
  const [twoFactorError, setTwoFactorError] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({ resolver: zodResolver(loginSchema(tv)), defaultValues: { email: "", password: "" } });

  async function onSubmit(values: LoginValues) {
    setServerError("");
    setNotVerified(false);
    const result = await login(values.email, values.password);
    if (result.ok && result.requiresTwoFactor) {
      setChallengeToken(result.challengeToken);
      return;
    }
    if (result.ok) {
      router.push({ pathname: "/compte", query: { welcome: "1" } });
    } else if (result.error === "not_verified") {
      setNotVerified(true);
    } else {
      setServerError(result.error === "invalid_credentials" ? tl("invalidCredentials") : tl("error"));
    }
  }

  async function onSubmitTwoFactor(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!challengeToken) return;
    setTwoFactorError("");
    setTwoFactorSubmitting(true);
    const result = await verifyLoginTwoFactor(challengeToken, twoFactorCode);
    setTwoFactorSubmitting(false);
    if (result.ok) {
      router.push({ pathname: "/compte", query: { welcome: "1" } });
      return;
    }
    setTwoFactorError(
      result.error === "invalid_code" ? t2fa("invalidCode") : result.error === "too_many_attempts" ? t2fa("tooManyAttempts") : t2fa("error"),
    );
  }

  // Focus sur le titre du nouvel écran (formulaire -> succès…), cf. useFocusHeadingOnChange.
  const focusRef = useFocusHeadingOnChange(challengeToken ? "2fa" : "login");

  if (challengeToken) {
    return (
      <form ref={focusRef} className="auth" noValidate onSubmit={onSubmitTwoFactor}>
        <h2 tabIndex={-1}>{t("twoFactorTitle")}</h2>
        <p className="mut">{t("twoFactorLede")}</p>
        <div className={twoFactorError ? "fl2 err" : "fl2"} id="f-otp">
          <label htmlFor="otp">{t("codeLabel")}</label>
          <input
            id="otp"
            className="fi otp"
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={6}
            placeholder="••••••"
            aria-invalid={twoFactorError ? true : undefined}
            value={twoFactorCode}
            onChange={(e) => setTwoFactorCode(e.target.value)}
          />
          <span className="e" role="alert">
            {twoFactorError}
          </span>
        </div>
        <button className="b b-p wide" disabled={twoFactorSubmitting}>
          {t("verify")}
        </button>
        <button
          type="button"
          className="linkbtn"
          onClick={() => {
            setChallengeToken(null);
            setTwoFactorCode("");
            setTwoFactorError("");
          }}
        >
          {t("backToLogin")}
        </button>
      </form>
    );
  }

  return (
    <form ref={focusRef} className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      <h2 tabIndex={-1}>{t("loginTitle")}</h2>
      <p className="mut">{t("loginLede")}</p>
      <FieldWrap id="le" label={tl("emailLabel")} error={errors.email?.message}>
        <div className="icw">
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2" />
            <path d="m22 6-10 7L2 6" />
          </svg>
          <input id="le" className="fi ico" type="email" autoComplete="email" placeholder={tl("emailPlaceholder")} aria-invalid={errors.email ? true : undefined} {...register("email")} />
        </div>
      </FieldWrap>
      <PasswordFieldRhf label={tl("passwordLabel")} error={errors.password?.message} registration={register("password")} />
      <div className="row3">
        <label className="ck">
          <input type="checkbox" />
          <span>{t("rememberMe")}</span>
        </label>
        <button type="button" className="linkbtn" onClick={onForgot}>
          {t("forgotLink")}
        </button>
      </div>
      {notVerified && (
        <p className="qerr" role="alert">
          {t("notVerifiedTitle")} —{" "}
          <Link className="lnk" href="/verification-email">
            {t("resendLink")}
          </Link>
        </p>
      )}
      {serverError && (
        <p className="qerr" role="alert">
          {serverError}
        </p>
      )}
      <button className="b b-p wide" disabled={isSubmitting}>
        {tl("submit")}
      </button>
      <p className="asec">
        <ProtoIcon name="lock" />
        {t("secureNote")}
      </p>
    </form>
  );
}

/** Mot de passe de connexion : pas de robustesse à afficher (déjà choisi), juste afficher/masquer. */
function PasswordFieldRhf({
  label,
  error,
  registration,
}: {
  label: string;
  error?: string;
  registration: ReturnType<ReturnType<typeof useForm<LoginValues>>["register"]>;
}) {
  const [visible, setVisible] = useState(false);
  return (
    <div className={error ? "fl2 err" : "fl2"} id="f-lp">
      <label htmlFor="lp">{label}</label>
      <div className="pwd">
        <input id="lp" type={visible ? "text" : "password"} className="fi" autoComplete="current-password" aria-invalid={error ? true : undefined} {...registration} />
        <PasswordVisibilityToggle visible={visible} onToggle={() => setVisible((v) => !v)} />
      </div>
      <span className="e" role="alert">
        {error}
      </span>
    </div>
  );
}
