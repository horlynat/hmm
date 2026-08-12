"use client";

import { useState } from "react";
import { useTranslations, useLocale } from "next-intl";
import clsx from "clsx";
import { Send, Sparkles, X } from "lucide-react";
import type { AiAssistantChatResult, AiAssistantEntry, AiAssistantSettings } from "@/lib/types";

interface Message {
  who: "bot" | "user";
  text: string;
}

interface AiAssistantWidgetProps {
  settings: AiAssistantSettings;
  entries: AiAssistantEntry[];
}

/**
 * Identité visuelle volontairement distincte du reste de la vitrine (bleu de
 * marque) : fond crème, accent terracotta, serif éditorial (--font-assistant-serif)
 * — dans l'esprit du produit qui répond réellement derrière ce panneau
 * (Claude, cf. App\Service\ClaudeClient), plutôt que la palette "site SaaS"
 * appliquée partout ailleurs. Jetons dédiés dans globals.css (--assistant-*),
 * jamais les tokens de marque globaux.
 *
 * Puces : rien n'est affiché à l'ouverture (seul l'accueil compte pour la
 * première impression) — les chips de départ (`entries`, réponses locales
 * instantanées, coût zéro) n'apparaissent que pendant que le visiteur tape,
 * filtrées sur ses mots-clés (`entry.keywords`), comme un fil d'Ariane vers
 * la FAQ plutôt qu'un mur de boutons imposé d'entrée. Dès qu'un échange réel
 * a eu lieu (texte libre envoyé ou clic sur une suggestion), la barre bascule
 * sur les questions de suivi contextuelles renvoyées par Claude
 * (`result.suggestions`, cf. AiAssistantChatProcessor::buildSystemPrompt côté
 * backend) — affichées en continu, pas seulement pendant la frappe : ce sont
 * des relances de conversation, pas une aide à la saisie. Sur repli/erreur,
 * le backend ne renvoie aucune suggestion et on garde la dernière barre
 * affichée plutôt que de la vider.
 *
 * Le texte libre tapé par le visiteur part vers /api/ai-assistant/chat —
 * proxy Next.js vers POST /api/assistant/chat (RAG Gemini + raisonnement
 * Claude). En cas d'erreur (429/503/réseau), repli sur `settings.fallback`
 * plutôt qu'un message d'erreur générique — l'expérience visiteur reste
 * continue. `settings`/`entries` sont résolus côté serveur (via
 * src/lib/api/ai-assistant.ts) et passés en props depuis le layout.
 */
