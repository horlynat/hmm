"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import clsx from "clsx";

/**
 * Widget purement local : réponses par mots-clés, sans appel à un backend IA
 * (aucun endpoint de ce type n'existe côté API — cf. plan). Portage direct du
 * comportement du prototype, gardé identique pour les deux locales.
 */
type AnswerKey =
  | "answerPresentation"
  | "answerCompetences"
  | "answerRealisations"
  | "answerPhilosophie"
  | "answerPourquoi"
  | "answerProjet";

const KEYWORDS: Record<"fr" | "en", Record<AnswerKey, string[]>> = {
  fr: {
    answerPresentation: ["qui", "présent", "present"],
    answerCompetences: ["compét", "compet", "stack"],
    answerRealisations: ["réalis", "realis", "projet"],
    answerPhilosophie: ["vision", "philosoph", "futur", "demain"],
    answerPourquoi: ["pourquoi"],
    answerProjet: ["confier", "devis", "budget"],
  },
  en: {
    answerPresentation: ["who", "introduc"],
    answerCompetences: ["skill", "stack", "tech"],
    answerRealisations: ["project", "work", "portfolio"],
    answerPhilosophie: ["vision", "philosoph", "future", "tomorrow"],
    answerPourquoi: ["why"],
    answerProjet: ["start", "quote", "budget"],
  },
};

const CHIPS: { chip: string; answer: AnswerKey }[] = [
  { chip: "chipPresentation", answer: "answerPresentation" },
  { chip: "chipCompetences", answer: "answerCompetences" },
  { chip: "chipRealisations", answer: "answerRealisations" },
  { chip: "chipPhilosophie", answer: "answerPhilosophie" },
  { chip: "chipPourquoi", answer: "answerPourquoi" },
  { chip: "chipProjet", answer: "answerProjet" },
];

interface Message {
  who: "bot" | "user";
  text: string;
}

export function AiAssistantWidget() {
  const t = useTranslations("aiAssistant");
  const tc = useTranslations("common");
  const locale = useLocale() as "fr" | "en";
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    { who: "bot", text: t("greeting") },
  ]);
  const [input, setInput] = useState("");

  function addMessage(text: string, who: Message["who"]) {
    setMessages((prev) => [...prev, { who, text }]);
  }

  function respond(userText: string) {
    const lower = userText.toLowerCase();
    const keywordSet = KEYWORDS[locale] ?? KEYWORDS.fr;
    const matched = (Object.keys(keywordSet) as AnswerKey[]).find((key) =>
      keywordSet[key].some((kw) => lower.includes(kw)),
    );
    const answer = matched ? t(matched) : t("fallback");
    window.setTimeout(() => addMessage(answer, "bot"), 350);
  }

  function send() {
    const value = input.trim();
    if (!value) return;
    addMessage(value, "user");
    setInput("");
    respond(value);
  }

  function askChip(chip: { chip: string; answer: AnswerKey }) {
    setOpen(true);
    addMessage(t(chip.chip), "user");
    window.setTimeout(() => addMessage(t(chip.answer), "bot"), 350);
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
            "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 70%)",
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
              "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 70%)",
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
                  ? "self-start rounded-bl-[4px] bg-brand-light text-brand-dark"
                  : "ml-auto rounded-br-[4px] bg-brand-primary text-white",
              )}
            >
              {m.text}
            </div>
          ))}
        </div>

        <div className="flex flex-wrap gap-1.5 px-4 pb-3">
          {CHIPS.map((c) => (
            <button
              key={c.chip}
              type="button"
              onClick={() => askChip(c)}
              className="rounded-full border border-[var(--border-soft)] px-2.5 py-1.5 font-mono text-[0.68rem] text-brand-primary"
            >
              {t(c.chip)}
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
            className="rounded-[var(--radius-sm)] bg-brand-primary px-4 text-sm font-semibold text-white"
          >
            {t("send")}
          </button>
        </div>
      </div>
    </>
  );
}
