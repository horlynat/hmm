"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Send, Sparkles } from "lucide-react";
import type { AiAssistantChatResult } from "@/lib/types";

interface Message {
  who: "bot" | "user";
  text: string;
}

interface AiPageInsightProps {
  /** Question envoyée à l'assistant au premier clic — déjà interpolée avec le titre de l'article/projet (cf. blog/[slug] et realisations/[slug]). */
  seedQuestion: string;
}

/**
 * Assistant IA intégré directement sur la page de détail (article, projet) —
 * pas seulement la bulle flottante, cf. AiAssistantWidget.tsx dont ce
 * composant reprend volontairement l'identité visuelle (jetons --assistant-*,
 * même structure de bulles) pour rester perçu comme le même assistant, pas
 * une fonctionnalité séparée.
 *
 * Volontairement PAS déclenché automatiquement au chargement de la page :
 * chaque appel accepté par le backend coûte réellement (Claude, cf.
 * App\Service\ClaudeClient côté backend) et partage le même budget de
 * rate-limit par IP que la bulle flottante (20/h, cf. route.ts) — déclencher
 * un appel pour chaque simple visite de page l'épuiserait pour des visiteurs
 * qui ne lisent même pas la réponse. Le premier appel n'est donc envoyé
 * qu'au clic explicite du visiteur sur le bouton.
 */
export function AiPageInsight({ seedQuestion }: AiPageInsightProps) {
  const t = useTranslations("aiAssistant");
  const tp = useTranslations("aiAssistant.pageInsight");
  const locale = useLocale();
  const [started, setStarted] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [pending, setPending] = useState(false);
  const [input, setInput] = useState("");

  async function ask(question: string, historyBefore: Message[]) {
    setPending(true);
    setMessages((prev) => [...prev, { who: "user", text: question }]);
    try {
      const res = await fetch("/api/ai-assistant/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          question,
          history: historyBefore.map((m) => ({
            role: m.who === "bot" ? "assistant" : "user",
            text: m.text,
          })),
          locale,
        }),
      });
      const result = (await res.json()) as AiAssistantChatResult;
      if (result.ok) {
        setMessages((prev) => [...prev, { who: "bot", text: result.answer }]);
        return;
      }
      setMessages((prev) => [
        ...prev,
        { who: "bot", text: result.error === "rate_limited" ? t("errorRateLimited") : t("errorUnavailable") },
      ]);
    } catch {
      setMessages((prev) => [...prev, { who: "bot", text: t("errorUnavailable") }]);
    } finally {
      setPending(false);
    }
  }

  function handleFollowUp() {
    const value = input.trim();
    if (!value || pending) return;
    const historyBefore = messages;
    setInput("");
    void ask(value, historyBefore);
  }

  if (!started) {
    return (
      <button
        type="button"
        onClick={() => {
          setStarted(true);
          void ask(seedQuestion, []);
        }}
        className="inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition-all hover:-translate-y-0.5"
        style={{
          background: "var(--assistant-accent-soft)",
          color: "var(--assistant-accent-soft-text)",
          border: "1px solid var(--assistant-border)",
        }}
      >
        <Sparkles className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
        {tp("cta")}
      </button>
    );
  }

  return (
    <div
      className="overflow-hidden rounded-[var(--radius-lg)]"
      style={{ background: "var(--assistant-bg)", border: "1px solid var(--assistant-border)" }}
    >
      <div
        className="flex items-center gap-3 px-5 py-4"
        style={{ background: "var(--assistant-surface)", borderBottom: "1px solid var(--assistant-border)" }}
      >
        <span
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white"
          style={{ background: "var(--assistant-accent)" }}
          aria-hidden="true"
        >
          <Sparkles className="h-4 w-4" strokeWidth={2} />
        </span>
        <div>
          <div
            className="text-[0.95rem] font-semibold leading-tight"
            style={{ fontFamily: "var(--font-assistant-serif)", color: "var(--assistant-text)" }}
          >
            {tp("title")}
          </div>
          <div className="font-mono text-[0.68rem] uppercase tracking-wide" style={{ color: "var(--assistant-text-dim)" }}>
            {tp("disclaimer")}
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-3 p-5" aria-live="polite">
        {messages.map((m, i) => {
          // Le premier message est la question interne envoyée en coulisses
          // (cf. seedQuestion) — jamais celle du visiteur. L'afficher comme
          // si c'était un message qu'il avait tapé ressemble à un prompt
          // exposé plutôt qu'à un vrai résumé, ça sème le doute plus que ça
          // n'inspire confiance. On ne rend donc que sa réponse (message 1),
          // en texte simple plutôt qu'en bulle de chat — comme un vrai
          // paragraphe de résumé, pas un tour de conversation. Les échanges
          // suivants (vraies questions tapées par le visiteur) restent en
          // bulles normales.
          if (i === 0) return null;
          if (i === 1 && m.who === "bot") {
            return (
              <p key={i} className="text-[0.92rem] leading-relaxed" style={{ color: "var(--assistant-text)" }}>
                {m.text}
              </p>
            );
          }
          return (
            <div
              key={i}
              className="max-w-[92%] px-4 py-3 text-[0.9rem] leading-relaxed"
              style={
                m.who === "user"
                  ? {
                      marginLeft: "auto",
                      background: "var(--assistant-accent-soft)",
                      color: "var(--assistant-accent-soft-text)",
                      borderRadius: "1rem 1rem 6px 1rem",
                    }
                  : {
                      background: "var(--assistant-surface)",
                      color: "var(--assistant-text)",
                      border: "1px solid var(--assistant-border)",
                      borderRadius: "1rem 1rem 1rem 6px",
                    }
              }
            >
              {m.text}
            </div>
          );
        })}
        {pending && (
          <div
            className="flex w-fit items-center gap-2 rounded-2xl rounded-bl-[6px] px-4 py-3"
            style={{ background: "var(--assistant-surface)", border: "1px solid var(--assistant-border)" }}
          >
            <span className="flex gap-1">
              <span className="assistant-dot" style={{ background: "var(--assistant-accent)", animationDelay: "0ms" }} />
              <span className="assistant-dot" style={{ background: "var(--assistant-accent)", animationDelay: "160ms" }} />
              <span className="assistant-dot" style={{ background: "var(--assistant-accent)", animationDelay: "320ms" }} />
            </span>
            <span className="font-mono text-[0.7rem]" style={{ color: "var(--assistant-text-dim)" }}>
              {t("thinking")}
            </span>
          </div>
        )}
      </div>

      <div className="flex items-center gap-2 p-4" style={{ borderTop: "1px solid var(--assistant-border)" }}>
        <div
          className="flex flex-1 items-center gap-2 rounded-full py-2 pl-4 pr-2"
          style={{ background: "var(--assistant-surface)", border: "1px solid var(--assistant-border)" }}
        >
          <input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") handleFollowUp();
            }}
            type="text"
            placeholder={tp("followUpPlaceholder")}
            disabled={pending}
            className="min-w-0 flex-1 bg-transparent text-[0.88rem] outline-none placeholder:opacity-50 disabled:cursor-not-allowed"
            style={{ color: "var(--assistant-text)" }}
          />
          <button
            type="button"
            onClick={handleFollowUp}
            disabled={pending || !input.trim()}
            aria-label={t("send")}
            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-opacity disabled:opacity-40"
            style={{ background: "var(--assistant-accent)" }}
          >
            <Send className="h-3.5 w-3.5 text-white" strokeWidth={2.25} />
          </button>
        </div>
      </div>
    </div>
  );
}
