"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Card } from "@/components/ui";
import { TextInput, TextArea, SubmitButton, FormMessage } from "@/components/ui/form";
import { profileSchema, type ProfileValues } from "@/lib/validation/schemas";
import { useRouter } from "@/i18n/navigation";
import { updateProfile } from "@/lib/auth/actions";
import type { ProfileUpdatePayload, SessionUser } from "@/lib/types";

export function ProfileForm({ user }: { user: SessionUser }) {
  const t = useTranslations("auth.profile");
  const tv = useTranslations("validation");
  const router = useRouter();
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  // Les champs "collaborateur" ne concernent que les comptes freelance.
  const showCollaboratorFields = user.isCollaborator;

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
      specialties: (user.specialties ?? []).join(", "),
      availability: user.availability ?? "",
      portfolioUrl: user.portfolioUrl ?? "",
      password: "",
    },
  });

  async function onSubmit(values: ProfileValues) {
    setServerStatus("idle");
    setServerError("");

    const payload: ProfileUpdatePayload = {
      fullName: (values.fullName ?? "").trim(),
      phone: (values.phone ?? "").trim(),
      bio: (values.bio ?? "").trim(),
    };
    if (showCollaboratorFields) {
      payload.specialties = (values.specialties ?? "")
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);
      payload.availability = (values.availability ?? "").trim();
      payload.portfolioUrl = (values.portfolioUrl ?? "").trim();
    }
    if (values.password && values.password.length > 0) {
      payload.plainPassword = values.password;
    }

    const result = await updateProfile(payload);
    if (result.ok) {
      reset({ ...values, password: "" });
      setServerStatus("success");
      router.refresh();
    } else {
      setServerError(result.error.replace(/^[a-zA-Z]+:\s*/, ""));
      setServerStatus("error");
    }
  }

  return (
    <Card variant="soft" className="p-8">
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
          <TextInput
            label={t("fullNameLabel")}
            autoComplete="name"
            error={errors.fullName?.message}
            {...register("fullName")}
          />
          <TextInput
            label={t("phoneLabel")}
            type="tel"
            autoComplete="tel"
            error={errors.phone?.message}
            {...register("phone")}
          />
        </div>

        <TextArea label={t("bioLabel")} error={errors.bio?.message} {...register("bio")} />

        {showCollaboratorFields && (
          <>
            <TextInput
              label={t("specialtiesLabel")}
              placeholder={t("specialtiesPlaceholder")}
              error={errors.specialties?.message}
              {...register("specialties")}
            />
            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
              <TextInput
                label={t("availabilityLabel")}
                error={errors.availability?.message}
                {...register("availability")}
              />
              <TextInput
                label={t("portfolioLabel")}
                type="url"
                inputMode="url"
                error={errors.portfolioUrl?.message}
                {...register("portfolioUrl")}
              />
            </div>
          </>
        )}

        <TextInput
          label={t("passwordLabel")}
          type="password"
          autoComplete="new-password"
          placeholder={t("passwordPlaceholder")}
          hint={t("passwordHint")}
          error={errors.password?.message}
          {...register("password")}
        />

        <SubmitButton className="mt-2 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
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
