import { getTranslations } from "next-intl/server";
import clsx from "clsx";
import { Badge, Reveal } from "@/components/ui";
import type { Offer } from "@/lib/types";
import { whatsappLink } from "@/lib/whatsapp";
import { formatFcfa } from "@/lib/format/fcfa";
import { WhatsappIcon } from "./OfferIcon";
import { OpenAssistantButton } from "./OpenAssistantButton";

interface ChatLine {
  who: "bot" | "user";
  text: string;
}
interface MiniStep {
  title: string;
  desc: string;
}

/**
 * « Votre assistant WhatsApp, en action » (prototype de refonte 2026-11).
 *
 * La conversation du téléphone est une ILLUSTRATION figée (boutique
 * d'exemple, badge « Exemple ») : elle montre au visiteur ce que vivrait SON
 * client. Pour essayer une vraie IA, le second bouton ouvre l'assistant du
 * site, déjà en production (AiAssistantWidget → /api/assistant/chat, avec son
 * limiteur et son plafond budgétaire) — aucun nouveau point d'entrée IA
 * n'est ouvert ici.
 *
 * `offer` : l'offre de l'assistant WhatsApp si elle est publiée (prix et
 * délai réels) ; sans elle, le bloc prix est simplement omis.
 * `assistantAvailable` : le widget IA est-il monté (réglages présents) ? Sinon
 * le bouton d'essai est masqué plutôt que laissé sans effet.
 */
export async function AiDemoSection({
  locale,
  offer,
  assistantAvailable,
}: {
  locale: string;
  offer: Offer | null;
  assistantAvailable: boolean;
}) {
  const t = await getTranslations({ locale, namespace: "home.aiDemo" });
  const th = await getTranslations({ locale, namespace: "home.hero" });
  const chat = t.raw("chat") as ChatLine[];
  const steps = t.raw("steps") as MiniStep[];

  return (
    <section id="demo" className="px-6 py-20">
      <div className="mx-auto max-w-[1120px]">
        <Reveal className="mb-12 text-center">
          <Badge className="mb-3.5">{t("eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("title")}</h2>
          <p className="mx-auto max-w-[62ch] text-[var(--color-muted)]">{t("lede")}</p>
        </Reveal>

        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,300px)_minmax(0,1fr)_minmax(0,1fr)]">
          {/* Téléphone : illustration non interactive. */}
          <figure className="mx-auto w-full max-w-[300px] rounded-[2rem] border border-[var(--border-soft)] bg-[#0b141a] p-3 shadow-lg">
            <figcaption className="sr-only">{t("exampleBadge")} — {t("shopName")}</figcaption>
            <div className="flex items-center gap-3 rounded-t-[1.4rem] bg-[#202c33] px-4 py-3">
              <span className="text-[#25D366]"><WhatsappIcon size={20} /></span>
              <span>
                <b className="block text-[15px] text-white">{t("shopName")}</b>
                <span className="text-xs text-[#6EE7A0]">{t("shopSub")}</span>
              </span>
              <span className="ml-auto rounded-full bg-white/10 px-2 py-0.5 font-mono text-[0.6rem] uppercase tracking-wide text-white/80">
                {t("exampleBadge")}
              </span>
            </div>
            <div className="space-y-2.5 rounded-b-[1.4rem] bg-[#0b141a] px-3 py-4">
              {chat.map((line, i) => (
                <p
                  key={i}
                  className={clsx(
                    "max-w-[85%] rounded-xl px-3 py-2 text-left text-[0.82rem] leading-snug",
                    line.who === "user" ? "ml-auto bg-[#005c4b] text-white" : "bg-[#202c33] text-[#e9edef]",
                  )}
                >
                  {line.text}
                </p>
              ))}
            </div>
          </figure>

          {/* Tableau de bord : illustration (chiffres d'exemple). */}
          <div className="soft-card p-6" aria-hidden="true">
            <div className="mb-5 flex items-center justify-between">
              <b style={{ fontFamily: "var(--font-heading)" }}>{t("dashboardTitle")}</b>
              <span className="font-mono text-xs text-[var(--color-muted)]">{t("dashboardToday")}</span>
            </div>
            <div className="grid grid-cols-3 gap-3">
              {[
                [t("kpiMessages"), "128"],
                [t("kpiOrders"), "23"],
                [t("kpiHandedOver"), "4"],
              ].map(([label, value]) => (
                <div key={label} className="rounded-xl border border-[var(--border-softer)] p-3">
                  <small className="block text-[0.7rem] text-[var(--color-muted)]">{label}</small>
                  <b className="text-2xl" style={{ fontFamily: "var(--font-heading)" }}>{value}</b>
                </div>
              ))}
            </div>
          </div>

          <div>
            <ol className="mb-6 list-none space-y-4 p-0">
              {steps.map((step, i) => (
                <li key={i} className="flex gap-3">
                  <span aria-hidden="true" className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-primary/15 font-mono text-xs font-bold text-brand-primary">
                    {i + 1}
                  </span>
                  <span>
                    <b className="block text-sm">{step.title}</b>
                    <span className="text-sm text-[var(--color-muted)]">{step.desc}</span>
                  </span>
                </li>
              ))}
            </ol>
            {offer && (
              <p className="mb-5 text-sm text-[var(--color-muted)]">
                {offer.priceOnce ? th("priceOnce") : th("priceFrom")}{" "}
                <b className="text-lg font-extrabold text-brand-dark">{formatFcfa(offer.price, locale)}</b>
                {" · "}
                {th("readyIn", { delay: offer.delay })}
              </p>
            )}
            <div className="flex flex-wrap gap-3">
              <a className="btn-whatsapp" href={whatsappLink(t("orderMessage"))} target="_blank" rel="noopener noreferrer">
                <WhatsappIcon />
                {t("orderCta")}
              </a>
              {assistantAvailable && (
                <OpenAssistantButton className="btn-secondary">{t("tryCta")}</OpenAssistantButton>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
