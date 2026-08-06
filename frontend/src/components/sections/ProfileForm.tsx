"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { SettingsSection, SettingsSectionGroup } from "@/components/ui";
import { TextInput, TextArea, SubmitButton, FormMessage } from "@/components/ui/form";
import { profileSchema, type ProfileValues } from "@/lib/validation/schemas";
import { useRouter } from "@/i18n/navigation";
import { updateProfile } from "@/lib/auth/actions";
import { SPECIALTY_KEYS, SPECIALTY_ICONS, LANGUAGE_KEYS } from "@/lib/profileFields";
import type { ProfileUpdatePayload, SessionUser } from "@/lib/types";

/** Sélecteur à puces sur liste fermée (compétences/langues) — même pattern que FreelanceForm.tsx, pour une saisie fiable plutôt que du texte libre. */
function ChipToggleGroup({
  legend,
  options,
  selected,
  onToggle,
  disabled,
  lockedHint,
}: {
  legend: string;
  options: readonly { key: string; label: string; icon?: string }[];
  selected: string[];
  onToggle: (label: string) => void;
  disabled?: boolean;
  lockedHint?: string;
}) {
  return (
    <fieldset className="mb-5" disabled={disabled}>
      <legend className="field-label">{legend}</legend>
      <div className="flex flex-wrap gap-2">
        {options.map(({ key, label, icon }) => {
          const active = selected.includes(label);
          return (
            <button
              key={key}
              type="button"
              aria-pressed={active}
              disabled={disabled}
              onClick={() => onToggle(label)}
              className={clsx(
                "inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 font-mono text-xs transition-colors",
                disabled && "cursor-not-allowed opacity-50",
                active
                  ? "border-brand-primary bg-brand-primary text-[var(--color-on-brand-primary)]"
                  : "border-[var(--border-soft)] bg-bg-default text-brand-primary hover:border-brand-primary",
              )}
            >
              {icon && <span aria-hidden="true">{icon}</span>}
              {label}
            </button>
          );
        })}
      </div>
      {disabled && lockedHint && <p className="mt-1.5 text-xs text-[var(--color-muted)]">{lockedHint}</p>}
    </fieldset>
  );
}

