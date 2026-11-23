"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { requestPasswordReset } from "@/lib/auth/actions";
import { forgotPasswordSchema, type ForgotPasswordValues } from "@/lib/validation/schemas";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FieldWrap } from "./FieldWrap";

/** Panneau « Mot de passe oublié » (prototype `L.step===3/4`), logique identique à `ForgotPasswordForm`. */
export function ForgotPanel({ onBack }: { onBack: () => void }) {
  const t = useTranslations("authPage");
  const tf = useTranslations("auth.forgotPassword");
  const tv = useTranslations("validation");
  const [sent, setSent] = useState(false);
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordValues>({ resolver: zodResolver(forgotPasswordSchema(tv)), defaultValues: { email: "" } });

  async function onSubmit(values: ForgotPasswordValues) {
    setServerError("");
    const result = await requestPasswordReset(values.email);
    if (result.ok) setSent(true);
    else setServerError(result.error || tf("error"));
  }

  if (sent) {
    return (
      <div className="auth" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h2 tabIndex={-1}>{t("resetSentTitle")}</h2>
        <p className="mut">{t("resetSentText")}</p>
        <button type="button" className="b b-g" onClick={onBack}>
          {t("backToLogin")}
        </button>
      </div>
    );
  }

  return (
    <form className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      <h2 tabIndex={-1}>{t("forgotTitle")}</h2>
      <p className="mut">{t("forgotLede")}</p>
      <FieldWrap id="re" label={tf("emailLabel")} error={errors.email?.message}>
        <input id="re" className="fi" type="email" autoComplete="email" aria-invalid={errors.email ? true : undefined} {...register("email")} />
      </FieldWrap>
      {serverError && (
        <p className="qerr" role="alert">
          {serverError}
        </p>
      )}
      <button className="b b-p wide" disabled={isSubmitting}>
        {t("sendLink")}
      </button>
      <button type="button" className="linkbtn" onClick={onBack}>
        {t("backToLogin")}
      </button>
    </form>
  );
}
