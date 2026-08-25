"use client";

import { useEffect, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useLocale, useTranslations } from "next-intl";
import { newsletterSchema, type NewsletterValues } from "@/lib/validation/schemas";
import { FormMessage } from "@/components/ui/form";
import { subscribeToNewsletter } from "@/actions/newsletter";

type Status = "idle" | "pending" | "success" | "rate_limited" | "error";

/** Durée d'affichage de la carte de confirmation avant de retomber sur le formulaire — retour explicite de Horlynat. */
const SUCCESS_CARD_DURATION_MS = 20_000;

/**
 * Inscription à la newsletter du blog — cf. App\Entity\NewsletterSubscriber
 * côté backend pour le détail. Longtemps un stub purement visuel (un faux
 * succès local, `setSuccess(true)` sans aucun appel réseau) avec un
 * disclaimer honnête ("fonctionnalité à venir") resté visible en prod bien
 * après que le reste du site ait mûri — repéré par Horlynat lui-même.
 * Appelle désormais réellement subscribeToNewsletter (Server Action ->
 * App\ApiResource\NewsletterSubscriberApiResource), même structure que les
 * autres formulaires publics du site (cf. AppointmentForm, SupportTicketForm).
 */
export function NewsletterForm() {
  const t = useTranslations("blog.newsletter");
  const tv = useTranslations("validation");
  const locale = useLocale();
  const [status, setStatus] = useState<Status>("idle");

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<NewsletterValues>({
    resolver: zodResolver(newsletterSchema(tv)),
    defaultValues: { email: "" },
  });

  // Retombe sur le formulaire après SUCCESS_CARD_DURATION_MS — la carte de
  // confirmation ne doit pas rester affichée indéfiniment (retour explicite
  // de Horlynat). Nettoyé si le composant se démonte ou si un nouveau statut
  // écrase "success" avant l'échéance (double soumission par ex.).
  const dismissTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => {
    if (status !== "success") return;
    dismissTimer.current = setTimeout(() => setStatus("idle"), SUCCESS_CARD_DURATION_MS);
    return () => {
      if (dismissTimer.current) clearTimeout(dismissTimer.current);
    };
  }, [status]);

  async function onSubmit(values: NewsletterValues) {
    setStatus("pending");
    const result = await subscribeToNewsletter({ email: values.email, locale });
    if (result.ok) {
      setStatus("success");
      reset();
      return;
    }
    setStatus(result.error === "rate_limited" ? "rate_limited" : "error");
  }

  return (
    <section
      id="newsletter"
      className="px-6 py-16 text-center text-white"
      style={{
        background:
          "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
      }}
    >
      <div className="mx-auto max-w-[1120px]">
        <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">{t("title")}</h2>
        <p className="mx-auto mb-5 max-w-[52ch] opacity-85">{t("text")}</p>
        {/* Hauteur minimale fixe : le passage formulaire <-> carte de succès
            ne fait pas sauter le reste de la page. */}
        <div className="mx-auto flex min-h-[168px] max-w-[420px] items-center justify-center">
          {status === "success" ? (
            <div
              role="status"
              aria-live="polite"
              className="flex w-full flex-col items-center justify-center gap-2 rounded-[var(--radius-lg)] border border-white/25 bg-white/10 px-8 py-9 text-center backdrop-blur-sm"
            >
              <span className="text-3xl" aria-hidden="true">
                ✓
              </span>
              <p className="text-base font-semibold text-white">{t("success")}</p>
            </div>
          ) : (
            <div className="w-full">
              <form
                onSubmit={handleSubmit(onSubmit)}
                noValidate
                className="flex flex-wrap items-start justify-center gap-2.5"
              >
                <div className="min-w-[200px] flex-1 text-left">
                  <label htmlFor="newsletter-email" className="sr-only">
                    {t("placeholder")}
                  </label>
                  <input
                    id="newsletter-email"
                    type="email"
                    placeholder={t("placeholder")}
                    aria-invalid={errors.email ? true : undefined}
                    aria-describedby={errors.email ? "newsletter-email-error" : undefined}
                    disabled={status === "pending"}
                    className="w-full rounded-[var(--radius-sm)] border border-white/30 bg-white/10 px-4 py-2.5 text-sm text-white placeholder:text-white/70 focus:outline-none focus:ring-2 focus:ring-white/60 disabled:opacity-60"
                    {...register("email")}
                  />
                  {errors.email && (
                    <p id="newsletter-email-error" role="alert" className="mt-1.5 text-xs font-medium text-white">
                      {errors.email.message}
                    </p>
                  )}
                </div>
                <button
                  type="submit"
                  disabled={status === "pending"}
                  className="rounded-[var(--radius-sm)] bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-on-brand-light)] transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {t("submit")}
                </button>
              </form>
              {status === "rate_limited" && (
                <FormMessage variant="error" className="text-white">
                  {tv("rateLimited")}
                </FormMessage>
              )}
              {status === "error" && (
                <FormMessage variant="error" className="text-white">
                  {tv("genericError")}
                </FormMessage>
              )}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
