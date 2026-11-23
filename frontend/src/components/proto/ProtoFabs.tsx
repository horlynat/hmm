"use client";

import { useEffect, useRef, useState } from "react";
import { siteConfig } from "@/config/site";
import { whatsappLink } from "@/lib/whatsapp";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { PL } from "./PL";
import { ProtoIcon } from "./ProtoSvg";

/**
 * Boutons flottants et panneau de l'assistant — copie conforme du prototype
 * (fabs(), aSay(), aAns() : markup `.fabs/.drawer/.dh2/.dbody/.dsug/.dfoot`,
 * réponses, délai de 750 ms, relances, « nouvelle conversation »).
 *
 * PHASE 1 : réponses SCRIPTÉES du prototype, à l'identique. La phase 2
 * remplace `answer()` par l'appel à l'assistant réel (/api/ai-assistant/chat,
 * limiteur et plafond de coût existants).
 *
 * Tout texte saisi est rendu comme texte React (jamais interprété en HTML),
 * comme l'esc() du prototype.
 */
type Msg = { u: string } | { t: string; l: string | null; lt: string | null; w: boolean };

const SUG: [Parameters<typeof ProtoIcon>[0]["name"], string, string][] = [
  ["shop", "Je veux plus de clients", "Un assistant WhatsApp qui répond et vend"],
  ["tag", "Combien ça coûte ?", "Les prix de départ de chaque offre"],
  ["lock", "Sécuriser mon site", "Un audit de sécurité en 7 jours"],
  ["umb", "Suis-je bien assuré ?", "Vérifier ce que mes contrats couvrent"],
];
const FOL = ["Quel délai ?", "Combien ça coûte ?", "Parler à Horlynat"];

const norm = (s: string) => s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

/** aAns() du prototype, à l'identique. */
function answer(q: string): [string, string | null, string | null, boolean] {
  if (/parler à horlynat/i.test(q)) return ["Avec plaisir : Horlynat vous répond en personne sur WhatsApp, en général dans la journée.", null, null, true];
  const n = norm(q);
  if (/whatsapp|assistant|commerce|boutique|client/.test(n)) return ["Je crée votre assistant IA WhatsApp en 21 jours : il répond à vos clients jour et nuit et prend les commandes. 199 999 FCFA payés une seule fois, sans abonnement.", "/offres/whatsapp", "Voir l'offre", false];
  if (/site|vitrine|visible|google/.test(n)) return ["Je mets votre site professionnel en ligne en 14 jours, avec un bouton WhatsApp sur chaque page.", "/offres/site", "Voir l'offre", false];
  if (/delai|duree|combien de temps|quand/.test(n)) return ["Chaque offre affiche son délai : de 7 jours pour un audit à 4 à 8 semaines pour une application sur mesure. Il est confirmé dans la proposition écrite.", "/offres", "Comparer les offres", false];
  if (/assur|couvert|sinistre|contrat|risque/.test(n)) return ["Je vérifie vos contrats d’assurance en 7 jours et je vous montre ce qui n’est pas couvert, avant un sinistre.", "/offres/risques", "Voir l'offre", false];
  if (/ralenti|plante|architect|lent/.test(n)) return ["L’audit d’architecture identifie en 10 jours ce qui ralentit votre application et comment la faire évoluer.", "/offres/architecture", "Voir l'offre", false];
  if (/securit|audit|faille|pirat/.test(n)) return ["L’audit de sécurité express prend 7 jours : je teste votre site et je vous remets un rapport clair.", "/offres/audit", "Voir l'offre", false];
  if (/prix|cout|tarif|combien|budget/.test(n)) return ["Chaque offre affiche son prix de départ. Vous pouvez les comparer selon votre budget.", "/offres", "Voir les offres", false];
  if (/appli|logiciel|mobile|plateforme/.test(n)) return ["Je conçois votre application web ou mobile, sécurisée dès la conception, en 4 à 8 semaines.", "/offres/application", "Voir l'offre", false];
  if (/projet|realisation|portfolio/.test(n)) return ["Mes projets en production : la plateforme de Digital Business Group, le Portail Digital et un agent WhatsApp IA.", "/realisations", "Voir les réalisations", false];
  return ["Le plus simple est d’écrire directement à Horlynat sur WhatsApp : il répond dans la journée.", null, null, true];
}

