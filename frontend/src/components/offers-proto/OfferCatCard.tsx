import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Offer } from "@/lib/types";
import { formatFcfa } from "@/lib/format/fcfa";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { offerIcon } from "@/components/landing/offerHelpers";

/** « Pour : » : les deux premiers publics saisis, comme le prototype. */
export function audienceShort(audience: string): string {
  return audience.split(",").slice(0, 2).join(",");
}

/** Carte d'offre du catalogue (catCard du prototype : `.oc`). Utilisable côté serveur et client. */
export function OfferCatCard({ offer: o }: { offer: Offer }) {
  const t = useTranslations("catalog");
  const tl = useTranslations("landing.hero");
  const locale = useLocale();
  return (
    <Link className="oc" href={{ pathname: "/offres/[slug]", params: { slug: o.slug } }}>
      <span className="oic">
        <ProtoIcon name={offerIcon(o.icon)} />
      </span>
      <h3>{o.title}</h3>
      {o.afterItems[0] && <p className="prom">{o.afterItems[0].replace(/\.?$/, ".")}</p>}
      {o.audience && <p className="who2">{t("for", { who: audienceShort(o.audience) })}</p>}
      <ul>
        {o.includes.slice(0, 3).map((item) => (
          <li key={item}>
            <ProtoIcon name="ok" />
            {item}
          </li>
        ))}
      </ul>
      <div className="ocf">
        <span>
          <small>{o.priceOnce ? tl("priceOnce") : tl("priceFrom")}</small>
          <b>{formatFcfa(o.price, locale)}</b>
        </span>
        <span className="dl">{tl("readyIn", { delay: o.delay })}</span>
      </div>
      <span className="ocl">
        {t("see")} <ProtoIcon name="ar" />
      </span>
    </Link>
  );
}
