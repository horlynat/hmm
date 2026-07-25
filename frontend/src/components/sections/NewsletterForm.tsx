"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";

/** Stub visuel — aucune entité Newsletter/Subscriber côté backend, cf. plan. */
export function NewsletterForm() {
  const t = useTranslations("blog.newsletter");
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState<"idle" | "success">("idle");

  function handleSubmit() {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
    setStatus("success");
  }

  return (
    <div
      className="mx-auto max-w-[1120px] rounded-[var(--radius-lg)] px-6 py-10 text-center text-white"
      style={{
        background:
          "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 80%)",
      }}
    >
      <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)] text-white">{t("title")}</h2>
      <p className="mx-auto mb-5 max-w-[52ch] opacity-85">{t("text")}</p>
      <div className="mx-auto flex max-w-[420px] flex-wrap justify-center gap-2.5">
        <input
          type="email"
          placeholder={t("placeholder")}
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="min-w-[200px] flex-1 rounded-[var(--radius-sm)] border border-white/30 bg-white/10 px-4 py-2.5 text-sm text-white placeholder:text-white/70"
        />
        <button
          type="button"
          onClick={handleSubmit}
          className="rounded-[var(--radius-sm)] bg-white px-4 py-2.5 text-sm font-semibold text-brand-dark"
        >
          {t("submit")}
        </button>
      </div>
      {status === "success" && (
        <p className="mt-4 text-sm font-semibold">✓ {t("success")}</p>
      )}
      <p className="mt-3 text-xs opacity-70">{t("note")}</p>
    </div>
  );
}
