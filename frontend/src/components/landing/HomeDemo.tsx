"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import clsx from "clsx";
import { useLocale, useTranslations } from "next-intl";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { whatsappLink } from "@/lib/whatsapp";
import { clockTime } from "@/lib/clock";
import type { AiDemoResult } from "@/lib/types";

/**
 * Démo « Votre assistant WhatsApp, en action » de l'accueil, branchée sur la
 * VRAIE IA : POST /api/ai-assistant/demo (proxy Next → App\ApiResource\
 * AiDemoChatApiResource, persona « Boutique Ndinga », limiteur et plafond
 * budgétaire dédiés).
 *
 * Comme dans la vraie vie : la boutique accueille d'abord le client, rien
 * n'est joué à sa place. Les réponses rapides ne sont jamais figées : elles
 * viennent de l'IA, tirées de ce que le visiteur vient d'écrire. Habillage
 * d'un vrai écran WhatsApp (mode sombre) : barre d'état, en-tête « en ligne /
 * écrit… », bulles à queue, heure et coches de lecture.
 *
 * L'ancien jeu de réponses scriptées (mots-clés) reste le FILET DE SECOURS :
 * toute panne, tout refus du garde-fou (`degraded`) ou limite de débit
 * retombe sur lui plutôt que d'afficher une erreur — sans réponses rapides.
 * Mots-clés : sources de RegExp écrites dans les traductions (jamais une
 * saisie du visiteur). Toute réponse est rendue comme texte React, jamais en HTML.
 */
type Bubble = { who: "u" | "b"; text: string; time?: string; typing?: number };
type Alert = { kind: string; title: string; meta: string; fresh?: boolean };

const DELAY_MS = 900;
const MAX_LEN = 300;
const MAX_HISTORY = 6;
/** Après deux échanges, la démo propose d'avoir le même assistant. */
const CTA_AFTER = 2;
const norm = (s: string) => s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => Array.from(w)[0])
    .join("")
    .slice(0, 2)
    .toLocaleUpperCase();
}

const svg = { "aria-hidden": true, fill: "none", stroke: "currentColor", strokeWidth: 2, strokeLinecap: "round", strokeLinejoin: "round" } as const;

function ReadTicks() {
  return (
    <svg aria-hidden="true" viewBox="0 0 16 11" width="16" height="11">
      <path fill="currentColor" d="M11.07.65 4.6 7.12 1.93 4.45.87 5.51l3.73 3.73 7.53-7.53zm4.06 0L8.66 7.12l-.53-.53-1.06 1.06 1.59 1.59 7.53-7.53z" />
    </svg>
  );
}

