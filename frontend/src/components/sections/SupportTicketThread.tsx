"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations, useLocale } from "next-intl";
import clsx from "clsx";
import { Badge, Card } from "@/components/ui";
import { TextArea, SubmitButton, FormMessage } from "@/components/ui/form";
import { supportTicketReplySchema, type SupportTicketReplyValues } from "@/lib/validation/schemas";
import { replySupportTicket } from "@/actions/supportTicket";
import type { SupportTicketThread as SupportTicketThreadData } from "@/lib/types";

const STATUS_VARIANT = {
  open: "info",
  in_progress: "warning",
  resolved: "success",
} as const;

export function SupportTicketThread({ token, thread }: { token: string; thread: SupportTicketThreadData }) {
  const t = useTranslations("supportTicket.thread");
  const tv = useTranslations("validation");
  const locale = useLocale();
  const [messages, setMessages] = useState(thread.messages);
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error" | "rate_limited">("idle");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SupportTicketReplyValues>({
    resolver: zodResolver(supportTicketReplySchema(tv)),
    defaultValues: { message: "" },
  });

  return (
    <div className="space-y-6">
      <Card variant="soft" className="p-6">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-lg font-bold" style={{ fontFamily: "var(--font-heading)" }}>
            {thread.subject}
          </h1>
          <Badge variant={STATUS_VARIANT[thread.status]}>{thread.statusLabel}</Badge>
        </div>

        <div className="space-y-3">
          {messages.map((message, index) => (
            <div
              key={`${message.createdAt}-${index}`}
              className={clsx("flex", message.fromAdmin ? "justify-start" : "justify-end")}
            >
              <div
                className={clsx(
                  "max-w-[80%] rounded-2xl px-4 py-3 text-sm",
                  message.fromAdmin
                    ? "bg-brand-light/60 text-[var(--color-on-brand-light)]"
                    : "bg-brand-primary text-[var(--color-on-brand-primary)]",
                )}
              >
                <p className="mb-1 text-xs opacity-70">
                  {message.fromAdmin ? t("supportLabel") : t("youLabel")} —{" "}
                  {new Date(message.createdAt).toLocaleString(locale)}
                </p>
                <p className="whitespace-pre-line leading-relaxed">{message.body}</p>
              </div>
            </div>
          ))}
        </div>
      </Card>

      <Card variant="soft" className="p-6">
        <form
          noValidate
          onSubmit={handleSubmit(async (values) => {
            setServerStatus("idle");
            const result = await replySupportTicket(token, values.message);
            if (result.ok) {
              setMessages((prev) => [
                ...prev,
                { body: values.message, fromAdmin: false, createdAt: new Date().toISOString() },
              ]);
              reset();
              setServerStatus("success");
            } else {
              setServerStatus(result.error === "rate_limited" ? "rate_limited" : "error");
            }
          })}
        >
          <TextArea
            label={t("replyLabel")}
            placeholder={t("replyPlaceholder")}
            rows={4}
            error={errors.message?.message}
            {...register("message")}
          />
          <SubmitButton pending={isSubmitting} pendingLabel={t("replySubmit")}>
            {t("replySubmit")}
          </SubmitButton>

          {serverStatus === "success" && <FormMessage variant="success">{t("replySuccess")}</FormMessage>}
          {serverStatus === "error" && <FormMessage variant="error">{t("replyError")}</FormMessage>}
          {serverStatus === "rate_limited" && <FormMessage variant="error">{t("replyRateLimited")}</FormMessage>}
        </form>
      </Card>
    </div>
  );
}
