"use client";

import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Card, LegalLink } from "@/components/ui";
import {
  TextInput,
  TextArea,
  Checkbox,
  SubmitButton,
  FormMessage,
  FormSection,
  PasswordStrength,
} from "@/components/ui/form";
import { freelanceRegisterSchema, type FreelanceRegisterValues } from "@/lib/validation/schemas";
import { mapActionError } from "@/lib/validation/errors";
import { registerCollaborator } from "@/actions/collaborator";

const SPECIALTY_KEYS = [
  "specialtyBackend",
  "specialtyFrontend",
  "specialtyMobile",
  "specialtyAi",
  "specialtyCyber",
  "specialtyDesign",
] as const;
const SPECIALTY_ICONS: Record<(typeof SPECIALTY_KEYS)[number], string> = {
  specialtyBackend: "🗄️",
  specialtyFrontend: "💻",
  specialtyMobile: "📱",
  specialtyAi: "🤖",
  specialtyCyber: "🛡️",
  specialtyDesign: "🎨",
};
const DISPO_KEYS = ["dispoImmediate", "dispo2Weeks", "dispo1Month", "dispoDiscuss"] as const;

export function FreelanceForm() {
  const t = useTranslations("freelances.signup");
  const tv = useTranslations("validation");
  const [honeypot, setHoneypot] = useState("");
  const [specialties, setSpecialties] = useState<string[]>([]);
  const [availability, setAvailability] = useState(t(DISPO_KEYS[0]));
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");
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
    setSpecialties((prev) =>
      prev.includes(label) ? prev.filter((s) => s !== label) : [...prev, label],
    );
  }

  async function onSubmit(values: FreelanceRegisterValues) {
    if (honeypot.trim().length > 0) return; // anti-bot silencieux
    setServerStatus("idle");
    setServerError("");
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
      setAvailability(t(DISPO_KEYS[0]));
      setServerStatus("success");
    } else {
      setServerError(mapActionError(result.error, tv, t));
      setServerStatus("error");
    }
  }

  return (
    <Card variant="soft" className="p-8">
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        {/* Champ piège anti-bot, invisible et hors du flux d'accessibilité. */}
        <div className="absolute h-0 w-0 overflow-hidden opacity-0" aria-hidden="true">
          <label htmlFor="fl-hp">Ne pas remplir ce champ</label>
          <input
            id="fl-hp"
            type="text"
            tabIndex={-1}
            autoComplete="off"
            value={honeypot}
            onChange={(e) => setHoneypot(e.target.value)}
          />
        </div>

        <FormSection icon="👤" title={t("sectionIdentity")}>
          <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
            <TextInput
              label={t("nameLabel")}
              placeholder={t("namePlaceholder")}
              autoComplete="name"
              error={errors.name?.message}
              {...register("name")}
            />
            <TextInput
              label={t("emailLabel")}
              type="email"
              autoComplete="email"
              placeholder={t("emailPlaceholder")}
              error={errors.email?.message}
              {...register("email")}
            />
          </div>

          <TextInput
            label={t("passwordLabel")}
            type="password"
            autoComplete="new-password"
            placeholder={t("passwordPlaceholder")}
            hint={t("passwordHint")}
            error={errors.password?.message}
            {...register("password")}
          />
          <PasswordStrength value={password} />
        </FormSection>

        <FormSection icon="🛠️" title={t("sectionExpertise")}>
          <fieldset>
            <legend className="field-label">{t("specialtiesLabel")}</legend>
            <div className="flex flex-wrap gap-2">
              {SPECIALTY_KEYS.map((key) => {
                const label = t(key);
                const active = specialties.includes(label);
                return (
                  <button
                    key={key}
                    type="button"
                    aria-pressed={active}
                    onClick={() => toggleSpecialty(label)}
                    className={clsx(
                      "inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 font-mono text-xs transition-colors",
                      active
                        ? "border-brand-primary bg-brand-primary text-[var(--color-on-brand-primary)]"
                        : "border-[var(--border-soft)] bg-bg-default text-brand-primary hover:border-brand-primary",
                    )}
                  >
                    <span aria-hidden="true">{SPECIALTY_ICONS[key]}</span>
                    {label}
                  </button>
                );
              })}
            </div>
          </fieldset>
        </FormSection>

        <FormSection icon="📅" title={t("sectionAvailability")}>
          <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
            <div className="mb-5">
              <label className="field-label" htmlFor="fl-dispo">
                {t("dispoLabel")}
              </label>
              <select
                id="fl-dispo"
                className="input"
                value={availability}
                onChange={(e) => setAvailability(e.target.value)}
              >
                {DISPO_KEYS.map((key) => (
                  <option key={key} value={t(key)}>
                    {t(key)}
                  </option>
                ))}
              </select>
            </div>
            <TextInput
              label={t("portfolioLabel")}
              type="url"
              inputMode="url"
              placeholder={t("portfolioPlaceholder")}
              error={errors.portfolioUrl?.message}
              {...register("portfolioUrl")}
            />
          </div>
        </FormSection>

        <FormSection icon="✍️" title={t("sectionAbout")}>
          <TextArea
            label={t("bioLabel")}
            placeholder={t("bioPlaceholder")}
            hint={t("bioHint")}
            error={errors.bio?.message}
            {...register("bio")}
          />
        </FormSection>

        <Checkbox
          label={t.rich("agreeTerms", {
            terms: (chunks) => <LegalLink href="/conditions-generales">{chunks}</LegalLink>,
            privacy: (chunks) => (
              <LegalLink href="/politique-de-confidentialite">{chunks}</LegalLink>
            ),
          })}
          error={errors.agreeTerms?.message}
          {...register("agreeTerms")}
        />

        <SubmitButton className="mt-5 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
          {t("submit")}
        </SubmitButton>

        {serverStatus === "success" && <FormMessage variant="success">{t("success")}</FormMessage>}
        {serverStatus === "error" && (
          <FormMessage variant="error">{serverError || t("error")}</FormMessage>
        )}
      </form>
    </Card>
  );
}
