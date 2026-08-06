"use client";

import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { SettingsSection, SettingsSectionGroup } from "@/components/ui";
import { TextInput, PasswordStrength, SubmitButton, FormMessage } from "@/components/ui/form";
import { changePasswordSchema, type ChangePasswordValues } from "@/lib/validation/schemas";
import { changePassword } from "@/lib/auth/actions";

export function PasswordChangeForm() {
  const t = useTranslations("auth.changePassword");
  const tv = useTranslations("validation");
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    control,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema(tv)),
    defaultValues: { currentPassword: "", password: "", confirmPassword: "" },
  });

  const newPassword = useWatch({ control, name: "password" });

  async function onSubmit(values: ChangePasswordValues) {
    setServerStatus("idle");
    setServerError("");

    const result = await changePassword(values.currentPassword, values.password);
    if (result.ok) {
      reset({ currentPassword: "", password: "", confirmPassword: "" });
      setServerStatus("success");
    } else {
      setServerError(result.error.replace(/^[a-zA-Z]+:\s*/, ""));
      setServerStatus("error");
    }
  }

  return (
    <SettingsSectionGroup>
      <SettingsSection>
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <TextInput
          label={t("currentPasswordLabel")}
          type="password"
          autoComplete="current-password"
          error={errors.currentPassword?.message}
          {...register("currentPassword")}
        />

        <TextInput
          label={t("newPasswordLabel")}
          type="password"
          autoComplete="new-password"
          hint={t("passwordHint")}
          error={errors.password?.message}
          {...register("password")}
        />
        <PasswordStrength value={newPassword ?? ""} />

        <TextInput
          label={t("confirmPasswordLabel")}
          type="password"
          autoComplete="new-password"
          error={errors.confirmPassword?.message}
          {...register("confirmPassword")}
        />

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
