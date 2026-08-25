"use client";

import { useState } from "react";
import { createPortal } from "react-dom";
import { useLocale, useTranslations } from "next-intl";
import { Send, Sparkles, X } from "lucide-react";
import type { AiAssistantChatResult } from "@/lib/types";

interface Message {
  who: "bot" | "user";
  text: string;
}

interface AiPageInsightProps {
  title: string;
  /** Texte brut (HTML déjà retiré), déjà borné en longueur côté page — cf. blog/[slug] et realisations/[slug]. */
  content: string;
  contentType: "article" | "project";
}

/**
 * Assistant IA intégré directement sur la page de détail (article, projet) —
 * pas seulement la bulle flottante générale (AiAssistantWidget.tsx, FAQ sur
 * le profil de Horlynat). Appelle un endpoint dédié au résumé
 * (/api/ai-assistant/summarize -> App\ApiResource\AiContentSummaryApiResource)
 * plutôt que /api/ai-assistant/chat : ce dernier a un système prompt qui dit
 * explicitement à Claude de se méfier de tout contenu fourni dans la
 * question et de ne s'appuyer QUE sur un corpus curé — testé en pratique,
 * réutiliser ce chat pour résumer un article produisait des réponses
 * évasives ("je n'ai pas accès au contenu complet"). L'endpoint dédié a un
 * prompt taillé pour la tâche : `content` EST la matière à résumer.
 *
 * Identité visuelle volontairement DIFFÉRENTE d'AiAssistantWidget.tsx (crème
 * + terracotta + serif éditorial, pensé comme un produit conversationnel à
 * part) : ici, dégradé de marque du site (--cta-gradient-from/to, le même
 * que .btn-primary et les CTA de clôture des pages liste) + jetons de marque
 * standard — pour se lire comme une capacité de LA PAGE elle-même, pas
 * comme "vous parlez maintenant à un autre produit".
 *
 * Présentation en fenêtre flottante ancrée à droite, rendue via un portail
 * React (document.body) — pas inline dans le flux de la page : ne déforme
 * jamais la mise en page du hero, et le visiteur peut continuer à lire
 * l'article pendant que le panneau reste ouvert à côté (retour utilisateur
 * explicite). Le portail est nécessaire, pas cosmétique : le bouton
 * déclencheur est enveloppé dans un `.hero-in` dont l'animation pose un
 * `transform` persistant (fill-mode both) sur son conteneur — n'importe quel
 * ancêtre avec un `transform` non-`none` devient un "containing block" CSS
 * pour tout descendant `position: fixed`, qui se positionnerait alors par
 * rapport à CET ancêtre au lieu du vrai viewport (constaté en pratique : le
 * panneau se retrouvait mal placé/coupé). Le portail sort le panneau de cet
 * arbre DOM, hors d'atteinte de ce piège CSS classique, quel que soit
 * l'ancêtre du bouton qui l'a ouvert.
 *
 * Volontairement PAS déclenché automatiquement au chargement : chaque appel
 * accepté par le backend coûte réellement (Claude, cf. App\Service\
 * ClaudeClient) et partage le même budget de rate-limit par IP que la bulle
 * flottante (20/h, cf. route.ts) — seul un clic explicite du visiteur
 * déclenche le premier appel.
 */
