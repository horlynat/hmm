"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Badge, Card } from "@/components/ui";
import { submitAppointmentRequest } from "@/actions/contact";

const SLOT_KEYS = [
  "slotMonMorning",
  "slotMonAfternoon",
  "slotTueMorning",
  "slotTueAfternoon",
  "slotWedMorning",
  "slotWedAfternoon",
] as const;
const SUBJECT_KEYS = [
  "subjectNew",
  "subjectQuestion",
  "subjectFollowup",
  "subjectOther",
] as const;

export function AppointmentForm() {
  const t = useTranslations("contact.rdv");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [slot, setSlot] = useState("");
  const [subject, setSubject] = useState(t(SUBJECT_KEYS[0]));
  const [message, setMessage] = useState("");
  const [status, setStatus] = useState<
    "idle" | "submitting" | "success" | "error"
  >("idle");

  async function handleSubmit() {
    setStatus("submitting");
    const result = await submitAppointmentRequest({
      name,
      email,
      slot,
      subject,
      message,
    });
    setStatus(result.ok ? "success" : "error");
  }

  return (
    <Card variant="soft" className="p-8">
      <Badge variant="accent">{t("badge")}</Badge>
      <h3
        className="mb-3 mt-3.5 text-lg font-semibold"
        style={{ fontFamily: "var(--font-heading)" }}
      >
        {t("title")}
      </h3>
      <p className="mb-5 text-sm opacity-70">{t("text")}</p>
      <ul className="mb-6 list-none space-y-2 p-0 text-sm opacity-80">
        <li>✓ {t("point1")}</li>
        <li>✓ {t("point2")}</li>
        <li>✓ {t("point3")}</li>
      </ul>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className="field-label" htmlFor="rdv-name">
            {t("nameLabel")}
          </label>
          <input
            id="rdv-name"
            className="input"
            placeholder={t("namePlaceholder")}
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </div>
        <div>
          <label className="field-label" htmlFor="rdv-email">
            {t("emailLabel")}
          </label>
          <input
            id="rdv-email"
            type="email"
            className="input"
            placeholder={t("emailPlaceholder")}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>
      </div>

      <div className="mt-4">
        <span className="field-label">{t("slotLabel")}</span>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
          {SLOT_KEYS.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => setSlot(t(key))}
              className={clsx(
                "rounded-[var(--radius-sm)] border px-2 py-2.5 text-center font-mono text-xs",
                slot === t(key)
                  ? "border-brand-primary bg-brand-primary text-white"
                  : "border-[var(--border-soft)] bg-bg-default",
              )}
            >
              {t(key)}
            </button>
          ))}
        </div>
      </div>

      <div className="mt-4">
        <label className="field-label" htmlFor="rdv-subject">
          {t("subjectLabel")}
        </label>
        <select
          id="rdv-subject"
          className="input"
          value={subject}
          onChange={(e) => setSubject(e.target.value)}
        >
          {SUBJECT_KEYS.map((key) => (
            <option key={key} value={t(key)}>
              {t(key)}
            </option>
          ))}
        </select>
      </div>

      <div className="mt-4">
        <label className="field-label" htmlFor="rdv-message">
          {t("messageLabel")}
        </label>
        <textarea
          id="rdv-message"
          className="input min-h-[100px]"
          placeholder={t("messagePlaceholder")}
          value={message}
          onChange={(e) => setMessage(e.target.value)}
        />
      </div>

      <button
        type="button"
        className="btn-primary mt-5 w-full"
        disabled={status === "submitting"}
        onClick={handleSubmit}
      >
        {status === "submitting" ? "…" : t("submit")}
      </button>

      {status === "success" && (
        <p className="mt-3 text-center text-sm font-semibold text-success">
          ✓ {t("success")}
        </p>
      )}
      {status === "error" && (
        <p className="mt-3 text-center text-sm text-danger">{t("error")}</p>
      )}
    </Card>
  );
}
