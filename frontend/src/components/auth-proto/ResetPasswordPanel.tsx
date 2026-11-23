"use client";

import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { confirmPasswordReset } from "@/lib/auth/actions";
import { resetPasswordSchema, type ResetPasswordValues } from "@/lib/validation/schemas";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FieldWrap } from "./FieldWrap";
import { RhfPasswordField } from "./RhfPasswordField";

/** Choix d'un nouveau mot de passe depuis le lien reçu par e-mail — logique identique à `ResetPasswordForm`. */
export function ResetPasswordPanel({ token }: { token: string }) {
  const t = useTranslations("auth.resetPassword");
  const tv = useTranslations("validation");
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordValues>({ resolver: zodResolver(resetPasswordSchema(tv)), defaultValues: { password: "", confirmPassword: "" } });
  const password = useWatch({ control, name: "password" });

  async function onSubmit(values: ResetPasswordValues) {
    setStatus("idle");
    setServerError("");
    const result = await confirmPasswordReset(token, values.password);
    if (result.ok) setStatus("success");
    else {
      setServerError(result.error);
      setStatus("error");
    }
  }

  if (status === "success") {
    return (
      <div className="auth" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h2 tabIndex={-1}>{t("title")}</h2>
        <p className="mut">{t("success")}</p>
        <Link className="b b-g" href="/connexion">
          {t("loginLink")}
        </Link>
      </div>
    );
  }

  return (
    <form className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      <h2 tabIndex={-1}>{t("title")}</h2>
      <p className="mut">{t("subtitle")}</p>
      <RhfPasswordField id="rp" label={t("passwordLabel")} error={errors.password?.message} register={register} name="password" watched={password} withMeter />
      <FieldWrap id="rpc" label={t("confirmPasswordLabel")} error={errors.confirmPassword?.message}>
        <input id="rpc" type="password" className="fi" autoComplete="new-password" aria-invalid={errors.confirmPassword ? true : undefined} {...register("confirmPassword")} />
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
