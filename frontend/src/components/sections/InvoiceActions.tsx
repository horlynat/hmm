"use client";

import { useState, useTransition, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { CheckCircle2, MessageSquareWarning } from "lucide-react";
import { validateInvoice, requestInvoiceRevision } from "@/lib/auth/actions";
import { useRouter } from "@/i18n/navigation";
import type { SessionInvoice } from "@/lib/types";

/** Actions client sur une facture en attente : valider le montant, ou demander une révision (motif posté dans la discussion du projet). */
export function InvoiceActions({ invoice, locale }: { invoice: SessionInvoice; locale: string }) {
  const t = useTranslations("auth.invoices");
  const router = useRouter();
  const [isPending, startTransition] = useTransition();
  const [pendingAction, setPendingAction] = useState<"validate" | "revision" | null>(null);
  const [showRevisionForm, setShowRevisionForm] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState<string | null>(null);

  function handleValidate() {
    setError(null);
    setPendingAction("validate");
    startTransition(async () => {
      const result = await validateInvoice(invoice.id);
      if (result.ok) {
        router.refresh();
      } else {
        setError(t("genericError"));
      }
    });
  }

  function handleRevisionSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const trimmed = message.trim();
    if (!trimmed) {
      setError(t("revisionRequiredError"));
      return;
    }

    setError(null);
    setPendingAction("revision");
    startTransition(async () => {
      const result = await requestInvoiceRevision(invoice.id, trimmed);
      if (result.ok) {
        setShowRevisionForm(false);
        setMessage("");
        router.refresh();
      } else {
        setError(t("genericError"));
      }
    });
  }

  if (invoice.status === "revision_requested") {
    return <p className="mt-3 text-xs text-(--color-muted)">{t("revisionRequestedNote")}</p>;
  }

  if (invoice.validatedAt) {
    return (
      <p className="mt-3 flex items-center gap-1.5 text-xs font-medium text-success">
        <CheckCircle2 size={14} aria-hidden="true" />
        {t("validatedOn", { date: new Date(invoice.validatedAt).toLocaleDateString(locale) })}
      </p>
    );
  }

  if (invoice.status !== "pending") {
    return null;
  }

  return (
    <div className="mt-3 space-y-2">
      {!showRevisionForm ? (
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={handleValidate}
            disabled={isPending}
            className="btn-secondary !px-3 !py-2 gap-1.5 text-xs"
          >
            <CheckCircle2 size={14} aria-hidden="true" />
            {isPending && "validate" === pendingAction ? t("validating") : t("validateButton")}
          </button>
          <button
            type="button"
            onClick={() => setShowRevisionForm(true)}
            disabled={isPending}
            className="btn-secondary !px-3 !py-2 gap-1.5 text-xs"
          >
            <MessageSquareWarning size={14} aria-hidden="true" />
            {t("requestRevisionButton")}
          </button>
        </div>
      ) : (
        <form onSubmit={handleRevisionSubmit} className="space-y-2">
          <textarea
            value={message}
            onChange={(event) => setMessage(event.target.value)}
            placeholder={t("revisionPlaceholder")}
            rows={2}
            maxLength={2000}
            disabled={isPending}
            className="input text-xs"
          />
          <div className="flex flex-wrap gap-2">
            <button type="submit" disabled={isPending} className="btn-primary !px-3 !py-2 text-xs">
              {isPending && "revision" === pendingAction ? t("revisionSending") : t("revisionSubmit")}
            </button>
            <button
              type="button"
              disabled={isPending}
              onClick={() => {
                setShowRevisionForm(false);
                setError(null);
              }}
              className="btn-secondary !px-3 !py-2 text-xs"
            >
              {t("revisionCancel")}
            </button>
          </div>
        </form>
      )}
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  );
}