export function ProtoFabs() {
  const [open, setOpen] = useState(false);
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const [typing, setTyping] = useState(false);
  const logRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const reduce = useReducedMotion();

  useEffect(() => {
    if (!open) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    inputRef.current?.focus({ preventScroll: true });
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = prev;
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  useEffect(() => {
    if (logRef.current) logRef.current.scrollTop = logRef.current.scrollHeight;
  }, [msgs, typing]);

  function say(q: string) {
    const text = q.trim();
    if (!text) return;
    setMsgs((m) => [...m, { u: text }]);
    setTyping(true);
    window.setTimeout(() => {
      const [t, l, lt, w] = answer(text);
      setTyping(false);
      setMsgs((m) => [...m, { t, l, lt, w }]);
    }, reduce ? 0 : 750);
  }

  const last = msgs[msgs.length - 1];

  return (
    <div className="fabs" id="fabs">
      {open && (
        <>
          <div className="scrim" onClick={() => setOpen(false)} />
          <aside className="drawer" role="dialog" aria-modal="true" aria-label="Assistant de Horlynat">
            <header className="dh2">
              <span className="dav">
                <span className="dlogo"><ProtoIcon name="spark" /></span>
                <i />
              </span>
              <span className="dht">
                <b>Assistant de Horlynat</b>
                <small>En ligne · répond en quelques secondes</small>
              </span>
              {msgs.length > 0 && (
                <button type="button" className="ib" aria-label="Nouvelle conversation" title="Nouvelle conversation" onClick={() => setMsgs([])}>
                  <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                </button>
              )}
              <button type="button" className="ib" aria-label="Fermer l’assistant" onClick={() => setOpen(false)}>
                <ProtoIcon name="x" />
              </button>
            </header>
            <div className="dbody" id="alog" role="log" aria-live="polite" ref={logRef}>
              {msgs.length > 0 ? (
                <>
                  {msgs.map((m, i) =>
                    "u" in m ? (
                      <div key={i} className="bub bu">{m.u}</div>
                    ) : (
                      <div key={i} className="arow">
                        <span className="dlogo sm"><ProtoIcon name="spark" /></span>
                        <div className="bub bb">
                          {m.t}
                          {m.l && m.lt && (
                            <PL href={m.l} className="acard2" onClick={() => setOpen(false)}>
                              <span>{m.lt}</span>
                              <ProtoIcon name="ar" />
                            </PL>
                          )}
                          {m.w && (
                            <a className="b b-w sm" style={{ marginTop: 10 }} href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer">
                              <ProtoIcon name="wa" size={17} />
                              Écrire à Horlynat
                            </a>
                          )}
                        </div>
                      </div>
                    ),
                  )}
                  {typing && (
                    <div className="arow">
                      <span className="dlogo sm"><ProtoIcon name="spark" /></span>
                      <div className="bub bb typing"><i /><i /><i /></div>
                    </div>
                  )}
                  {!typing && last && !("u" in last) && (
                    <div className="fol">
                      {FOL.map((f) => (
                        <button key={f} type="button" onClick={() => say(f)}>{f}</button>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <>
                  <div className="dwel">
                    <span className="dlogo lg"><ProtoIcon name="spark" /></span>
                    <h2>Bonjour, que puis-je faire pour vous&nbsp;?</h2>
                    <p>Décrivez votre besoin : je vous oriente vers la bonne solution, avec son prix et son délai.</p>
                  </div>
                  <div className="dsug">
                    {SUG.map(([icon, title, sub]) => (
                      <button key={title} type="button" onClick={() => say(title)}>
                        <span className="sic2"><ProtoIcon name={icon} /></span>
                        <b>{title}</b>
                        <span>{sub}</span>
                      </button>
                    ))}
                  </div>
                  <a className="dwa" href={whatsappLink("Bonjour Horlynat, j’ai une question.")} target="_blank" rel="noopener noreferrer">
                    <span className="avs">
                      <svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="15" r="7" fill="rgba(255,255,255,.55)" /><path d="M6 40c1-9 7-14 14-14s13 5 14 14z" fill="rgba(255,255,255,.55)" /></svg>
                    </span>
                    <span>
                      <b>Préférez parler à Horlynat&nbsp;?</b>
                      <small>Réponse en personne sur WhatsApp</small>
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
                if (v.trim()) {
                  say(v);
                  if (inputRef.current) inputRef.current.value = "";
                }
              }}
            >
              <label className="sr" htmlFor="ai">Votre question</label>
              <div className="din">
                <textarea id="ai" ref={inputRef} rows={1} placeholder="Écrivez votre question…" maxLength={1000} />
                <button className="dsend" aria-label="Envoyer"><ProtoIcon name="send" /></button>
              </div>
              <p>
                Assistant IA : il peut se tromper. Pour un devis, Horlynat vous répond en personne.{" "}
                <PL href="/confidentialite" onClick={() => setOpen(false)}>Vos données</PL>
              </p>
            </form>
          </aside>
        </>
      )}
      <a className="fab" href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer" aria-label="Écrire sur WhatsApp" style={{ background: "#25D366", color: "#052611" }}>
        <ProtoIcon name="wa" size={26} />
      </a>
      <button type="button" className="fab" aria-label="Ouvrir l'assistant de Horlynat" aria-expanded={open} style={{ background: "var(--or)", color: "#2A1206" }} onClick={() => setOpen((v) => !v)}>
        <ProtoIcon name="spark" />
      </button>
    </div>
  );
}