export function AiAssistantWidget({ settings, entries }: AiAssistantWidgetProps) {
  const t = useTranslations("aiAssistant");
  const locale = useLocale();
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    { who: "bot", text: settings.greeting },
  ]);
  const [input, setInput] = useState("");
  const [pending, setPending] = useState(false);
  const [suggestions, setSuggestions] = useState<string[] | null>(null);

  function addMessage(text: string, who: Message["who"]) {
    setMessages((prev) => [...prev, { who, text }]);
  }

  async function respond(userText: string, historyBefore: Message[]) {
    setPending(true);
    try {
      const res = await fetch("/api/ai-assistant/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          question: userText,
          history: historyBefore.map((m) => ({
            role: m.who === "bot" ? "assistant" : "user",
            text: m.text,
          })),
          locale,
        }),
      });
      const result = (await res.json()) as AiAssistantChatResult;

      if (result.ok) {
        addMessage(result.answer, "bot");
        if (result.suggestions.length > 0) {
          setSuggestions(result.suggestions);
        }
        return;
      }

      addMessage(
        result.error === "rate_limited" ? t("errorRateLimited") : t("errorUnavailable"),
        "bot",
      );
    } catch {
      addMessage(settings.fallback, "bot");
    } finally {
      setPending(false);
    }
  }

  function send() {
    const value = input.trim();
    if (!value || pending) return;
    const historyBefore = messages;
    addMessage(value, "user");
    setInput("");
    void respond(value, historyBefore);
  }

  function askChip(entry: AiAssistantEntry) {
    setOpen(true);
    setInput("");
    addMessage(entry.chipLabel, "user");
    window.setTimeout(() => addMessage(entry.answer, "bot"), 350);
  }

  function askSuggestion(text: string) {
    if (pending) return;
    const historyBefore = messages;
    addMessage(text, "user");
    void respond(text, historyBefore);
  }

  const typed = input.trim().toLowerCase();
  const matchingEntries =
    suggestions === null && typed
      ? entries.filter((entry) =>
          entry.keywords.some(
            (kw) => typed.includes(kw.toLowerCase()) || kw.toLowerCase().includes(typed),
          ),
        )
      : [];
  const chipsToShow = suggestions ?? matchingEntries;

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-label={open ? t("close") : t("title")}
        aria-expanded={open}
        className="assistant-toggle fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full transition-all hover:-translate-y-0.5"
        style={{ background: "var(--assistant-accent)" }}
      >
        {!open && <span className="assistant-ping absolute inset-0 rounded-full" />}
        {open ? (
          <X className="h-5 w-5 text-white" strokeWidth={2} />
        ) : (
          <Sparkles className="h-5 w-5 text-white" strokeWidth={2} />
        )}
      </button>

      <div
        role="dialog"
        aria-label={t("title")}
        className={clsx(
          "fixed bottom-24 right-6 z-50 flex h-[540px] max-h-[calc(100vh-8rem)] w-[390px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-[var(--radius-lg)]",
          open ? "flex" : "hidden",
        )}
        style={{
          background: "var(--assistant-bg)",
          border: "1px solid var(--assistant-border)",
          boxShadow: "0 20px 40px -14px var(--assistant-shadow)",
        }}
      >
        <div
          className="flex items-center justify-between gap-2 px-4 py-3.5"
          style={{ background: "var(--assistant-surface)", borderBottom: "1px solid var(--assistant-border)" }}
        >
          <div className="flex items-center gap-2.5">
            <span
              className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[0.68rem] font-bold text-white"
              style={{ background: "var(--assistant-accent)", fontFamily: "var(--font-assistant-serif)" }}
              aria-hidden="true"
            >
              HM
            </span>
            <div>
              <div
                className="text-[1.05rem] font-semibold leading-tight"
                style={{ fontFamily: "var(--font-assistant-serif)", color: "var(--assistant-text)" }}
              >
                {t("title")}
              </div>
              <div
                className="flex items-center gap-1.5 font-mono text-[0.65rem] uppercase tracking-wide"
                style={{ color: "var(--assistant-text-dim)" }}
              >
                <span className="relative flex h-1.5 w-1.5">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75" />
                  <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500" />
                </span>
                {t("sub")}
              </div>
            </div>
          </div>
          <button
            type="button"
            onClick={() => setOpen(false)}
            aria-label={t("close")}
            className="flex h-7 w-7 items-center justify-center rounded-full transition-colors hover:bg-black/5 dark:hover:bg-white/10"
            style={{ color: "var(--assistant-text-dim)" }}
          >
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4" aria-live="polite">
          {messages.map((m, i) => (
            <div
              key={i}
              className={clsx(
                "max-w-[85%] px-3.5 py-2.5 text-[0.87rem] leading-relaxed",
                m.who === "user" ? "ml-auto rounded-2xl rounded-br-[6px]" : "rounded-2xl rounded-bl-[6px]",
              )}
              style={
                m.who === "user"
                  ? { background: "var(--assistant-accent-soft)", color: "var(--assistant-accent-soft-text)" }
                  : { background: "var(--assistant-surface)", color: "var(--assistant-text)", border: "1px solid var(--assistant-border)" }
              }
            >
              {m.text}
            </div>
          ))}
          {pending && (
            <div
              className="flex items-center gap-2 self-start rounded-2xl rounded-bl-[6px] px-3.5 py-2.5"
              style={{ background: "var(--assistant-surface)", border: "1px solid var(--assistant-border)" }}
            >
              <span className="flex gap-1">
                <span className="assistant-dot" style={{ background: "var(--assistant-accent)", animationDelay: "0ms" }} />
                <span className="assistant-dot" style={{ background: "var(--assistant-accent)", animationDelay: "160ms" }} />
                <span className="assistant-dot" style={{ background: "var(--assistant-accent)", animationDelay: "320ms" }} />
              </span>
              <span className="font-mono text-[0.68rem]" style={{ color: "var(--assistant-text-dim)" }}>
                {t("thinking")}
              </span>
            </div>
          )}
        </div>

        {chipsToShow.length > 0 && (
          <div className="flex flex-wrap gap-1.5 px-4 pb-3">
            {suggestions === null
              ? matchingEntries.map((entry) => (
                  <button
                    key={entry.id}
                    type="button"
                    onClick={() => askChip(entry)}
                    className="rounded-full px-2.5 py-1.5 font-mono text-[0.66rem] transition-colors"
                    style={{ border: "1px solid var(--assistant-border)", color: "var(--assistant-accent)" }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--assistant-accent-soft)")}
                    onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}
                  >
                    {entry.chipLabel}
                  </button>
                ))
              : suggestions.map((suggestion, i) => (
                  <button
                    key={i}
                    type="button"
                    disabled={pending}
                    onClick={() => askSuggestion(suggestion)}
                    className="rounded-full px-2.5 py-1.5 font-mono text-[0.66rem] transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    style={{ border: "1px solid var(--assistant-border)", color: "var(--assistant-accent)" }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = "var(--assistant-accent-soft)")}
                    onMouseLeave={(e) => (e.currentTarget.style.background = "transparent")}
                  >
                    {suggestion}
                  </button>
                ))}
          </div>
        )}

        <div className="p-3" style={{ borderTop: "1px solid var(--assistant-border)" }}>
          <div
            className="flex items-center gap-1.5 rounded-full py-1.5 pl-4 pr-1.5"
            style={{ background: "var(--assistant-surface)", border: "1px solid var(--assistant-border)" }}
          >
            <input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") send();
              }}
              type="text"
              placeholder={t("placeholder")}
              disabled={pending}
              className="min-w-0 flex-1 bg-transparent text-[0.87rem] outline-none placeholder:opacity-50 disabled:cursor-not-allowed"
              style={{ color: "var(--assistant-text)" }}
            />
            <button
              type="button"
              onClick={send}
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
    </>
  );
}
