"use client";

import { useEffect } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui";
import { Link } from "@/i18n/navigation";

/**
 * Frontière d'erreur au niveau de la locale : capture les erreurs de rendu des
 * pages sans casser le header/footer. `reset()` retente le rendu du segment.
 */
export default function LocaleError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const t = useTranslations("errorPage");

  useEffect(() => {
    // Journalisation côté client ; le `digest` corrèle avec les logs serveur.
    console.error(error);
  }, [error]);

  return (
    <section className="px-6 py-24 text-center">
      <div className="mx-auto max-w-[560px]">
        <div className="mb-4 font-mono text-sm uppercase tracking-wide text-danger">
          {t("code")}
        </div>
        <h1 className="mb-4 text-[clamp(1.8rem,4vw,2.6rem)]">{t("title")}</h1>
        <p className="mb-8 opacity-70">{t("sub")}</p>
        <div className="flex flex-wrap justify-center gap-3">
          <Button onClick={reset}>{t("retry")}</Button>
          <Link href="/" className="btn-secondary">
            {t("backHome")}
          </Link>
        </div>
      </div>
    </section>
  );
}
