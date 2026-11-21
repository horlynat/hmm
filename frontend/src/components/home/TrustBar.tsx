import { getTranslations } from "next-intl/server";
import { CheckMark } from "./OfferIcon";

const ITEMS = ["price", "payment", "proposal", "security", "followUp"] as const;

/** Bandeau de garanties sous le hero (prototype de refonte 2026-11). */
export async function TrustBar({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "home.trust" });

  return (
    <section aria-label={t("label")} className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-5">
      <ul className="mx-auto flex max-w-[1120px] flex-wrap items-center justify-center gap-x-8 gap-y-3">
        {ITEMS.map((key) => (
          <li key={key} className="flex items-center gap-2 text-sm text-[var(--color-muted)]">
            <span className="text-success"><CheckMark /></span>
            {t(key)}
          </li>
        ))}
      </ul>
    </section>
  );
}
