"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Card } from "@/components/ui";
import { TextInput, SubmitButton, FormMessage } from "@/components/ui/form";
import { loginSchema, type LoginValues } from "@/lib/validation/schemas";
import { Link, useRouter } from "@/i18n/navigation";
import { login } from "@/lib/auth/actions";

export function LoginForm() {
  const t = useTranslations("auth.login");
  const tv = useTranslations("validation");
  const router = useRouter();
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema(tv)),
    defaultValues: { email: "", password: "" },
  });

  async function onSubmit(values: LoginValues) {
    setServerError("");
    const result = await login(values.email, values.password);
    if (result.ok) {
      // Recharge les Server Components avec la nouvelle session (cookie serveur).
      router.push("/compte");
      router.refresh();
    } else {
      setServerError(
        result.error === "invalid_credentials"
          ? t("invalidCredentials")
          : t("error"),
      );
    }
  }

  return (
    <Card variant="soft" className="p-8">
      <div
        aria-hidden="true"
        className="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-brand-primary/10 text-2xl"
      >
        🔒
      </div>

      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <TextInput
          label={t("emailLabel")}
          type="email"
          autoComplete="email"
          placeholder={t("emailPlaceholder")}
          error={errors.email?.message}
          {...register("email")}
        />

        <TextInput
          label={t("passwordLabel")}
          type="password"
          autoComplete="current-password"
          placeholder={t("passwordPlaceholder")}
          error={errors.password?.message}
          {...register("password")}
        />

        <SubmitButton className="mt-2 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
          {t("submit")}
        </SubmitButton>

        {serverError && <FormMessage variant="error">{serverError}</FormMessage>}

        <p className="mt-4 flex items-center justify-center gap-1.5 text-xs text-[var(--color-muted)]">
          <span aria-hidden="true">🔏</span>
          {t("secureBadge3")}
        </p>
      </form>

      <div className="my-6 border-t border-[var(--border-softer)]" />

      <p className="text-center text-sm opacity-70">
        {t("noAccount")}{" "}
        <Link href="/inscription" className="font-semibold text-brand-primary hover:underline">
          {t("registerLink")}
        </Link>
      </p>
    </Card>
  );
}
