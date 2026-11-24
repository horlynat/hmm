"use client";

import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { registerClient } from "@/actions/client";
import { clientRegisterSchema, type ClientRegisterValues } from "@/lib/validation/schemas";
import { mapActionError } from "@/lib/validation/errors";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FieldWrap } from "./FieldWrap";
import { RhfPasswordField } from "./RhfPasswordField";
import { useFocusHeadingOnChange } from "@/lib/useFocusHeadingOnChange";

/** Formulaire client du prototype (`.g2 .fl2 .cons`), logique identique à `ClientForm`. */
export function ClientSignupForm({ onChangeRole }: { onChangeRole: () => void }) {
  const t = useTranslations("authPage");
  const tc = useTranslations("auth.register.client");
  const tv = useTranslations("validation");
  const [honeypot, setHoneypot] = useState("");
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    reset,
    control,
    formState: { errors, isSubmitting },
  } = useForm<ClientRegisterValues>({
    resolver: zodResolver(clientRegisterSchema(tv)),
    defaultValues: { name: "", email: "", phone: "", password: "" },
  });
  const password = useWatch({ control, name: "password" });

  async function onSubmit(values: ClientRegisterValues) {
    if (honeypot.trim().length > 0) return; // anti-bot silencieux
    setStatus("idle");
    const result = await registerClient({ name: values.name, email: values.email, phone: values.phone ?? "", password: values.password, agreeTerms: values.agreeTerms });
    if (result.ok) {
      reset();
      setStatus("success");
    } else {
      setServerError(mapActionError(result.error, tv, tc));
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
        <h2 tabIndex={-1}>{t("createAccount")}</h2>
        <p className="mut">{tc("success")}</p>
      </div>
    );
  }

  return (
    <form ref={focusRef} className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      <button type="button" className="linkbtn back" onClick={onChangeRole}>
        <ProtoIcon name="al" />
        {t("changeRole")}
      </button>
      <h2 tabIndex={-1}>{t("clientFormTitle")}</h2>
      <div className="hp" aria-hidden="true">
        <label htmlFor="cl-hp">{t("codeLabel")}</label>
        <input id="cl-hp" tabIndex={-1} autoComplete="off" value={honeypot} onChange={(e) => setHoneypot(e.target.value)} />
      </div>
      <div className="g2">
        <FieldWrap id="sn" label={tc("nameLabel")} error={errors.name?.message}>
          <input id="sn" className="fi" autoComplete="name" placeholder={tc("namePlaceholder")} aria-invalid={errors.name ? true : undefined} {...register("name")} />
        </FieldWrap>
        <FieldWrap id="se" label={tc("emailLabel")} error={errors.email?.message}>
          <input id="se" className="fi" type="email" autoComplete="email" placeholder={tc("emailPlaceholder")} aria-invalid={errors.email ? true : undefined} {...register("email")} />
        </FieldWrap>
      </div>
      <FieldWrap id="sw" label={tc("phoneLabel")} error={errors.phone?.message}>
        <input id="sw" className="fi" type="tel" autoComplete="tel" placeholder={tc("phonePlaceholder")} aria-invalid={errors.phone ? true : undefined} {...register("phone")} />
      </FieldWrap>
      <RhfPasswordField id="sp" label={tc("passwordLabel")} error={errors.password?.message} register={register} name="password" watched={password} withMeter meterHint={tc("passwordHint")} />
      <div className="fl2 cons" id="f-sc">
        <label className="ck">
          <input type="checkbox" {...register("agreeTerms")} aria-invalid={errors.agreeTerms ? true : undefined} />
          <span>
            {tc.rich("agreeTerms", {
              terms: (chunks) => (
                <Link className="lnk" href="/conditions-generales">
                  {chunks}
                </Link>
              ),
              privacy: (chunks) => (
                <Link className="lnk" href="/politique-de-confidentialite">
                  {chunks}
                </Link>
              ),
            })}
          </span>
        </label>
        <span className="e" role="alert">
          {errors.agreeTerms?.message}
        </span>
      </div>
      {status === "error" && (
        <p className="qerr" role="alert">
          {serverError || tc("error")}
        </p>
      )}
      <button className="b b-p wide" disabled={isSubmitting}>
        {t("createAccount")}
      </button>
    </form>
  );
}
