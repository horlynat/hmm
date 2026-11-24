"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import type { AiDemoResult } from "@/lib/types";

/**
 * Démo « Votre assistant WhatsApp, en action » de l'accueil — boîte de
 * dialogue du prototype (phone .scr .log .qs), branchée sur la VRAIE IA :
 * POST /api/ai-assistant/demo (proxy Next → App\ApiResource\
 * AiDemoChatApiResource, persona « Boutique Ndinga », limiteur et plafond
 * budgétaire dédiés). L'ancien jeu de réponses scriptées (answer/bump sur
 * mots-clés) devient le FILET DE SECOURS : toute panne, tout refus du
 * garde-fou (`degraded`), ou limite de débit atteinte retombe sur lui plutôt
 * que d'afficher une erreur — la démo ne doit jamais paraître cassée.
 *
 * Mots-clés du filet de secours : sources de RegExp écrites dans les
 * fichiers de traduction (jamais une saisie du visiteur) ; la saisie est
 * seulement TESTÉE contre elles. Toute réponse (scriptée ou IA) est rendue
 * comme texte React, jamais en HTML.
 */
type Bubble = { who: "u" | "b"; text: string; typing?: number };
type Alert = { kind: string; title: string; meta: string; fresh?: boolean };

const DELAY_MS = 900;
const MAX_LEN = 300;
const MAX_HISTORY = 6;
const norm = (s: string) => s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

export function HomeDemo() {
  const t = useTranslations("landing.demo");
  const locale = useLocale();
  const reduce = useReducedMotion();
  const match = t.raw("match") as Record<string, string>;
  const initialAlerts = (t.raw("alerts") as [string, string, string][]).map(([kind, title, meta]) => ({ kind, title, meta }));

  const [log, setLog] = useState<Bubble[]>([
    { who: "b", text: t("welcome") },
    { who: "u", text: t("user1") },
    { who: "b", text: t("bot1") },
  ]);
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
  // App\Validator\ConversationHistory), donc silencieusement rabattu sur le
  // repli scripté au lieu de la vraie IA (contre-audit). Un seul envoi à la
  // fois évite ce cas plutôt que de le rattraper après coup.
  const sending = useRef(false);

  useEffect(() => {
    logSnapshot.current = log;
  }, [log]);

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
      setLog((l) => [...l, { who: "u" as const, text: q }, { who: "b" as const, text: "", typing: id }].slice(-60));

      // Durée minimale de l'indicateur de frappe, en parallèle de l'appel
      // réseau (pas en plus) : si le réseau est plus lent, elle est déjà
      // passée ; sinon on attend le reste. Pas de `Date.now()` ici (cf. règle
      // react-hooks/purity) : on mesure en laissant courir un minuteur, jamais
      // en comparant des horodatages.
      const minDelayDone = new Promise<void>((resolve) => {
        const tid = window.setTimeout(resolve, reduce ? 0 : DELAY_MS);
        timers.current.push(tid);
      });

      let reply = "";
      let slot: -1 | 1 | 2 = -1;
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
        }
      } catch {
        /* panne réseau : repli scripté ci-dessous */
      }
      if (!reply) {
        reply = answer(q);
        slot = localSlot(q);
      }
      if (!alive.current) return;

      await minDelayDone;
      if (!alive.current) return;
      applyBump(slot);
      setLog((l) => l.map((b) => (b.typing === id ? { who: "b", text: reply } : b)));
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

  return (
    <>
      <div className="phone">
        <div className="scr">
          <div className="bar">
            <i>
              <ProtoIcon name="wa" />
            </i>
            <span>
              <b style={{ display: "block", fontSize: "15px", color: "#fff" }}>{t("shop")}</b>
              <span style={{ fontSize: "12px", color: "#6EE7A0" }}>{t("shopSub")}</span>
            </span>
          </div>
          <div className="log" id="dlog" role="log" aria-live="polite" ref={logRef}>
            {log.map((b, i) =>
              b.typing ? (
                <div key={i} className="bub bb typing" aria-label={t("typing")}>
                  <i />
                  <i />
                  <i />
                </div>
              ) : (
                <div key={i} className={b.who === "u" ? "bub bu" : "bub bb"}>
                  {b.text}
                </div>
              ),
            )}
          </div>
          <div className="qs">
            {(t.raw("quick") as string[]).map((q) => (
              <button key={q} type="button" onClick={() => void say(q)}>
                {q}
              </button>
            ))}
          </div>
          <form onSubmit={onSubmit}>
            <label className="sr" htmlFor="dm">
              {t("inputLabel")}
            </label>
            <input id="dm" ref={inputRef} placeholder={t("placeholder")} autoComplete="off" maxLength={MAX_LEN} />
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
