"use client";

import { Fragment, useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import { useLocale, useTranslations } from "next-intl";
import { siteConfig } from "@/config/site";
import { whatsappLink } from "@/lib/whatsapp";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { AI_ASSISTANT_OPEN_EVENT, consumeAiAssistantOpenRequest } from "@/lib/aiAssistantOpen";
import type { AiAssistantChatResult } from "@/lib/types";
import { PL } from "./PL";
import { PersonAvatarArt, ProtoIcon } from "./ProtoSvg";
import type { ProtoIconName } from "./assets";
import {
  buildHistory,
  internalLinks,
  parseAnswer,
  truncateBlocks,
  visibleLength,
  type Block,
  type ChatMsg,
  type Inline,
} from "./assistantChat";

/**
 * Boutons flottants et panneau de l'assistant — interface du prototype
 * (fabs() : `.fabs .drawer .dh2 .dbody .dsug .dfoot`), branchée sur
 * l'ASSISTANT RÉEL : POST /api/ai-assistant/chat (proxy Next vers le backend,
 * avec son limiteur et son plafond de coût).
 *
 * Comportement d'une messagerie moderne :
 * - la saisie n'est JAMAIS bloquée : on peut écrire pendant que l'assistant
 *   répond ; le bouton d'envoi devient alors « Arrêter » ;
 * - la réponse s'affiche progressivement, déjà mise en forme (paragraphes,
 *   listes, gras, liens vers les offres en cartes) ;
 * - historique borné et nettoyé (cf. assistantChat.ts) : la conversation ne
 *   tombe plus en « indisponible » au bout de quelques questions ;
 * - conversation conservée pendant la session (rechargement compris) ;
 * - « Nouvelle conversation » annule toute réponse en cours ;
 * - tout texte (saisi ou reçu) est rendu comme TEXTE React, jamais en HTML.
 */
const MAX_QUESTION = 1000;
const STORE_KEY = "hmm-assistant-v2";
const MAX_STORED = 40;
const REVEAL_STEP_MS = 16;
const REVEAL_CHARS = 5;

type Stored = { msgs: ChatMsg[]; follow: string[] | null };

function loadStored(): Stored | null {
  try {
    const raw = window.sessionStorage.getItem(STORE_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw) as Partial<Stored>;
    if (!Array.isArray(data.msgs)) return null;
    const msgs = data.msgs.filter(
      (m): m is ChatMsg =>
        !!m && typeof m === "object" && (m.who === "user" || m.who === "bot") && typeof m.text === "string" && typeof m.inHistory === "boolean",
    );
    const follow = Array.isArray(data.follow) ? data.follow.filter((f): f is string => typeof f === "string").slice(0, 3) : null;
    return { msgs: msgs.slice(-MAX_STORED), follow };
  } catch {
    return null;
  }
}

function saveStored(value: Stored) {
  try {
    window.sessionStorage.setItem(STORE_KEY, JSON.stringify({ msgs: value.msgs.slice(-MAX_STORED), follow: value.follow }));
  } catch {
    /* stockage indisponible : la conversation reste en mémoire */
  }
}

function Rich({ blocks, onNavigate }: { blocks: Block[]; onNavigate: () => void }) {
  const inline = (parts: Inline[]): ReactNode =>
    parts.map((p, i) => {
      if (p.kind === "bold") return <b key={i}>{p.text}</b>;
      if (p.kind === "link") {
        return p.internal ? (
          <PL key={i} href={p.href} onClick={onNavigate} className="alink">
            {p.text}
          </PL>
        ) : (
          <a key={i} href={p.href} target="_blank" rel="noopener noreferrer" className="alink">
            {p.text}
          </a>
        );
      }
      return <Fragment key={i}>{p.text}</Fragment>;
    });
  return (
    <>
      {blocks.map((b, i) =>
        b.kind === "p" ? (
          <p key={i} className="ap">
            {inline(b.parts)}
          </p>
        ) : (
          <ul key={i} className="aul">
            {b.items.map((it, j) => (
              <li key={j}>{inline(it)}</li>
            ))}
          </ul>
        ),
      )}
    </>
  );
}

export function SiteAssistant({ fallback }: { fallback: string | null }) {
  const t = useTranslations("landing.assistant");
  const ta = useTranslations("aiAssistant");
  const locale = useLocale();
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [msgs, setMsgs] = useState<ChatMsg[]>([]);
  const [pending, setPending] = useState(false);
  const [follow, setFollow] = useState<string[] | null>(null);
  // Caractères déjà affichés de la DERNIÈRE réponse (null : tout est affiché).
  const [reveal, setReveal] = useState<number | null>(null);
  const [draft, setDraft] = useState("");
  const logRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const fabRef = useRef<HTMLButtonElement>(null);
  const abortRef = useRef<AbortController | null>(null);
  const convRef = useRef(0);
  const restored = useRef(false);

  const suggestions = t.raw("suggestions") as [ProtoIconName, string, string][];
  const defaultFollow = t.raw("follow") as string[];

  // Conversation de la session (après hydratation : pas d'écart serveur/client).
  // `setState` différé au frame suivant plutôt qu'appelé à même le corps de
  // l'effet (cascading render) : cf. règle react-hooks/set-state-in-effect,
  // même convention que Reveal.tsx.
  useEffect(() => {
    const stored = loadStored();
    if (!stored) {
      restored.current = true;
      return;
    }
    const raf = requestAnimationFrame(() => {
      setMsgs(stored.msgs);
      setFollow(stored.follow);
      restored.current = true;
    });
    return () => cancelAnimationFrame(raf);
  }, []);

  useEffect(() => {
    if (restored.current) saveStored({ msgs, follow });
  }, [msgs, follow]);

  // Ouverture demandée ailleurs sur le site (ex. démo WhatsApp de l'accueil).
  useEffect(() => {
    function onOpenRequest() {
      if (consumeAiAssistantOpenRequest()) setOpen(true);
    }
    window.addEventListener(AI_ASSISTANT_OPEN_EVENT, onOpenRequest);
    onOpenRequest();
    return () => window.removeEventListener(AI_ASSISTANT_OPEN_EVENT, onOpenRequest);
  }, []);

  useEffect(() => {
    if (!open) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    inputRef.current?.focus({ preventScroll: true });
    const fab = fabRef.current;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = prev;
      document.removeEventListener("keydown", onKey);
      fab?.focus({ preventScroll: true });
    };
  }, [open]);

  useEffect(() => () => abortRef.current?.abort(), []);

  useEffect(() => {
    if (logRef.current) logRef.current.scrollTop = logRef.current.scrollHeight;
  }, [msgs, pending, reveal]);

  // Champ qui grandit avec le texte (jusqu'à la hauteur max du prototype).
  useEffect(() => {
    const el = inputRef.current;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 120)}px`;
  }, [draft, open]);

  // Affichage progressif de la dernière réponse.
  const lastMsg = msgs[msgs.length - 1];
  const revealTotal = reveal !== null && lastMsg?.who === "bot" ? visibleLength(parseAnswer(lastMsg.text)) : 0;
  useEffect(() => {
    if (reveal === null) return;
    if (reveal >= revealTotal) {
      // `setState` différé au frame suivant (cf. commentaire ci-dessus).
      const raf = requestAnimationFrame(() => setReveal(null));
      return () => cancelAnimationFrame(raf);
    }
    const id = window.setTimeout(() => setReveal((r) => (r === null ? r : r + REVEAL_CHARS)), REVEAL_STEP_MS);
    return () => window.clearTimeout(id);
  }, [reveal, revealTotal]);

  const addBot = useCallback(
    (text: string, opts: { inHistory: boolean; whatsapp: boolean }) => {
      setMsgs((m) => [...m, { who: "bot" as const, text, ...opts }]);
      setReveal(reduce ? null : 0);
    },
    [reduce],
  );

  const stop = useCallback(() => {
    abortRef.current?.abort();
    abortRef.current = null;
    setPending(false);
  }, []);

  async function say(raw: string) {
    const question = raw.trim().slice(0, MAX_QUESTION);
    if (!question || pending) return;
    setReveal(null);
    const history = buildHistory(msgs);
    setMsgs((m) => [...m, { who: "user", text: question, inHistory: true }]);

    // « Parler à Horlynat » : aucun appel à l'IA.
    const conv = convRef.current;
    if (question === defaultFollow[2]) {
      window.setTimeout(() => {
        if (conv === convRef.current) addBot(t("talkAnswer"), { inHistory: false, whatsapp: true });
      }, reduce ? 0 : 400);
      return;
    }

    const controller = new AbortController();
    abortRef.current = controller;
    setPending(true);
    try {
      const res = await fetch("/api/ai-assistant/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ question, history, locale }),
        signal: controller.signal,
      });
      const result = (await res.json()) as AiAssistantChatResult;
      if (conv !== convRef.current) return;
      if (result.ok) {
        addBot(result.answer, { inHistory: !result.degraded, whatsapp: result.degraded === true });
        if (result.suggestions.length > 0) setFollow(result.suggestions.slice(0, 3));
      } else {
        const text = result.error === "rate_limited" ? ta("errorRateLimited") : ta("errorUnavailable");
        addBot(text, { inHistory: false, whatsapp: true });
      }
    } catch {
      if (controller.signal.aborted || conv !== convRef.current) return;
      addBot(fallback ?? ta("errorUnavailable"), { inHistory: false, whatsapp: true });
    } finally {
      if (abortRef.current === controller) abortRef.current = null;
      if (conv === convRef.current) setPending(false);
    }
  }

  function submit() {
    const value = draft.trim();
    if (!value || pending) return;
    setDraft("");
    void say(value);
  }

  function newChat() {
    convRef.current += 1;
    stop();
    setReveal(null);
    setMsgs([]);
    setFollow(null);
    inputRef.current?.focus({ preventScroll: true });
  }

  const last = msgs[msgs.length - 1];
  const followItems = follow ?? defaultFollow;
  const close = () => setOpen(false);

  return (
    <div className="fabs" id="fabs">
      {open && (
        <>
          <div className="scrim" onClick={close} />
          <aside className="drawer" role="dialog" aria-modal="true" aria-label={t("name")}>
            <header className="dh2">
              <span className="dav">
                <span className="dlogo"><ProtoIcon name="spark" /></span>
                <i />
              </span>
              <span className="dht">
                <b>{t("name")}</b>
                <small>{t("status")}</small>
              </span>
              {msgs.length > 0 && (
                <button type="button" className="ib" aria-label={t("newChat")} title={t("newChat")} onClick={newChat}>
                  <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                </button>
              )}
              <button type="button" className="ib" aria-label={t("close")} onClick={close}>
                <ProtoIcon name="x" />
              </button>
            </header>
            <div className="dbody" id="alog" role="log" aria-live="polite" aria-busy={pending || reveal !== null} ref={logRef}>
              {msgs.length > 0 ? (
                <>
                  {msgs.map((m, i) => {
                    if (m.who === "user") {
                      return (
                        <div key={i} className="bub bu">
                          {m.text}
                        </div>
                      );
                    }
                    const blocks = parseAnswer(m.text);
                    const revealing = reveal !== null && i === msgs.length - 1;
                    const shown = revealing ? truncateBlocks(blocks, reveal) : blocks;
                    const cards = revealing ? [] : internalLinks(blocks);
                    return (
                      <div key={i} className="arow">
                        <span className="dlogo sm"><ProtoIcon name="spark" /></span>
                        <div className="bub bb">
                          <Rich blocks={shown} onNavigate={close} />
                          {cards.map((c) => (
                            <PL key={c.href} href={c.href} className="acard2" onClick={close}>
                              <span>{c.text}</span>
                              <ProtoIcon name="ar" />
                            </PL>
                          ))}
                          {m.whatsapp && !revealing && (
                            <a className="b b-w sm" style={{ marginTop: 10 }} href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer">
                              <ProtoIcon name="wa" size={17} />
                              {t("writeHorlynat")}
                            </a>
                          )}
                        </div>
                      </div>
                    );
                  })}
                  {pending && (
                    <div className="arow">
                      <span className="dlogo sm"><ProtoIcon name="spark" /></span>
                      <div className="bub bb typing" aria-label={t("typing")}><i /><i /><i /></div>
                    </div>
                  )}
                  {!pending && reveal === null && last && last.who === "bot" && (
                    <div className="fol">
                      {followItems.map((f) => (
                        <button key={f} type="button" onClick={() => say(f)}>{f}</button>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <>
                  <div className="dwel">
                    <span className="dlogo lg"><ProtoIcon name="spark" /></span>
                    <h2>{t("welcomeTitle")}</h2>
                    <p>{t("welcomeText")}</p>
                  </div>
                  <div className="dsug">
                    {suggestions.map(([icon, title, sub]) => (
                      <button key={title} type="button" onClick={() => say(title)}>
                        <span className="sic2"><ProtoIcon name={icon} /></span>
                        <b>{title}</b>
                        <span>{sub}</span>
                      </button>
                    ))}
                  </div>
                  <a className="dwa" href={whatsappLink(t("humanMessage"))} target="_blank" rel="noopener noreferrer">
                    <span className="avs">
                      <PersonAvatarArt />
                    </span>
                    <span>
                      <b>{t("humanTitle")}</b>
                      <small>{t("humanSub")}</small>
                    </span>
                    <ProtoIcon name="ar" />
                  </a>
                </>
              )}
            </div>
            <form
              className="dfoot"
              onSubmit={(e) => {
                e.preventDefault();
                if (pending) stop();
                else submit();
              }}
            >
              <label className="sr" htmlFor="ai">{t("inputLabel")}</label>
              <div className="din">
                <textarea
                  id="ai"
                  ref={inputRef}
                  rows={1}
                  value={draft}
                  onChange={(e) => setDraft(e.target.value)}
                  placeholder={t("placeholder")}
                  maxLength={MAX_QUESTION}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
                      e.preventDefault();
                      submit();
                    }
                  }}
                />
                {pending ? (
                  <button className="dsend" aria-label={t("stop")} title={t("stop")}>
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"><rect x="3" y="3" width="10" height="10" rx="2" fill="currentColor" /></svg>
                  </button>
                ) : (
                  <button className="dsend" aria-label={t("send")} disabled={!draft.trim()}>
                    <ProtoIcon name="send" />
                  </button>
                )}
              </div>
              <p>
                {t("disclaimer")}{" "}
                <PL href="/confidentialite" onClick={close}>{t("dataLink")}</PL>
              </p>
            </form>
          </aside>
        </>
      )}
      <a className="fab" href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer" aria-label={t("whatsapp")} style={{ background: "#25D366", color: "#052611" }}>
        <ProtoIcon name="wa" size={26} />
      </a>
      <button
        ref={fabRef}
        type="button"
        className="fab"
        aria-label={t("open")}
        aria-expanded={open}
        style={{ background: "var(--or)", color: "#2A1206" }}
        onClick={() => setOpen((v) => !v)}
      >
        <ProtoIcon name="spark" />
      </button>
    </div>
  );
}