export function ProfileForm({ user }: { user: SessionUser }) {
  const t = useTranslations("auth.profile");
  const ts = useTranslations("freelances.signup");
  const tv = useTranslations("validation");
  const router = useRouter();
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");
  const [specialties, setSpecialties] = useState<string[]>(user.specialties ?? []);
  const [languages, setLanguages] = useState<string[]>(user.languages ?? []);

  // Les champs "collaborateur" ne concernent que les comptes freelance.
  const showCollaboratorFields = user.isCollaborator;

  function toggleSpecialty(label: string) {
    setSpecialties((prev) => (prev.includes(label) ? prev.filter((s) => s !== label) : [...prev, label]));
  }
  function toggleLanguage(label: string) {
    setLanguages((prev) => (prev.includes(label) ? prev.filter((s) => s !== label) : [...prev, label]));
  }

  // Un champ déjà renseigné ("validé") est verrouillé : cf. MeController::rejectIfLocked(),
  // même règle appliquée côté serveur — l'UI ne fait que refléter cette contrainte,
  // elle ne la remplace pas.
  const locked = {
    fullName: Boolean(user.fullName?.trim()),
    bio: Boolean(user.bio?.trim()),
    availability: Boolean(user.availability?.trim()),
    portfolioUrl: Boolean(user.portfolioUrl?.trim()),
    specialties: (user.specialties?.length ?? 0) > 0,
    yearsOfExperience: user.yearsOfExperience != null,
    city: Boolean(user.city?.trim()),
    linkedinUrl: Boolean(user.linkedinUrl?.trim()),
    githubUrl: Boolean(user.githubUrl?.trim()),
    languages: (user.languages?.length ?? 0) > 0,
  };
  const hasEditableField =
    !locked.fullName ||
    !locked.bio ||
    (showCollaboratorFields &&
      (!locked.specialties ||
        !locked.availability ||
        !locked.portfolioUrl ||
        !locked.yearsOfExperience ||
        !locked.city ||
        !locked.linkedinUrl ||
        !locked.githubUrl ||
        !locked.languages));

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema(tv)),
    defaultValues: {
      fullName: user.fullName ?? "",
      phone: user.phone ?? "",
      bio: user.bio ?? "",
      availability: user.availability ?? "",
      portfolioUrl: user.portfolioUrl ?? "",
      yearsOfExperience: user.yearsOfExperience != null ? String(user.yearsOfExperience) : "",
      city: user.city ?? "",
      linkedinUrl: user.linkedinUrl ?? "",
      githubUrl: user.githubUrl ?? "",
    },
  });

  async function onSubmit(values: ProfileValues) {
    setServerStatus("idle");
    setServerError("");

    // `phone` est volontairement exclu : le champ est désactivé côté UI
    // (cf. TextInput disabled ci-dessous) et non modifiable en self-service.
    // Les autres champs déjà verrouillés (cf. `locked` ci-dessus) sont aussi
    // exclus : rien à envoyer pour un champ que l'utilisateur ne peut pas
    // toucher, et le backend les rejetterait de toute façon (rejectIfLocked()).
    const payload: ProfileUpdatePayload = {};
    if (!locked.fullName) payload.fullName = (values.fullName ?? "").trim();
    if (!locked.bio) payload.bio = (values.bio ?? "").trim();
    if (showCollaboratorFields) {
      if (!locked.specialties) payload.specialties = specialties;
      if (!locked.availability) payload.availability = (values.availability ?? "").trim();
      if (!locked.portfolioUrl) payload.portfolioUrl = (values.portfolioUrl ?? "").trim();
      if (!locked.yearsOfExperience) payload.yearsOfExperience = values.yearsOfExperience ? Number(values.yearsOfExperience) : null;
      if (!locked.city) payload.city = (values.city ?? "").trim();
      if (!locked.linkedinUrl) payload.linkedinUrl = (values.linkedinUrl ?? "").trim();
      if (!locked.githubUrl) payload.githubUrl = (values.githubUrl ?? "").trim();
      if (!locked.languages) payload.languages = languages;
    }

    const result = await updateProfile(payload);
    if (result.ok) {
      reset(values);
      setServerStatus("success");
      router.refresh();
    } else {
      setServerError(result.error.replace(/^[a-zA-Z]+:\s*/, ""));
      setServerStatus("error");
    }
  }

  if (!hasEditableField) {
    return (
      <SettingsSectionGroup>
        <SettingsSection>
          <p className="text-center text-sm text-(--color-muted)">{t("allFieldsLocked")}</p>
        </SettingsSection>
      </SettingsSectionGroup>
    );
  }

  return (
    <SettingsSectionGroup>
      <SettingsSection>
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
          <TextInput
            label={t("fullNameLabel")}
            autoComplete="name"
            disabled={locked.fullName}
            hint={locked.fullName ? t("fieldLocked") : undefined}
            error={errors.fullName?.message}
            {...register("fullName")}
          />
          <TextInput
            label={t("phoneLabel")}
            type="tel"
            autoComplete="tel"
            disabled
            hint={t("phoneLocked")}
            error={errors.phone?.message}
            {...register("phone")}
          />
        </div>

        <TextArea
          label={t("bioLabel")}
          disabled={locked.bio}
          hint={locked.bio ? t("fieldLocked") : undefined}
          error={errors.bio?.message}
          {...register("bio")}
        />

        {showCollaboratorFields && (
          <>
            <ChipToggleGroup
              legend={t("specialtiesLabel")}
              options={SPECIALTY_KEYS.map((key) => ({ key, label: ts(key), icon: SPECIALTY_ICONS[key] }))}
              selected={specialties}
              onToggle={toggleSpecialty}
              disabled={locked.specialties}
              lockedHint={t("fieldLocked")}
            />

            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
              <TextInput
                label={t("availabilityLabel")}
                disabled={locked.availability}
                hint={locked.availability ? t("fieldLocked") : undefined}
                error={errors.availability?.message}
                {...register("availability")}
              />
              <TextInput
                label={t("portfolioLabel")}
                type="url"
                inputMode="url"
                disabled={locked.portfolioUrl}
                hint={locked.portfolioUrl ? t("fieldLocked") : undefined}
                error={errors.portfolioUrl?.message}
                {...register("portfolioUrl")}
              />
            </div>

            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
              <TextInput
                label={t("yearsOfExperienceLabel")}
                type="number"
                min={0}
                max={60}
                inputMode="numeric"
                disabled={locked.yearsOfExperience}
                hint={locked.yearsOfExperience ? t("fieldLocked") : undefined}
                error={errors.yearsOfExperience?.message}
                {...register("yearsOfExperience")}
              />
              <TextInput
                label={t("cityLabel")}
                autoComplete="address-level2"
                disabled={locked.city}
                hint={locked.city ? t("fieldLocked") : undefined}
                error={errors.city?.message}
                {...register("city")}
              />
            </div>

            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
              <TextInput
                label={t("linkedinLabel")}
                type="url"
                inputMode="url"
                placeholder="https://linkedin.com/in/..."
                disabled={locked.linkedinUrl}
                hint={locked.linkedinUrl ? t("fieldLocked") : undefined}
                error={errors.linkedinUrl?.message}
                {...register("linkedinUrl")}
              />
              <TextInput
                label={t("githubLabel")}
                type="url"
                inputMode="url"
                placeholder="https://github.com/..."
                disabled={locked.githubUrl}
                hint={locked.githubUrl ? t("fieldLocked") : undefined}
                error={errors.githubUrl?.message}
                {...register("githubUrl")}
              />
            </div>

            <ChipToggleGroup
              legend={t("languagesLabel")}
              options={LANGUAGE_KEYS.map((key) => ({ key, label: t(key) }))}
              selected={languages}
              onToggle={toggleLanguage}
              disabled={locked.languages}
              lockedHint={t("fieldLocked")}
            />
          </>
        )}

        <SubmitButton className="mt-2 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
          {t("submit")}
        </SubmitButton>

        {serverStatus === "success" && <FormMessage variant="success">{t("success")}</FormMessage>}
        {serverStatus === "error" && (
          <FormMessage variant="error">{serverError || t("error")}</FormMessage>
        )}
      </form>
      </SettingsSection>
    </SettingsSectionGroup>
  );
}