export function HomeDemo() {
  const t = useTranslations("landing.demo");
  const locale = useLocale();
  const reduce = useReducedMotion();
  const match = t.raw("match") as Record<string, string>;
  const initialAlerts = (t.raw("alerts") as [string, string, string][]).map(([kind, title, meta]) => ({ kind, title, meta }));

  const [log, setLog] = useState<Bubble[]>([{ who: "b", text: t("welcome") }]);
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [kpi, setKpi] = useState<[number, number, number]>([47, 9, 2]);
  const [pop, setPop] = useState<[number, number, number]>([0, 0, 0]);
  const [alerts, setAlerts] = useState<Alert[]>(initialAlerts);
  const logRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const timers = useRef<number[]>([]);
  const seq = useRef(0);
  const alive = useRef(true);
  const logSnapshot = useRef(log);
  // Un second envoi pendant qu'une réponse est en cours calculerait un
  // historique qui finit par un tour "user" (la bulle de frappe en cours en
  // est exclue, cf. filtre ci-dessous) — rejeté par le backend (422,
  // App\Validator\ConversationHistory). Un seul envoi à la fois.
  const sending = useRef(false);

  useEffect(() => {
    logSnapshot.current = log;
  }, [log]);

  // Heure de l'accueil : celle de l'appareil, connue seulement côté client
  // (le rendu serveur n'en a pas). Différée au frame suivant, comme Reveal.tsx.
  useEffect(() => {
    const id = requestAnimationFrame(() => setLog((l) => l.map((b, i) => (i === 0 && !b.time ? { ...b, time: clockTime(locale) } : b))));
    return () => cancelAnimationFrame(id);
  }, [locale]);

  useEffect(() => {
    const pending = timers.current;
    return () => {
      alive.current = false;
      pending.forEach((id) => window.clearTimeout(id));
    };
  }, []);

  useEffect(() => {
    if (logRef.current) logRef.current.scrollTop = logRef.current.scrollHeight;
  }, [log]);

  const test = (key: string, n: string) => new RegExp(match[key]).test(n);

  function answer(q: string): string {
    const n = norm(q);
    if (test("product", n)) return t("answers.product");
    if (test("delivery", n)) return t("answers.delivery");
    if (test("hours", n)) return t("answers.hours");
    if (test("order", n)) return t("answers.order");
    if (test("bulk", n)) return t("answers.bulk");
    return t("answers.default");
  }

  /** Slot déclenché par le filet de secours scripté (cf. docblock de fichier). */
  function localSlot(q: string): -1 | 1 | 2 {
    const n = norm(q);
    if (test("countOrder", n)) return 1;
    if (test("countHandover", n)) return 2;
    return -1;
  }

  function applyBump(slot: -1 | 1 | 2) {
    let alert: Alert | null = null;
    if (slot === 1) {
      const [title, meta] = t.raw("newOrder") as [string, string];
      alert = { kind: "ok", title, meta, fresh: true };
    } else if (slot === 2) {
      const [title, meta] = t.raw("handover") as [string, string];
      alert = { kind: "hi", title, meta, fresh: true };
    }
    setKpi(([a, b, c]) => [a + 1, slot === 1 ? b + 1 : b, slot === 2 ? c + 1 : c]);
    setPop(([a, b, c]) => [a + 1, slot === 1 ? b + 1 : b, slot === 2 ? c + 1 : c]);
    if (alert) {
      const added = alert;
      setAlerts((list) => [added, ...list].slice(0, 3));
    }
  }

  async function say(raw: string) {
    const q = raw.trim().slice(0, MAX_LEN);
    if (!q || sending.current) return;
    sending.current = true;
    try {
      const id = ++seq.current;
      const history = logSnapshot.current
        .filter((b) => !b.typing && b.text)
        .slice(-MAX_HISTORY)
        .map((b) => ({ role: b.who === "b" ? ("assistant" as const) : ("user" as const), text: b.text }));
      setSuggestions([]);
      setLog((l) => [...l, { who: "u" as const, text: q, time: clockTime(locale) }, { who: "b" as const, text: "", typing: id }].slice(-60));

      // Durée minimale de l'indicateur de frappe, en parallèle de l'appel
      // réseau (pas en plus). Pas de `Date.now()` (react-hooks/purity) : on
      // laisse courir un minuteur au lieu de comparer des horodatages.
      const minDelayDone = new Promise<void>((resolve) => {
        const tid = window.setTimeout(resolve, reduce ? 0 : DELAY_MS);
        timers.current.push(tid);
      });

      let reply = "";
      let slot: -1 | 1 | 2 = -1;
      let next: string[] = [];
      try {
        const res = await fetch("/api/ai-assistant/demo", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ message: q, history, locale }),
        });
        const result = (await res.json()) as AiDemoResult;
        if (result.ok) {
          reply = result.answer;
          slot = result.event === "order" ? 1 : result.event === "handover" ? 2 : -1;
          next = Array.isArray(result.suggestions) ? result.suggestions : [];
        }
      } catch {
        /* panne réseau : repli scripté ci-dessous */
      }
      if (!reply) {
        reply = answer(q);
        slot = localSlot(q);
        next = [];
      }
      if (!alive.current) return;

      await minDelayDone;
      if (!alive.current) return;
      applyBump(slot);
      setLog((l) => l.map((b) => (b.typing === id ? { who: "b", text: reply, time: clockTime(locale) } : b)));
      setSuggestions(next);
    } finally {
      sending.current = false;
    }
  }

  function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const input = inputRef.current;
    if (!input) return;
    void say(input.value);
    input.value = "";
  }

  const kpiLabels = t.raw("kpi") as [string, string, string];
  const typingNow = log.some((b) => b.typing);
  const showCta = !typingNow && log.filter((b) => b.who === "u").length >= CTA_AFTER;

  return (
    <>
      <div className="phone wa">
        <div className="scr">
          <div className="wa-sb" aria-hidden="true">
            <span>9:41</span>
            <span className="wa-isl" />
            <span className="wa-sbi">
              <svg viewBox="0 0 18 12" width="17" height="11">
                <path fill="currentColor" d="M1 9h2v3H1zM5 6h2v6H5zM9 3h2v9H9zM13 0h2v12h-2z" />
              </svg>
              <svg viewBox="0 0 16 12" width="15" height="11" {...svg}>
                <path d="M1 4.5a10 10 0 0 1 14 0M3.5 7a6.5 6.5 0 0 1 9 0M6 9.5a3 3 0 0 1 4 0" />
              </svg>
              <svg viewBox="0 0 26 12" width="24" height="11">
                <rect x="0.5" y="0.5" width="22" height="11" rx="3" fill="none" stroke="currentColor" opacity=".5" />
                <rect x="2" y="2" width="16" height="8" rx="1.5" fill="currentColor" />
                <path d="M24 4v4" stroke="currentColor" opacity=".5" />
              </svg>
            </span>
          </div>

          <div className="wa-hd">
            <svg viewBox="0 0 24 24" width="22" height="22" {...svg}>
              <path d="M15 18l-6-6 6-6" />
            </svg>
            <span className="wa-av" aria-hidden="true">
              {initials(t("shop"))}
            </span>
            <span className="wa-who">
              <b>{t("shop")}</b>
              <small className={typingNow ? "wa-tp" : undefined}>{typingNow ? t("typingStatus") : t("online")}</small>
            </span>
            <span className="wa-ics" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="21" height="21" {...svg}>
                <rect x="2" y="6" width="14" height="12" rx="2" />
                <path d="M16 10l6-4v12l-6-4z" />
              </svg>
              <svg viewBox="0 0 24 24" width="19" height="19" {...svg}>
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z" />
              </svg>
              <svg viewBox="0 0 24 24" width="19" height="19">
                <circle cx="12" cy="5" r="1.8" fill="currentColor" />
                <circle cx="12" cy="12" r="1.8" fill="currentColor" />
                <circle cx="12" cy="19" r="1.8" fill="currentColor" />
              </svg>
            </span>
          </div>

          <div className="log" id="dlog" role="log" aria-live="polite" ref={logRef}>
            <span className="wa-dt">{t("today")}</span>
            <p className="wa-nt">{t("notice")}</p>
            {log.map((b, i) => {
              const tail = i === 0 || log[i - 1].who !== b.who;
              return b.typing ? (
                <div key={i} className={clsx("bub bb typing", tail && "wa-tail")} aria-label={t("typing")}>
                  <i />
                  <i />
                  <i />
                </div>
              ) : (
                <div key={i} className={clsx("bub", b.who === "u" ? "bu" : "bb", tail && "wa-tail")}>
                  <span className="wa-tx">{b.text}</span>
                  {b.time && (
                    <span className="wa-mt">
                      {b.time}
                      {b.who === "u" && <ReadTicks />}
                    </span>
                  )}
                </div>
              );
            })}
            {showCta && (
              <p className="wa-nt wa-cta">
                {t("ctaText")}{" "}
                <a href={whatsappLink(t("orderMessage"))} target="_blank" rel="noopener noreferrer">
                  {t("order")}
                </a>
              </p>
            )}
          </div>

          {suggestions.length > 0 && (
            <div className="qs">
              {suggestions.map((q) => (
                <button key={q} type="button" onClick={() => void say(q)}>
                  {q}
                </button>
              ))}
            </div>
          )}

          <form onSubmit={onSubmit}>
            <label className="sr" htmlFor="dm">
              {t("inputLabel")}
            </label>
            <span className="wa-fld">
              <svg viewBox="0 0 24 24" width="22" height="22" {...svg}>
                <circle cx="12" cy="12" r="10" />
                <path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" />
              </svg>
              <input id="dm" ref={inputRef} placeholder={t("placeholder")} autoComplete="off" maxLength={MAX_LEN} />
              <svg viewBox="0 0 24 24" width="21" height="21" {...svg}>
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
              </svg>
            </span>
            <button aria-label={t("send")}>
              <ProtoIcon name="send" />
            </button>
          </form>
        </div>
      </div>
      <div className="own">
        <div className="ownh">
          <b>{t("dashboard")}</b>
          <span>{t("today")}</span>
        </div>
        <div className="kpi">
          {kpi.map((v, i) => (
            <div key={i}>
              <small>{kpiLabels[i]}</small>
              {/* `key` : relance l'animation « pop » du prototype à chaque hausse. */}
              <b key={pop[i]} id={`k${i + 1}`} className={pop[i] > 0 ? "pop" : undefined}>
                {v}
              </b>
            </div>
          ))}
        </div>
        <p className="dm" style={{ fontSize: "13px", margin: "18px 0 10px" }}>
          {t("alertsTitle")}
        </p>
        <ul className="alerts" id="alerts" aria-live="polite">
          {alerts.map((a, i) => (
            <li key={`${alerts.length}-${i}-${a.title}`} className={a.fresh && i === 0 ? `${a.kind} new` : a.kind}>
              <i />
              <span>
                <b>{a.title}</b>
                <small>{a.meta}</small>
              </span>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}
