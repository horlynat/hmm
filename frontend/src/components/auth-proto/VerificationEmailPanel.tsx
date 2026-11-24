"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { requestVerificationEmail } from "@/lib/auth/actions";
import { forgotPasswordSchema, type ForgotPasswordValues } from "@/lib/validation/schemas";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FieldWrap } from "./FieldWrap";
import { useFocusHeadingOnChange } from "@/lib/useFocusHeadingOnChange";

/** Demande d'un nouveau lien de confirmation — logique identique à `ResendVerificationEmailForm`. */
export function VerificationEmailPanel() {
  const t = useTranslations("auth.verificationEmail");
  const tv = useTranslations("validation");
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordValues>({ resolver: zodResolver(forgotPasswordSchema(tv)), defaultValues: { email: "" } });

  async function onSubmit(values: ForgotPasswordValues) {
    setStatus("idle");
    setServerError("");
    const result = await requestVerificationEmail(values.email);
    if (result.ok) setStatus("success");
    else {
      setServerError(result.error);
      setStatus("error");
    }
  }

  // Focus sur le titre du nouvel écran (formulaire -> succès…), cf. useFocusHeadingOnChange.
  const focusRef = useFocusHeadingOnChange(status === "success");

  if (status === "success") {
    return (
      <div ref={focusRef} className="auth" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h2 tabIndex={-1}>{t("title")}</h2>
        <p className="mut">{t("success")}</p>
      </div>
    );
  }

  return (
    <form ref={focusRef} className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      <h2 tabIndex={-1}>{t("title")}</h2>
      <p className="mut">{t("subtitle")}</p>
      <FieldWrap id="ve" label={t("emailLabel")} error={errors.email?.message}>
        <input id="ve" className="fi" type="email" autoComplete="email" placeholder={t("emailPlaceholder")} aria-invalid={errors.email ? true : undefined} {...register("email")} />
      </FieldWrap>
      {status === "error" && (
        <p className="qerr" role="alert">
          {serverError || t("error")}
        </p>
      )}
      <button className="b b-p wide" disabled={isSubmitting}>
        {t("submit")}
      </button>
    </form>
  );
}
