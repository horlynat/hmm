"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { newsletterSchema, type NewsletterValues } from "@/lib/validation/schemas";
import { FormMessage } from "@/components/ui/form";

/** Stub visuel — aucune entité Newsletter/Subscriber côté backend, cf. plan. */
export function NewsletterForm() {
  const t = useTranslations("blog.newsletter");
  const tv = useTranslations("validation");
  const [success, setSuccess] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<NewsletterValues>({
    resolver: zodResolver(newsletterSchema(tv)),
    defaultValues: { email: "" },
  });

  function onSubmit() {
    setSuccess(true);
    reset();
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
        <form
          onSubmit={handleSubmit(onSubmit)}
          noValidate
          className="mx-auto flex max-w-[420px] flex-wrap items-start justify-center gap-2.5"
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
              className="w-full rounded-[var(--radius-sm)] border border-white/30 bg-white/10 px-4 py-2.5 text-sm text-white placeholder:text-white/70 focus:outline-none focus:ring-2 focus:ring-white/60"
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
            className="rounded-[var(--radius-sm)] bg-white px-4 py-2.5 text-sm font-semibold text-[var(--color-on-brand-light)] transition-opacity hover:opacity-90"
          >
            {t("submit")}
          </button>
        </form>
        {success && (
          <FormMessage variant="success" className="text-white">
            {t("success")}
          </FormMessage>
        )}
        <p className="mt-3 text-xs opacity-70">{t("note")}</p>
      </div>
    </section>
  );
}
