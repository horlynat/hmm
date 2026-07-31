"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Card, LegalLink } from "@/components/ui";
import { TextInput, Checkbox, SubmitButton, FormMessage } from "@/components/ui/form";
import { clientRegisterSchema, type ClientRegisterValues } from "@/lib/validation/schemas";
import { mapActionError } from "@/lib/validation/errors";
import { registerClient } from "@/actions/client";

export function ClientForm() {
  const t = useTranslations("auth.register.client");
  const tv = useTranslations("validation");
  const [honeypot, setHoneypot] = useState("");
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");
  const [serverError, setServerError] = useState("");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ClientRegisterValues>({
    resolver: zodResolver(clientRegisterSchema(tv)),
    defaultValues: { name: "", email: "", phone: "", password: "" },
  });

  async function onSubmit(values: ClientRegisterValues) {
    if (honeypot.trim().length > 0) return; // anti-bot silencieux
    setServerStatus("idle");
    setServerError("");
    const result = await registerClient({
      name: values.name,
      email: values.email,
      phone: values.phone ?? "",
      password: values.password,
      agreeTerms: values.agreeTerms,
    });
    if (result.ok) {
      reset();
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
          <label htmlFor="cl-hp">Ne pas remplir ce champ</label>
          <input
            id="cl-hp"
            type="text"
            tabIndex={-1}
            autoComplete="off"
            value={honeypot}
            onChange={(e) => setHoneypot(e.target.value)}
          />
        </div>

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
          label={t("phoneLabel")}
          type="tel"
          autoComplete="tel"
          placeholder={t("phonePlaceholder")}
          error={errors.phone?.message}
          {...register("phone")}
        />

        <TextInput
          label={t("passwordLabel")}
          type="password"
          autoComplete="new-password"
          placeholder={t("passwordPlaceholder")}
          hint={t("passwordHint")}
          error={errors.password?.message}
          {...register("password")}
        />

        <div className="mt-1">
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
        </div>

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
