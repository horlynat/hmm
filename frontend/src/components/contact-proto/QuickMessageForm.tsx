"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { submitContactMessage } from "@/actions/contact";
import { isValidEmail } from "@/lib/quoteWizard/validators";
import type { Offer } from "@/lib/types";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { useFocusHeadingOnChange } from "@/lib/useFocusHeadingOnChange";

/**
 * Onglet « Message rapide » (prototype `directForm()` : `.g2 .fl2 .fi .e
 * .cons .ck`), branché sur l'action réelle `submitContactMessage`
 * (POST /contact_messages, `source: "Message rapide"`). Le menu « Votre
 * besoin » liste les offres réellement publiées, comme le prototype
 * (`${OFFERS.map(...)}`), jamais une liste figée.
 */
type FieldErrors = Partial<Record<"name" | "email" | "message" | "consent", boolean>>;

export function QuickMessageForm({ offers }: { offers: Offer[] }) {
  const t = useTranslations("contactPage");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [subject, setSubject] = useState(offers[0]?.title ?? "");
  const [message, setMessage] = useState("");
  const [consent, setConsent] = useState(false);
  const [honeypot, setHoneypot] = useState("");
  const [errors, setErrors] = useState<FieldErrors>({});
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (honeypot.trim().length > 0) return; // anti-bot silencieux — avant tout appel réseau

    const nextErrors: FieldErrors = {
      name: name.trim().length === 0,
      email: !isValidEmail(email),
      message: message.trim().length === 0,
      consent: !consent,
    };
    setErrors(nextErrors);
    if (Object.values(nextErrors).some(Boolean)) return;

    setSubmitting(true);
    setSubmitError(null);
    const result = await submitContactMessage({
      source: "Message rapide",
      name: name.trim(),
      email: email.trim(),
      subject: subject || t("quickSubjectOther"),
      message: message.trim(),
    });
    setSubmitting(false);
    if (result.ok) {
      setSent(true);
    } else {
      setSubmitError(result.error === "rate_limited" ? t("quickErrorRateLimited") : t("quickErrorSubmit"));
    }
  }

  // Focus sur le titre du nouvel écran (formulaire -> succès…), cf. useFocusHeadingOnChange.
  const focusRef = useFocusHeadingOnChange(sent);

  if (sent) {
    return (
      <div ref={focusRef} className="qdone" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h3 tabIndex={-1}>{t("quickSuccessTitle")}</h3>
        <p className="mut">{t("quickSuccessText")}</p>
      </div>
    );
  }

  return (
    <form ref={focusRef} noValidate onSubmit={onSubmit} style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
      <div className="hp" aria-hidden="true">
        <label htmlFor="quick-hp">{t("honeypotLabel")}</label>
        <input id="quick-hp" name="website" tabIndex={-1} autoComplete="off" value={honeypot} onChange={(e) => setHoneypot(e.target.value)} />
      </div>

      <div className="g2">
        <div className={errors.name ? "fl2 err" : "fl2"}>
          <label htmlFor="quick-name">{t("quickName")}</label>
          <input
            id="quick-name"
            className="fi"
            autoComplete="name"
            placeholder={t("quickNamePlaceholder")}
            aria-invalid={errors.name ? true : undefined}
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          <span className="e" role="alert">
            {t("quickErrorName")}
          </span>
        </div>
        <div className={errors.email ? "fl2 err" : "fl2"}>
          <label htmlFor="quick-email">{t("quickEmail")}</label>
          <input
            id="quick-email"
            type="email"
            inputMode="email"
            className="fi"
            autoComplete="email"
            placeholder={t("quickEmailPlaceholder")}
            aria-invalid={errors.email ? true : undefined}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <span className="e" role="alert">
            {t("quickErrorEmail")}
          </span>
        </div>
      </div>

      {offers.length > 0 && (
        <div className="fl2">
          <label htmlFor="quick-subject">{t("quickSubject")}</label>
          <select id="quick-subject" className="fi" value={subject} onChange={(e) => setSubject(e.target.value)}>
            {offers.map((o) => (
              <option key={o.slug} value={o.title}>
                {o.title}
              </option>
            ))}
            <option value={t("quickSubjectOther")}>{t("quickSubjectOther")}</option>
          </select>
        </div>
      )}

      <div className={errors.message ? "fl2 err" : "fl2"}>
        <label htmlFor="quick-message">{t("quickMessage")}</label>
        <textarea
          id="quick-message"
          className="fi"
          rows={4}
          placeholder={t("quickMessagePlaceholder")}
          aria-invalid={errors.message ? true : undefined}
          value={message}
          onChange={(e) => setMessage(e.target.value)}
        />
        <span className="e" role="alert">
          {t("quickErrorMessage")}
        </span>
      </div>

      <div className={errors.consent ? "fl2 cons err" : "fl2 cons"}>
        <label className="ck">
          <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
          <span>
            {t.rich("quickConsent", {
              privacy: (chunks) => (
                <Link className="lnk" href="/politique-de-confidentialite">
                  {chunks}
                </Link>
              ),
            })}
          </span>
        </label>
        <span className="e" role="alert">
          {t("quickErrorConsent")}
        </span>
      </div>

      {submitError && (
        <p className="qerr" role="alert">
          {submitError}
        </p>
      )}

      <button type="submit" className="b b-p" style={{ alignSelf: "flex-start" }} disabled={submitting}>
        {submitting ? t("quickSending") : t("quickSend")}
      </button>
    </form>
  );
}
