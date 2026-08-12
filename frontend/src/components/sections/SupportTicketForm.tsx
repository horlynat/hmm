"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Card } from "@/components/ui";
import { TextInput, TextArea, SubmitButton, FormMessage } from "@/components/ui/form";
import { supportTicketSchema, type SupportTicketValues } from "@/lib/validation/schemas";
import { submitSupportTicket } from "@/actions/supportTicket";

export function SupportTicketForm() {
  const t = useTranslations("supportTicket.form");
  const tv = useTranslations("validation");
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error" | "rate_limited">("idle");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SupportTicketValues>({
    resolver: zodResolver(supportTicketSchema(tv)),
    defaultValues: { name: "", email: "", subject: "", message: "" },
  });

  if (serverStatus === "success") {
    return (
      <Card variant="soft" className="p-8">
        <FormMessage variant="success">{t("success")}</FormMessage>
      </Card>
    );
  }

  return (
    <Card variant="soft" className="p-8">
      <form
        noValidate
        onSubmit={handleSubmit(async (values) => {
          setServerStatus("idle");
          const result = await submitSupportTicket(values);
          if (result.ok) {
            reset();
            setServerStatus("success");
          } else {
            setServerStatus(result.error === "rate_limited" ? "rate_limited" : "error");
          }
        })}
      >
        <TextInput
          label={t("nameLabel")}
          autoComplete="name"
          error={errors.name?.message}
          {...register("name")}
        />
        <TextInput
          label={t("emailLabel")}
          type="email"
          autoComplete="email"
          error={errors.email?.message}
          {...register("email")}
        />
        <TextInput
          label={t("subjectLabel")}
          error={errors.subject?.message}
          {...register("subject")}
        />
        <TextArea
          label={t("messageLabel")}
          placeholder={t("messagePlaceholder")}
          rows={6}
          error={errors.message?.message}
          {...register("message")}
        />

        <SubmitButton className="mt-2 w-full" pending={isSubmitting} pendingLabel={t("submit")}>
          {t("submit")}
        </SubmitButton>

        {serverStatus === "error" && <FormMessage variant="error">{t("error")}</FormMessage>}
        {serverStatus === "rate_limited" && <FormMessage variant="error">{t("rateLimited")}</FormMessage>}
      </form>
    </Card>
  );
}