export function AiPageInsight({ title, content, contentType }: AiPageInsightProps) {
  const t = useTranslations("aiAssistant");
  const tp = useTranslations("aiAssistant.pageInsight");
  const locale = useLocale();
  const [started, setStarted] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [pending, setPending] = useState(false);
  const [input, setInput] = useState("");

  async function ask(question: string, historyBefore: Message[], displayText?: string) {
    setPending(true);
    if (displayText) {
      setMessages((prev) => [...prev, { who: "user", text: displayText }]);
    }
    try {
      const res = await fetch("/api/ai-assistant/summarize", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          title,
          content,
          contentType,
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
    void ask(value, historyBefore, value);
  }

  if (!started) {
    return (
      <button
        type="button"
        onClick={() => {
          setStarted(true);
          // Pas de displayText : le résumé initial n'a pas de "question" à
          // montrer comme si le visiteur l'avait tapée (cf. docblock ci-dessus).
          void ask("", []);
        }}
        className="btn-primary gap-2 text-sm"
      >
        <Sparkles className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
        {tp("cta")}
      </button>
    );
  }

  const panel = (
    <div
      role="dialog"
      aria-label={tp("title")}
      className="fixed top-24 right-6 z-40 flex max-h-[calc(100vh-8rem)] w-[380px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-[var(--radius-lg)] shadow-2xl"
      style={{ background: "var(--color-bg-card)", border: "1px solid var(--border-softer)" }}
    >
      <div
        className="flex items-center gap-3 px-5 py-4 text-white"
        style={{ background: "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)" }}
      >
        <span
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15"
          aria-hidden="true"
        >
          <Sparkles className="h-4 w-4" strokeWidth={2} />
        </span>
        <div className="min-w-0 flex-1">
          <div className="text-[0.95rem] font-semibold leading-tight" style={{ fontFamily: "var(--font-heading)" }}>
            {tp("title")}
          </div>
          <div className="font-mono text-[0.68rem] uppercase tracking-wide opacity-80">{tp("disclaimer")}</div>
        </div>
        <button
          type="button"
          onClick={() => setStarted(false)}
          aria-label={t("close")}
          className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-colors hover:bg-white/15"
        >
          <X className="h-4 w-4" strokeWidth={2} />
        </button>
      </div>

      <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-5" aria-live="polite">
        {messages.map((m, i) =>
          m.who === "bot" && i === 0 ? (
            // Réponse au résumé initial : texte simple, pas une bulle de
            // chat — comme un vrai paragraphe de résumé, pas un tour de
            // conversation (rien ne l'a "demandé" à l'écran, cf. handleStart).
            <p key={i} className="text-[0.92rem] leading-relaxed text-brand-dark">
              {m.text}
            </p>
          ) : (
            <div
              key={i}
              className={
                m.who === "user"
                  ? "ml-auto max-w-[92%] rounded-2xl rounded-br-[6px] bg-brand-light/40 px-4 py-3 text-[0.9rem] leading-relaxed text-brand-dark"
                  : "max-w-[92%] rounded-2xl rounded-bl-[6px] border border-[var(--border-softer)] bg-bg-card px-4 py-3 text-[0.9rem] leading-relaxed text-brand-dark"
              }
            >
              {m.text}
            </div>
          ),
        )}
        {pending && (
          <div className="flex w-fit items-center gap-2 rounded-2xl rounded-bl-[6px] border border-[var(--border-softer)] bg-bg-card px-4 py-3">
            <span className="flex gap-1">
              <span className="assistant-dot" style={{ background: "var(--color-brand-primary)", animationDelay: "0ms" }} />
              <span className="assistant-dot" style={{ background: "var(--color-brand-primary)", animationDelay: "160ms" }} />
              <span className="assistant-dot" style={{ background: "var(--color-brand-primary)", animationDelay: "320ms" }} />
            </span>
            <span className="font-mono text-[0.7rem] opacity-60">{t("thinking")}</span>
          </div>
        )}
      </div>

      <div className="flex items-center gap-2 p-4" style={{ borderTop: "1px solid var(--border-softer)" }}>
        <div
          className="flex flex-1 items-center gap-2 rounded-full py-2 pl-4 pr-2"
          style={{ background: "var(--color-bg-default)", border: "1px solid var(--border-softer)" }}
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
            className="min-w-0 flex-1 bg-transparent text-[0.88rem] text-brand-dark outline-none placeholder:opacity-50 disabled:cursor-not-allowed"
          />
          <button
            type="button"
            onClick={handleFollowUp}
            disabled={pending || !input.trim()}
            aria-label={t("send")}
            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white transition-opacity disabled:opacity-40"
            style={{ background: "var(--color-brand-primary)" }}
          >
            <Send className="h-3.5 w-3.5" strokeWidth={2.25} />
          </button>
        </div>
      </div>
    </div>
  );

  return typeof document !== "undefined" ? createPortal(panel, document.body) : panel;
}
