"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Card } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { Link } from "@/i18n/navigation";
import { resetPasswordSchema, type ResetPasswordValues } from "@/lib/validation/schemas";
import { confirmPasswordReset } from "@/lib/auth/actions";

export function ResetPasswordForm({ token }: { token: string }) {
  const t = useTranslations("auth.resetPassword");
  const tv = useTranslations("validation");
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema(tv)),
    defaultValues: { password: "", confirmPassword: "" },
  });

  async function onSubmit(values: ResetPasswordValues) {
    setStatus("idle");
    setServerError("");
    const result = await confirmPasswordReset(token, values.password);
    if (result.ok) {
      setStatus("success");
    } else {
      setServerError(result.error);
      setStatus("error");
    }
  }

  if (status === "success") {
    return (
      <Card variant="soft" className="p-8">
        <FormMessage variant="success">{t("success")}</FormMessage>
        <Link
          href="/connexion"
          className="mt-4 block text-center text-sm font-semibold text-brand-primary hover:underline"
        >
          {t("loginLink")}
        </Link>
      </Card>
    );
  }

  return (
    <Card variant="soft" className="p-8">
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <TextInput
          label={t("passwordLabel")}
          type="password"
          autoComplete="new-password"
          error={errors.password?.message}
          {...register("password")}
        />
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

        {status === "error" && <FormMessage variant="error">{serverError || t("error")}</FormMessage>}
      </form>
    </Card>
  );
}
