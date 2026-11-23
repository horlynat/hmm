"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { submitSupportTicket } from "@/actions/supportTicket";
import { supportTicketSchema, type SupportTicketValues } from "@/lib/validation/schemas";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FieldWrap } from "@/components/auth-proto/FieldWrap";

/** Nouveau ticket de support (`.ph .acard .auth`), logique identique à `SupportTicketForm`. */
export function SupportTicketFormPanel() {
  const t = useTranslations("supportTicket.form");
  const tv = useTranslations("validation");
  const [status, setStatus] = useState<"idle" | "success" | "error" | "rate_limited">("idle");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SupportTicketValues>({ resolver: zodResolver(supportTicketSchema(tv)), defaultValues: { name: "", email: "", subject: "", message: "" } });

  async function onSubmit(values: SupportTicketValues) {
    setStatus("idle");
    const result = await submitSupportTicket(values);
    if (result.ok) {
      reset();
      setStatus("success");
    } else {
      setStatus(result.error === "rate_limited" ? "rate_limited" : "error");
    }
  }

  if (status === "success") {
    return (
      <div className="auth" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h2 tabIndex={-1}>{t("title")}</h2>
        <p className="mut">{t("success")}</p>
      </div>
    );
  }

  return (
    <form className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
      <h2 tabIndex={-1}>
        {t("title")} {t("titleAccent")}
      </h2>
      <p className="mut">{t("sub")}</p>
      <div className="g2">
        <FieldWrap id="st-name" label={t("nameLabel")} error={errors.name?.message}>
          <input id="st-name" className="fi" autoComplete="name" aria-invalid={errors.name ? true : undefined} {...register("name")} />
        </FieldWrap>
        <FieldWrap id="st-email" label={t("emailLabel")} error={errors.email?.message}>
          <input id="st-email" className="fi" type="email" autoComplete="email" aria-invalid={errors.email ? true : undefined} {...register("email")} />
        </FieldWrap>
      </div>
      <FieldWrap id="st-subject" label={t("subjectLabel")} error={errors.subject?.message}>
        <input id="st-subject" className="fi" aria-invalid={errors.subject ? true : undefined} {...register("subject")} />
      </FieldWrap>
      <FieldWrap id="st-message" label={t("messageLabel")} error={errors.message?.message}>
        <textarea id="st-message" className="fi" rows={6} placeholder={t("messagePlaceholder")} aria-invalid={errors.message ? true : undefined} {...register("message")} />
      </FieldWrap>
      {status === "error" && (
        <p className="qerr" role="alert">
          {t("error")}
        </p>
      )}
      {status === "rate_limited" && (
        <p className="qerr" role="alert">
          {t("rateLimited")}
        </p>
      )}
      <button className="b b-p wide" disabled={isSubmitting}>
        {t("submit")}
      </button>
    </form>
  );
}
