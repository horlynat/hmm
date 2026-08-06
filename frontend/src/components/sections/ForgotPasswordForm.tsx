"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Card } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { forgotPasswordSchema, type ForgotPasswordValues } from "@/lib/validation/schemas";
import { requestPasswordReset } from "@/lib/auth/actions";

export function ForgotPasswordForm() {
  const t = useTranslations("auth.forgotPassword");
  const tv = useTranslations("validation");
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordValues>({
    resolver: zodResolver(forgotPasswordSchema(tv)),
    defaultValues: { email: "" },
  });

  async function onSubmit(values: ForgotPasswordValues) {
    setStatus("idle");
    setServerError("");
    const result = await requestPasswordReset(values.email);
    if (result.ok) {
      setStatus("success");
    } else {
      setServerError(result.error);
      setStatus("error");
    }
  }

  return (
    <Card variant="soft" className="p-8">
      {status === "success" ? (
        <FormMessage variant="success">{t("success")}</FormMessage>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <TextInput
            label={t("emailLabel")}
            type="email"
            autoComplete="email"
            placeholder={t("emailPlaceholder")}
            error={errors.email?.message}
            {...register("email")}
          />

          <SubmitButton className="mt-2 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
            {t("submit")}
          </SubmitButton>

          {status === "error" && <FormMessage variant="error">{serverError || t("error")}</FormMessage>}
        </form>
      )}
    </Card>
  );
}
