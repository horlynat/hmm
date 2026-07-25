"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Card } from "@/components/ui";
import { submitFreelanceApplication } from "@/actions/contact";

const SPECIALTY_KEYS = [
  "specialtyBackend",
  "specialtyFrontend",
  "specialtyMobile",
  "specialtyAi",
  "specialtyCyber",
  "specialtyDesign",
] as const;
const DISPO_KEYS = [
  "dispoImmediate",
  "dispo2Weeks",
  "dispo1Month",
  "dispoDiscuss",
] as const;

export function FreelanceForm() {
  const t = useTranslations("freelances.signup");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [specialties, setSpecialties] = useState<string[]>([]);
  const [availability, setAvailability] = useState(t(DISPO_KEYS[0]));
  const [link, setLink] = useState("");
  const [message, setMessage] = useState("");
  const [status, setStatus] = useState<
    "idle" | "submitting" | "success" | "error"
  >("idle");

  function toggleSpecialty(label: string) {
    setSpecialties((prev) =>
      prev.includes(label) ? prev.filter((s) => s !== label) : [...prev, label],
    );
  }

  async function handleSubmit() {
    setStatus("submitting");
    const result = await submitFreelanceApplication({
      name,
      email,
      specialties,
      availability,
      link,
      message,
    });
    setStatus(result.ok ? "success" : "error");
  }

  return (
    <Card variant="soft" className="p-8">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className="field-label" htmlFor="fl-name">
            {t("nameLabel")}
          </label>
          <input
            id="fl-name"
            className="input"
            placeholder={t("namePlaceholder")}
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </div>
        <div>
          <label className="field-label" htmlFor="fl-email">
            {t("emailLabel")}
          </label>
          <input
            id="fl-email"
            type="email"
            className="input"
            placeholder={t("emailPlaceholder")}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>
      </div>

      <div className="mt-4">
        <span className="field-label">{t("specialtiesLabel")}</span>
        <div className="flex flex-wrap gap-2">
          {SPECIALTY_KEYS.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => toggleSpecialty(t(key))}
              className={clsx(
                "rounded-full border px-3.5 py-2 font-mono text-xs",
                specialties.includes(t(key))
                  ? "border-brand-primary bg-brand-primary text-white"
                  : "border-[var(--border-soft)] bg-bg-default text-brand-primary",
              )}
            >
              {t(key)}
            </button>
          ))}
        </div>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className="field-label" htmlFor="fl-dispo">
            {t("dispoLabel")}
          </label>
          <select
            id="fl-dispo"
            className="input"
            value={availability}
            onChange={(e) => setAvailability(e.target.value)}
          >
            {DISPO_KEYS.map((key) => (
              <option key={key} value={t(key)}>
                {t(key)}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="field-label" htmlFor="fl-link">
            {t("linkLabel")}
          </label>
          <input
            id="fl-link"
            className="input"
            placeholder={t("linkPlaceholder")}
            value={link}
            onChange={(e) => setLink(e.target.value)}
          />
        </div>
      </div>

      <div className="mt-4">
        <label className="field-label" htmlFor="fl-message">
          {t("messageLabel")}
        </label>
        <textarea
          id="fl-message"
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
