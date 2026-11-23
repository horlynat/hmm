"use client";

import { useEffect, useRef, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { siteConfig } from "@/config/site";
import { whatsappLink } from "@/lib/whatsapp";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { AI_ASSISTANT_OPEN_EVENT, consumeAiAssistantOpenRequest } from "@/lib/aiAssistantOpen";
import type { AiAssistantChatResult } from "@/lib/types";
import { PL } from "./PL";
import { ProtoIcon } from "./ProtoSvg";
import type { ProtoIconName } from "./assets";

/**
 * Boutons flottants et panneau de l'assistant — interface du prototype
 * (fabs() : `.fabs .drawer .dh2 .dbody .dsug .dfoot`), branchée sur
 * l'ASSISTANT RÉEL : POST /api/ai-assistant/chat (proxy Next vers le backend,
 * avec son limiteur et son plafond de coût), comme l'ancien widget.
 *
 * - relances : suggestions renvoyées par l'IA, sinon celles du prototype ;
 * - « Parler à Horlynat » : réponse locale + bouton WhatsApp, sans appel IA ;
 * - erreurs : messages « limite atteinte » / « indisponible » ; panne réseau :
 *   repli configuré au back-office (`fallback`), sinon renvoi vers WhatsApp ;
 * - tout texte (saisi ou reçu) est rendu comme TEXTE React, jamais en HTML ;
 * - ouvrable depuis n'importe quelle page (canal aiAssistantOpen, y compris
 *   un clic survenu avant l'hydratation).
 */
type Msg = { who: "user"; text: string } | { who: "bot"; text: string; whatsapp: boolean };

const MAX_QUESTION = 1000;

export function SiteAssistant({ fallback }: { fallback: string | null }) {
  const t = useTranslations("landing.assistant");
  const ta = useTranslations("aiAssistant");
  const locale = useLocale();
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const [pending, setPending] = useState(false);
  const [follow, setFollow] = useState<string[] | null>(null);
  const logRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const fabRef = useRef<HTMLButtonElement>(null);

  const suggestions = t.raw("suggestions") as [ProtoIconName, string, string][];
  const defaultFollow = t.raw("follow") as string[];

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

  useEffect(() => {
    if (logRef.current) logRef.current.scrollTop = logRef.current.scrollHeight;
  }, [msgs, pending]);

  async function say(raw: string) {
    const question = raw.trim().slice(0, MAX_QUESTION);
    if (!question || pending) return;
    const history = msgs;
    setMsgs((m) => [...m, { who: "user", text: question }]);

    // « Parler à Horlynat » : aucun appel à l'IA.
    if (question === defaultFollow[2]) {
      window.setTimeout(() => setMsgs((m) => [...m, { who: "bot", text: t("talkAnswer"), whatsapp: true }]), reduce ? 0 : 400);
      return;
    }

    setPending(true);
    try {
      const res = await fetch("/api/ai-assistant/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          question,
          history: history.map((m) => ({ role: m.who === "bot" ? "assistant" : "user", text: m.text })),
          locale,
        }),
      });
      const result = (await res.json()) as AiAssistantChatResult;
      if (result.ok) {
        setMsgs((m) => [...m, { who: "bot", text: result.answer, whatsapp: false }]);
        setFollow(result.suggestions.length > 0 ? result.suggestions.slice(0, 3) : null);
      } else {
        const text = result.error === "rate_limited" ? ta("errorRateLimited") : ta("errorUnavailable");
        setMsgs((m) => [...m, { who: "bot", text, whatsapp: true }]);
      }
    } catch {
      setMsgs((m) => [...m, { who: "bot", text: fallback ?? ta("errorUnavailable"), whatsapp: true }]);
    } finally {
      setPending(false);
    }
  }

  const last = msgs[msgs.length - 1];
  const followItems = follow ?? defaultFollow;

  return (
    <div className="fabs" id="fabs">
      {open && (
        <>
          <div className="scrim" onClick={() => setOpen(false)} />
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
                <button
                  type="button"
                  className="ib"
                  aria-label={t("newChat")}
                  title={t("newChat")}
                  onClick={() => {
                    setMsgs([]);
                    setFollow(null);
                  }}
                >
                  <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                </button>
              )}
              <button type="button" className="ib" aria-label={t("close")} onClick={() => setOpen(false)}>
                <ProtoIcon name="x" />
              </button>
            </header>
            <div className="dbody" id="alog" role="log" aria-live="polite" ref={logRef}>
              {msgs.length > 0 ? (
                <>
                  {msgs.map((m, i) =>
                    m.who === "user" ? (
                      <div key={i} className="bub bu">{m.text}</div>
                    ) : (
                      <div key={i} className="arow">
                        <span className="dlogo sm"><ProtoIcon name="spark" /></span>
                        <div className="bub bb">
                          {m.text}
                          {m.whatsapp && (
                            <a className="b b-w sm" style={{ marginTop: 10 }} href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer">
                              <ProtoIcon name="wa" size={17} />
                              {t("writeHorlynat")}
                            </a>
                          )}
                        </div>
                      </div>
                    ),
                  )}
                  {pending && (
                    <div className="arow">
                      <span className="dlogo sm"><ProtoIcon name="spark" /></span>
                      <div className="bub bb typing" aria-label={t("typing")}><i /><i /><i /></div>
                    </div>
                  )}
                  {!pending && last && last.who === "bot" && (
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
                      <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="15" r="7" fill="rgba(255,255,255,.55)" /><path d="M6 40c1-9 7-14 14-14s13 5 14 14z" fill="rgba(255,255,255,.55)" /></svg>
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
                const v = inputRef.current?.value ?? "";
                if (v.trim() && !pending) {
                  void say(v);
                  if (inputRef.current) inputRef.current.value = "";
                }
              }}
            >
              <label className="sr" htmlFor="ai">{t("inputLabel")}</label>
              <div className="din">
                <textarea
                  id="ai"
                  ref={inputRef}
                  rows={1}
                  placeholder={t("placeholder")}
                  maxLength={MAX_QUESTION}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && !e.shiftKey) {
                      e.preventDefault();
                      e.currentTarget.form?.requestSubmit();
                    }
                  }}
                />
                <button className="dsend" aria-label={t("send")} disabled={pending}><ProtoIcon name="send" /></button>
              </div>
              <p>
                {t("disclaimer")}{" "}
                <PL href="/confidentialite" onClick={() => setOpen(false)}>{t("dataLink")}</PL>
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
