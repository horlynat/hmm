"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useLocale, useTranslations } from "next-intl";
import { replySupportTicket } from "@/actions/supportTicket";
import { supportTicketReplySchema, type SupportTicketReplyValues } from "@/lib/validation/schemas";
import type { SupportTicketThread } from "@/lib/types";

/** Fil d'un ticket de support — bulles du prototype (`.log .bub .bu .bb`), logique identique à `SupportTicketThread`. */
export function SupportTicketThreadPanel({ token, thread }: { token: string; thread: SupportTicketThread }) {
  const t = useTranslations("supportTicket.thread");
  const tv = useTranslations("validation");
  const locale = useLocale();
  const [messages, setMessages] = useState(thread.messages);
  const [status, setStatus] = useState<"idle" | "success" | "error" | "rate_limited">("idle");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SupportTicketReplyValues>({ resolver: zodResolver(supportTicketReplySchema(tv)), defaultValues: { message: "" } });

  async function onSubmit(values: SupportTicketReplyValues) {
    setStatus("idle");
    const result = await replySupportTicket(token, values.message);
    if (result.ok) {
      setMessages((prev) => [...prev, { body: values.message, fromAdmin: false, createdAt: new Date().toISOString() }]);
      reset();
      setStatus("success");
    } else {
      setStatus(result.error === "rate_limited" ? "rate_limited" : "error");
    }
  }

  return (
    <>
      <div className="acard">
        <div style={{ padding: "24px 28px" }}>
          <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "12px", marginBottom: "16px" }}>
            <h1 style={{ fontSize: "20px" }}>{thread.subject}</h1>
            <span className="tag">{thread.statusLabel}</span>
          </div>
          <div className="log" style={{ height: "auto", maxHeight: "420px" }} role="log">
            {messages.map((m, i) => (
              <div key={`${m.createdAt}-${i}`} className={m.fromAdmin ? "bub bb" : "bub bu"}>
                <small style={{ display: "block", opacity: 0.7, marginBottom: "4px" }}>
                  {m.fromAdmin ? t("supportLabel") : t("youLabel")} — {new Date(m.createdAt).toLocaleString(locale)}
                </small>
                <span style={{ whiteSpace: "pre-line" }}>{m.body}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
      <div className="acard" style={{ marginTop: "20px" }}>
        <form className="auth" noValidate onSubmit={handleSubmit(onSubmit)}>
          <div className="fl2" id="f-reply">
            <label htmlFor="reply">{t("replyLabel")}</label>
            <textarea id="reply" className="fi" rows={4} placeholder={t("replyPlaceholder")} aria-invalid={errors.message ? true : undefined} {...register("message")} />
            <span className="e" role="alert">
              {errors.message?.message}
            </span>
          </div>
          {status === "success" && (
            <p className="asec" style={{ margin: 0 }}>
              {t("replySuccess")}
            </p>
          )}
          {status === "error" && (
            <p className="qerr" role="alert">
              {t("replyError")}
            </p>
          )}
          {status === "rate_limited" && (
            <p className="qerr" role="alert">
              {t("replyRateLimited")}
            </p>
          )}
          <button className="b b-p" disabled={isSubmitting} style={{ alignSelf: "flex-start" }}>
            {t("replySubmit")}
          </button>
        </form>
      </div>
    </>
  );
}
