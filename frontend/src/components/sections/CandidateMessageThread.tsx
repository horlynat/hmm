"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations, useLocale } from "next-intl";
import clsx from "clsx";
import { Card, EmptyState } from "@/components/ui";
import { TextArea, SubmitButton, FormMessage } from "@/components/ui/form";
import { candidateMessageSchema, type CandidateMessageValues } from "@/lib/validation/schemas";
import { sendCandidateMessage } from "@/lib/auth/actions";
import type { CandidateMessage } from "@/lib/types";

/**
 * Fil de conversation candidat <-> admin de /compte/messages. Même structure
 * visuelle que SupportTicketThread (bulles alignées par expéditeur), mais
 * authentifiée via sendCandidateMessage() (Bearer token) plutôt que par
 * jeton d'accès invité — pas de statut de ticket à afficher, c'est une
 * conversation continue, pas une demande ponctuelle à résoudre.
 */
export function CandidateMessageThread({ messages: initialMessages }: { messages: CandidateMessage[] }) {
  const t = useTranslations("auth.account.messagesPage");
  const tv = useTranslations("validation");
  const locale = useLocale();
  const [messages, setMessages] = useState(initialMessages);
  const [serverStatus, setServerStatus] = useState<"idle" | "success" | "error">("idle");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CandidateMessageValues>({
    resolver: zodResolver(candidateMessageSchema(tv)),
    defaultValues: { body: "" },
  });

  return (
    <div className="space-y-6">
      <Card variant="soft" className="p-6">
        {messages.length > 0 ? (
          <div className="space-y-3">
            {messages.map((message, index) => (
              <div
                key={message.id ?? index}
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
                    {message.fromAdmin ? t("teamLabel") : t("youLabel")} —{" "}
                    {new Date(message.createdAt).toLocaleString(locale)}
                  </p>
                  <p className="whitespace-pre-line leading-relaxed">{message.body}</p>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <EmptyState icon="💬" message={t("empty")} />
        )}
      </Card>

      <Card variant="soft" className="p-6">
        <form
          noValidate
          onSubmit={handleSubmit(async (values) => {
            setServerStatus("idle");
            const result = await sendCandidateMessage(values.body);
            if (result.ok) {
              setMessages((prev) => [
                ...prev,
                result.message ?? {
                  id: -Date.now(),
                  body: values.body,
                  fromAdmin: false,
                  createdAt: new Date().toISOString(),
                },
              ]);
              reset();
              setServerStatus("success");
            } else {
              setServerStatus("error");
            }
          })}
        >
          <TextArea
            label={t("replyLabel")}
            placeholder={t("replyPlaceholder")}
            rows={4}
            error={errors.body?.message}
            {...register("body")}
          />
          <SubmitButton pending={isSubmitting} pendingLabel={t("replySubmit")}>
            {t("replySubmit")}
          </SubmitButton>

          {serverStatus === "success" && <FormMessage variant="success">{t("replySuccess")}</FormMessage>}
          {serverStatus === "error" && <FormMessage variant="error">{t("replyError")}</FormMessage>}
        </form>
      </Card>
    </div>
  );
}
