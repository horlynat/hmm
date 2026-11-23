"use client";

import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { registerCollaborator } from "@/actions/collaborator";
import { freelanceRegisterSchema, type FreelanceRegisterValues } from "@/lib/validation/schemas";
import { mapActionError } from "@/lib/validation/errors";
import { SPECIALTY_KEYS } from "@/lib/profileFields";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FieldWrap } from "./FieldWrap";
import { RhfPasswordField } from "./RhfPasswordField";

const DISPO_KEYS = ["dispoImmediate", "dispo2Weeks", "dispo1Month", "dispoDiscuss"] as const;

/**
 * Formulaire freelance du prototype (`.g2 .fl2 .chipsel .chip2 .cons`),
 * logique identique à `FreelanceForm`. `onChangeRole` absent (ex. page
 * /freelances, où le profil est déjà déterminé par le contexte de la
 * page) masque le bouton « Changer de profil ».
 */
export function FreelanceSignupForm({ onChangeRole }: { onChangeRole?: () => void }) {
  const t = useTranslations("authPage");
  const tf = useTranslations("freelances.signup");
  const tv = useTranslations("validation");
  const [honeypot, setHoneypot] = useState("");
  const [specialties, setSpecialties] = useState<string[]>([]);
  const [specialtiesError, setSpecialtiesError] = useState(false);
  const [availability, setAvailability] = useState(tf(DISPO_KEYS[0]));
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    reset,
    control,
    formState: { errors, isSubmitting },
  } = useForm<FreelanceRegisterValues>({
    resolver: zodResolver(freelanceRegisterSchema(tv)),
    defaultValues: { name: "", email: "", phone: "", password: "", portfolioUrl: "", bio: "" },
  });
  const password = useWatch({ control, name: "password" });

  function toggleSpecialty(label: string) {
    setSpecialtiesError(false);
    setSpecialties((prev) => (prev.includes(label) ? prev.filter((s) => s !== label) : [...prev, label]));
  }

  async function onSubmit(values: FreelanceRegisterValues) {
    if (honeypot.trim().length > 0) return; // anti-bot silencieux
    if (specialties.length === 0) {
      setSpecialtiesError(true);
      return;
    }
    setStatus("idle");
    const result = await registerCollaborator({
      name: values.name,
      email: values.email,
      password: values.password,
      agreeTerms: values.agreeTerms,
      specialties,
      availability,
      portfolioUrl: values.portfolioUrl ?? "",
      bio: values.bio ?? "",
    });
    if (result.ok) {
      reset();
      setSpecialties([]);
      setAvailability(tf(DISPO_KEYS[0]));
      setStatus("success");
    } else {
      setServerError(mapActionError(result.error, tv, tf));
      setStatus("error");
    }
  }

  if (status === "success") {
    return (
      <div className="auth" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h2 tabIndex={-1}>{t("createAccount")}</h2>
        <p className="mut">{tf("success")}</p>
      </div>
    );
  }

  return (
    <form className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      {onChangeRole && (
        <button type="button" className="linkbtn back" onClick={onChangeRole}>
          <ProtoIcon name="al" />
          {t("changeRole")}
        </button>
      )}
      <h2 tabIndex={-1}>{t("freelanceFormTitle")}</h2>
      <div className="hp" aria-hidden="true">
        <label htmlFor="fl-hp">{t("codeLabel")}</label>
        <input id="fl-hp" tabIndex={-1} autoComplete="off" value={honeypot} onChange={(e) => setHoneypot(e.target.value)} />
      </div>

      <div className="g2">
        <FieldWrap id="sn" label={tf("nameLabel")} error={errors.name?.message}>
          <input id="sn" className="fi" autoComplete="name" placeholder={tf("namePlaceholder")} aria-invalid={errors.name ? true : undefined} {...register("name")} />
        </FieldWrap>
        <div className="fl2">
          <label htmlFor="sd">{tf("dispoLabel")}</label>
          <select id="sd" className="fi" value={availability} onChange={(e) => setAvailability(e.target.value)}>
            {DISPO_KEYS.map((key) => (
              <option key={key} value={tf(key)}>
                {tf(key)}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className={specialtiesError ? "fl2 err" : "fl2"} id="f-sk">
        <span className="lab">{tf("specialtiesLabel")}</span>
        <div className="chipsel">
          {SPECIALTY_KEYS.map((key) => {
            const label = tf(key);
            const active = specialties.includes(label);
            return (
              <button key={key} type="button" className={active ? "chip2 on" : "chip2"} aria-pressed={active} onClick={() => toggleSpecialty(label)}>
                {label}
              </button>
            );
          })}
        </div>
        <span className="e" role="alert">
          {tf("errorRequired")}
        </span>
      </div>

      <FieldWrap id="sl" label={tf("portfolioLabel")} error={errors.portfolioUrl?.message}>
        <input id="sl" className="fi" type="url" inputMode="url" placeholder={tf("portfolioPlaceholder")} aria-invalid={errors.portfolioUrl ? true : undefined} {...register("portfolioUrl")} />
      </FieldWrap>

      <FieldWrap id="sb" label={tf("bioLabel")} error={errors.bio?.message}>
        <textarea id="sb" className="fi" style={{ minHeight: "100px" }} placeholder={tf("bioPlaceholder")} aria-invalid={errors.bio ? true : undefined} {...register("bio")} />
        {!errors.bio && (
          <small className="dm" style={{ fontSize: "13px" }}>
            {tf("bioHint")}
          </small>
        )}
      </FieldWrap>

      <div className="g2">
        <FieldWrap id="se" label={tf("emailLabel")} error={errors.email?.message}>
          <input id="se" className="fi" type="email" autoComplete="email" placeholder={tf("emailPlaceholder")} aria-invalid={errors.email ? true : undefined} {...register("email")} />
        </FieldWrap>
        <RhfPasswordField id="sp" label={tf("passwordLabel")} error={errors.password?.message} register={register} name="password" watched={password} withMeter meterHint={tf("passwordHint")} />
      </div>

      <div className="fl2 cons" id="f-sc">
        <label className="ck">
          <input type="checkbox" {...register("agreeTerms")} aria-invalid={errors.agreeTerms ? true : undefined} />
          <span>
            {tf.rich("agreeTerms", {
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
          {serverError || tf("error")}
        </p>
      )}

      <button className="b b-p wide" disabled={isSubmitting}>
        {t("createAccount")}
      </button>
    </form>
  );
}
