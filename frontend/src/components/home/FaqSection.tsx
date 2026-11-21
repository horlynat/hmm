import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Badge, Reveal } from "@/components/ui";
import { whatsappLink } from "@/lib/whatsapp";

interface Faq {
  q: string;
  a: string;
}

/**
 * FAQ en accordéon natif (`<details>`) : aucun JavaScript, accessible au
 * clavier et aux lecteurs d'écran par défaut. La question sur les données
 * (4e) renvoie vers la politique de confidentialité.
 */
export async function FaqSection({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "home.faq" });
  const items = t.raw("items") as Faq[];

  return (
    <section id="faq" className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-20">
      <div className="mx-auto max-w-[820px]">
        <Reveal className="mb-10 text-center">
          <Badge className="mb-3.5">{t("eyebrow")}</Badge>
          <h2 className="text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("title")}</h2>
        </Reveal>
        <div className="space-y-3">
          {items.map((item, i) => (
            <details key={item.q} className="soft-card group p-5">
              <summary className="cursor-pointer list-none font-semibold marker:hidden" style={{ fontFamily: "var(--font-heading)" }}>
                <span className="flex items-center justify-between gap-4">
                  {item.q}
                  <span aria-hidden="true" className="text-brand-primary transition-transform group-open:rotate-45">+</span>
                </span>
              </summary>
              <p className="mt-3 text-sm text-[var(--color-muted)]">{item.a}</p>
              {i === 3 && (
                <Link href="/politique-de-confidentialite" className="mt-2 inline-block text-sm font-semibold text-brand-primary hover:underline">
                  {t("privacyLink")}
                </Link>
              )}
            </details>
          ))}
        </div>
        <p className="mt-8 text-center text-sm text-[var(--color-muted)]">
          {t("more")}{" "}
          <a href={whatsappLink(t("moreMessage"))} target="_blank" rel="noopener noreferrer" className="font-semibold text-brand-primary hover:underline">
            {t("moreCta")}
          </a>
        </p>
      </div>
    </section>
  );
}
