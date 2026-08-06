"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import type { AiAssistantEntry, AiAssistantSettings } from "@/lib/types";

interface Message {
  who: "bot" | "user";
  text: string;
}

interface AiAssistantWidgetProps {
  settings: AiAssistantSettings;
  entries: AiAssistantEntry[];
}

/**
 * Widget purement local : réponses par mots-clés, sans appel à un backend IA
 * (aucun endpoint de ce type n'existe côté API). `settings`/`entries` sont
 * résolus côté serveur (App\Entity\AiAssistantSettings/AiAssistantEntry, via
 * src/lib/api/ai-assistant.ts) et passés en props depuis le layout, pour que
 * l'assistant reste exact quand le profil change — plus de réponses figées
 * dans le code frontend.
 */
export function AiAssistantWidget({ settings, entries }: AiAssistantWidgetProps) {
  const t = useTranslations("aiAssistant");
  const tc = useTranslations("common");
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    { who: "bot", text: settings.greeting },
  ]);
  const [input, setInput] = useState("");

  function addMessage(text: string, who: Message["who"]) {
    setMessages((prev) => [...prev, { who, text }]);
  }

  function respond(userText: string) {
    const lower = userText.toLowerCase();
    const matched = entries.find((entry) =>
      entry.keywords.some((kw) => lower.includes(kw.toLowerCase())),
    );
    const answer = matched ? matched.answer : settings.fallback;
    window.setTimeout(() => addMessage(answer, "bot"), 350);
  }

  function send() {
    const value = input.trim();
    if (!value) return;
    addMessage(value, "user");
    setInput("");
    respond(value);
  }

  function askChip(entry: AiAssistantEntry) {
    setOpen(true);
    addMessage(entry.chipLabel, "user");
    window.setTimeout(() => addMessage(entry.answer, "bot"), 350);
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="fixed bottom-6 right-6 z-40 flex items-center gap-2.5 rounded-full px-5 py-3.5 text-sm font-bold text-white shadow-lg"
        style={{
          fontFamily: "var(--font-heading)",
          background:
            "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 70%)",
        }}
      >
        <span className="h-2 w-2 shrink-0 animate-pulse rounded-full bg-brand-light motion-reduce:animate-none" />
        <span className="hidden sm:inline">{tc("ctaParlerAssistant")}</span>
      </button>

      <div
        role="dialog"
        aria-label={t("title")}
        className={clsx(
          "fixed bottom-24 right-6 z-50 flex h-[480px] max-h-[calc(100vh-8rem)] w-[360px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-soft)] bg-bg-card shadow-2xl",
          open ? "flex" : "hidden",
        )}
      >
        <div
          className="flex items-center justify-between gap-2 px-4 py-3.5 text-white"
          style={{
            background:
              "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 70%)",
          }}
        >
          <div>
            <div className="flex items-center gap-2 text-sm font-bold" style={{ fontFamily: "var(--font-heading)" }}>
              <span className="h-2 w-2 rounded-full bg-brand-light" />
              {t("title")}
            </div>
            <div className="font-mono text-[0.68rem] opacity-85">{t("sub")}</div>
          </div>
          <button
            type="button"
            onClick={() => setOpen(false)}
            aria-label={t("close")}
            className="text-lg leading-none"
          >
            ×
          </button>
        </div>

        <div className="flex-1 space-y-2.5 overflow-y-auto p-4" aria-live="polite">
          {messages.map((m, i) => (
            <div
              key={i}
              className={clsx(
                "max-w-[85%] rounded-[var(--radius-md)] px-3.5 py-2.5 text-sm leading-relaxed",
                m.who === "bot"
                  ? "self-start rounded-bl-[4px] bg-brand-light text-[var(--color-on-brand-light)]"
                  : "ml-auto rounded-br-[4px] bg-brand-primary text-[var(--color-on-brand-primary)]",
              )}
            >
              {m.text}
            </div>
          ))}
        </div>

        <div className="flex flex-wrap gap-1.5 px-4 pb-3">
          {entries.map((entry) => (
            <button
              key={entry.id}
              type="button"
              onClick={() => askChip(entry)}
              className="rounded-full border border-[var(--border-soft)] px-2.5 py-1.5 font-mono text-[0.68rem] text-brand-primary"
            >
              {entry.chipLabel}
            </button>
          ))}
        </div>

        <div className="flex gap-2 border-t border-[var(--border-softer)] p-3">
          <input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") send();
            }}
            type="text"
            placeholder={t("placeholder")}
            className="input flex-1"
          />
          <button
            type="button"
            onClick={send}
            className="rounded-[var(--radius-sm)] bg-brand-primary px-4 text-sm font-semibold text-[var(--color-on-brand-primary)]"
          >
            {t("send")}
          </button>
        </div>
      </div>
    </>
  );
}
