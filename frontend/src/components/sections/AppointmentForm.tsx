"use client";

import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Badge, Card, LegalLink } from "@/components/ui";
import { TextInput, TextArea, Checkbox, SubmitButton, FormMessage, FormSection } from "@/components/ui/form";
import { appointmentSchema, type AppointmentValues } from "@/lib/validation/schemas";
import { submitAppointmentRequest } from "@/actions/contact";

const SLOT_KEYS = [
  "slotMonMorning",
  "slotMonAfternoon",
  "slotTueMorning",
  "slotTueAfternoon",
  "slotWedMorning",
  "slotWedAfternoon",
] as const;
const SUBJECT_KEYS = [
  "subjectNew",
  "subjectCyber",
  "subjectAssurance",
  "subjectSinistre",
  "subjectDesign",
  "subjectFreelance",
  "subjectFollowup",
  "subjectQuestion",
  "subjectOther",
] as const;
const CANAL_OPTIONS = [
  { value: "email", key: "canalEmail" },
  { value: "whatsapp", key: "canalWhatsapp" },
  { value: "phone", key: "canalPhone" },
] as const;

export function AppointmentForm() {
  const t = useTranslations("contact.rdv");
  const tv = useTranslations("validation");
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");

  const {
    register,
    handleSubmit,
    control,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<AppointmentValues>({
    resolver: zodResolver(appointmentSchema(tv)),
    defaultValues: {
      name: "",
      company: "",
      email: "",
      phone: "",
      canal: "",
      slot: "",
      subject: "subjectNew",
      message: "",
    },
  });

  const canal = useWatch({ control, name: "canal" });
  const slot = useWatch({ control, name: "slot" });
  const isEmailChannel = canal === "email" || !canal;

  return (
    <Card variant="soft" className="p-8">
      <Badge variant="accent">{t("badge")}</Badge>
      <h3 className="mb-3 mt-3.5 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("title")}
      </h3>
      <p className="mb-5 text-sm opacity-70">{t("text")}</p>
      <div className="mb-6 flex flex-wrap gap-2">
        {[
          ["⏱️", t("badgeDuration")],
          ["🆓", t("badgeFree")],
          ["📧", t("badgeConfirm")],
        ].map(([icon, label]) => (
          <span
            key={label}
            className="inline-flex items-center gap-1.5 rounded-full bg-brand-light/60 px-3 py-1.5 text-xs font-medium text-[var(--color-on-brand-light)]"
          >
            <span aria-hidden="true">{icon}</span>
            {label}
          </span>
        ))}
      </div>

      <form
        noValidate
        onSubmit={handleSubmit(async (values) => {
          setServerStatus("idle");
          const canalLabel = t(CANAL_OPTIONS.find((c) => c.value === values.canal)!.key);
          const result = await submitAppointmentRequest({
            name: values.name,
            company: values.company ?? "",
            email: values.email,
            phone: values.phone ?? "",
            canal: canalLabel,
            slot: t(values.slot as (typeof SLOT_KEYS)[number]),
            subject: t(values.subject as (typeof SUBJECT_KEYS)[number]),
            message: values.message ?? "",
          });
          if (result.ok) {
            reset();
            setServerStatus("success");
          } else {
            setServerStatus("error");
          }
        })}
      >
        <FormSection icon="👤" title={t("sectionContact")}>
          <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
            <TextInput
              label={t("nameLabel")}
              autoComplete="name"
              placeholder={t("namePlaceholder")}
              error={errors.name?.message}
              {...register("name")}
            />
            <TextInput
              label={t("companyLabel")}
              autoComplete="organization"
              placeholder={t("companyPlaceholder")}
              error={errors.company?.message}
              {...register("company")}
            />
          </div>

          <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
            <TextInput
              label={t("emailLabel")}
              type="email"
              autoComplete="email"
              placeholder={t("emailPlaceholder")}
              error={errors.email?.message}
              {...register("email")}
            />
            {!isEmailChannel && (
              <TextInput
                label={t("phoneLabel")}
                type="tel"
                autoComplete="tel"
                placeholder={t("phonePlaceholder")}
                hint={t("phoneHint")}
                error={errors.phone?.message}
                {...register("phone")}
              />
            )}
          </div>
        </FormSection>

        <FormSection icon="🗓️" title={t("sectionSlot")}>
          {/* Canal de contact — groupe de boutons radio accessible. */}
          <fieldset className="mb-5">
            <legend className="field-label">{t("canalLabel")}</legend>
            <div role="radiogroup" aria-label={t("canalLabel")} className="grid grid-cols-3 gap-2">
              {CANAL_OPTIONS.map(({ value, key }) => (
                <button
                  key={value}
                  type="button"
                  role="radio"
                  aria-checked={canal === value}
                  onClick={() => setValue("canal", value, { shouldValidate: true })}
                  className={clsx(
                    "rounded-[var(--radius-sm)] border px-2 py-2.5 text-center font-mono text-xs transition-colors",
                    canal === value
                      ? "border-brand-primary bg-brand-primary text-[var(--color-on-brand-primary)]"
                      : "border-[var(--border-soft)] bg-bg-default hover:border-brand-primary",
                  )}
                >
                  {t(key)}
                </button>
              ))}
            </div>
            {errors.canal && (
              <p role="alert" className="mt-1.5 text-xs font-medium text-danger">
                {errors.canal.message}
              </p>
            )}
          </fieldset>

          {/* Créneau souhaité. */}
          <fieldset>
            <legend className="field-label">{t("slotLabel")}</legend>
            <div role="radiogroup" aria-label={t("slotLabel")} className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {SLOT_KEYS.map((key) => (
                <button
                  key={key}
                  type="button"
                  role="radio"
                  aria-checked={slot === key}
                  onClick={() => setValue("slot", key, { shouldValidate: true })}
                  className={clsx(
                    "rounded-[var(--radius-sm)] border px-2 py-2.5 text-center font-mono text-xs transition-colors",
                    slot === key
                      ? "border-brand-primary bg-brand-primary text-[var(--color-on-brand-primary)]"
                      : "border-[var(--border-soft)] bg-bg-default hover:border-brand-primary",
                  )}
                >
                  {t(key)}
                </button>
              ))}
            </div>
            {errors.slot && (
              <p role="alert" className="mt-1.5 text-xs font-medium text-danger">
                {errors.slot.message}
              </p>
            )}
            <p className="mt-2 text-xs text-[var(--color-muted)]">{t("slotTimezoneHint")}</p>
          </fieldset>
        </FormSection>

        <FormSection icon="💬" title={t("sectionSubject")}>
          <div className="mb-5">
            <label className="field-label" htmlFor="rdv-subject">
              {t("subjectLabel")}
            </label>
            <select id="rdv-subject" className="input" {...register("subject")}>
              {SUBJECT_KEYS.map((key) => (
                <option key={key} value={key}>
                  {t(key)}
                </option>
              ))}
            </select>
          </div>

          <TextArea
            label={t("messageLabel")}
            placeholder={t("messagePlaceholder")}
            error={errors.message?.message}
            {...register("message")}
          />
        </FormSection>

        <Checkbox
          label={t.rich("agreeTerms", {
            privacy: (chunks) => (
              <LegalLink href="/politique-de-confidentialite">{chunks}</LegalLink>
            ),
          })}
          error={errors.agreeTerms?.message}
          {...register("agreeTerms")}
        />

        <SubmitButton className="mt-4 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
          {t("submit")}
        </SubmitButton>

        {serverStatus === "success" && <FormMessage variant="success">{t("success")}</FormMessage>}
        {serverStatus === "error" && <FormMessage variant="error">{t("error")}</FormMessage>}
      </form>
    </Card>
  );
}
