"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Démo « Votre assistant WhatsApp, en action » de l'accueil — copie du
 * comportement du prototype (demoAns / bump / demoSay) : réponses SCRIPTÉES
 * d'une boutique d'exemple, indicateur de frappe de 900 ms, compteurs et
 * alertes du tableau de bord mis à jour. Aucune donnée ne quitte le
 * navigateur (pas d'appel réseau) ; le persona IA « boutique » relèvera d'un
 * lot séparé.
 *
 * Mots-clés : sources de RegExp écrites dans les fichiers de traduction
 * (jamais une saisie du visiteur) ; la saisie est seulement TESTÉE contre
 * elles et rendue comme texte React.
 */
type Bubble = { who: "u" | "b"; text: string; typing?: number };
type Alert = { kind: string; title: string; meta: string; fresh?: boolean };

const DELAY_MS = 900;
const MAX_LEN = 300;
const norm = (s: string) => s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

export function HomeDemo() {
  const t = useTranslations("landing.demo");
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

  useEffect(() => {
    const pending = timers.current;
    return () => pending.forEach((id) => window.clearTimeout(id));
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

  function bump(q: string) {
    const n = norm(q);
    let alert: Alert | null = null;
    let slot = -1;
    if (test("countOrder", n)) {
      slot = 1;
      const [title, meta] = t.raw("newOrder") as [string, string];
      alert = { kind: "ok", title, meta, fresh: true };
    } else if (test("countHandover", n)) {
      slot = 2;
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

  function say(raw: string) {
    const q = raw.trim().slice(0, MAX_LEN);
    if (!q) return;
    const reply = answer(q);
    const id = ++seq.current;
    setLog((l) => [...l, { who: "u" as const, text: q }, { who: "b" as const, text: "", typing: id }].slice(-60));
    const delay = reduce ? 0 : DELAY_MS;
    timers.current.push(
      window.setTimeout(() => bump(q), delay),
      window.setTimeout(
        () => setLog((l) => l.map((b) => (b.typing === id ? { who: "b", text: reply } : b))),
        delay,
      ),
    );
  }

  function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const input = inputRef.current;
    if (!input) return;
    say(input.value);
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
              <button key={q} type="button" onClick={() => say(q)}>
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
