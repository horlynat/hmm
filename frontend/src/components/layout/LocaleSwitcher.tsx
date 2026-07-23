"use client";

import { useLocale } from "next-intl";
import { useParams } from "next/navigation";
import clsx from "clsx";
import { usePathname, useRouter } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

export function LocaleSwitcher() {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const params = useParams();

  function switchTo(nextLocale: (typeof routing.locales)[number]) {
    router.replace(
      // @ts-expect-error -- pathname vient d'une route connue de next-intl
      { pathname, params },
      { locale: nextLocale },
    );
  }

  return (
    <div className="flex items-center gap-1 font-mono text-xs">
      {routing.locales.map((l) => (
        <button
          key={l}
          type="button"
          onClick={() => switchTo(l)}
          className={clsx(
            "rounded px-1.5 py-1 uppercase transition-opacity",
            l === locale
              ? "font-bold text-brand-primary opacity-100"
              : "opacity-50 hover:opacity-100",
          )}
        >
          {l}
        </button>
      ))}
    </div>
  );
}
