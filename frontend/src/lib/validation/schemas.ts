/**
 * Schémas de validation partagés (Zod) — source unique de vérité pour la
 * validation des formulaires, réutilisable côté client (react-hook-form) ET
 * côté serveur (Server Actions), afin qu'aucune donnée non validée n'atteigne
 * l'API. Les messages sont injectés via un traducteur next-intl du namespace
 * `validation`, ce qui garde les schémas 100 % i18n.
 */

import { z } from "zod";

/** Traducteur minimal compatible avec `useTranslations("validation")` / `getTranslations`. */
export type Translator = (
  key: string,
  values?: Record<string, string | number | Date>,
) => string;

/** Regex e-mail volontairement simple mais suffisante côté client ; le backend reste l'autorité. */
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
/** Au moins 8 caractères, une majuscule, une minuscule, un chiffre, un caractère spécial. */
const STRONG_PASSWORD_RE = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;
/** Téléphone international tolérant : +, espaces, points, tirets, parenthèses ; 6 à 20 chiffres. */
const PHONE_RE = /^[+]?[()\d\s.\-]{6,20}$/;

export const emailField = (t: Translator) =>
  z
    .string()
    .trim()
    .min(1, t("emailRequired"))
    .regex(EMAIL_RE, t("email"));

export const nameField = (t: Translator) =>
  z.string().trim().min(1, t("nameRequired")).max(120, t("tooLong", { max: 120 }));

export const strongPasswordField = (t: Translator) =>
  z.string().min(1, t("passwordRequired")).regex(STRONG_PASSWORD_RE, t("passwordWeak"));

/** Téléphone optionnel : vide accepté, sinon doit être un format plausible. */
export const optionalPhoneField = (t: Translator) =>
  z
    .string()
    .trim()
    .max(30, t("tooLong", { max: 30 }))
    .refine((v) => v === "" || PHONE_RE.test(v), t("phoneInvalid"))
    .optional()
    .or(z.literal(""));

export const optionalUrlField = (t: Translator) =>
  z
    .string()
    .trim()
    .refine((v) => v === "" || /^https?:\/\/.+/.test(v), t("url"))
    .optional()
    .or(z.literal(""));

export const agreeTermsField = (t: Translator) =>
  z.literal(true, { message: t("agreeTerms") });

// ---------------------------------------------------------------------------
// Schémas de formulaires complets (fabriques)
// ---------------------------------------------------------------------------

export const loginSchema = (t: Translator) =>
  z.object({
    email: emailField(t),
    password: z.string().min(1, t("passwordRequired")),
  });
export type LoginValues = z.infer<ReturnType<typeof loginSchema>>;

export const clientRegisterSchema = (t: Translator) =>
  z.object({
    name: nameField(t),
    email: emailField(t),
    phone: optionalPhoneField(t),
    password: strongPasswordField(t),
    agreeTerms: agreeTermsField(t),
  });
export type ClientRegisterValues = z.infer<ReturnType<typeof clientRegisterSchema>>;

export const freelanceRegisterSchema = (t: Translator) =>
  z.object({
    name: nameField(t),
    email: emailField(t),
    phone: optionalPhoneField(t),
    password: strongPasswordField(t),
    specialties: z.array(z.string()).optional(),
    availability: z.string().optional(),
    portfolioUrl: optionalUrlField(t),
    bio: z.string().max(2000, t("tooLong", { max: 2000 })).optional().or(z.literal("")),
    agreeTerms: agreeTermsField(t),
  });
export type FreelanceRegisterValues = z.infer<ReturnType<typeof freelanceRegisterSchema>>;

export const profileSchema = (t: Translator) =>
  z.object({
    fullName: z.string().trim().max(120, t("tooLong", { max: 120 })).optional().or(z.literal("")),
    phone: optionalPhoneField(t),
    bio: z.string().max(2000, t("tooLong", { max: 2000 })).optional().or(z.literal("")),
    specialties: z.string().max(500, t("tooLong", { max: 500 })).optional().or(z.literal("")),
    availability: z.string().max(120, t("tooLong", { max: 120 })).optional().or(z.literal("")),
    portfolioUrl: optionalUrlField(t),
    password: z
      .string()
      .refine((v) => v === "" || STRONG_PASSWORD_RE.test(v), t("passwordWeak"))
      .optional()
      .or(z.literal("")),
  });
export type ProfileValues = z.infer<ReturnType<typeof profileSchema>>;

export const newsletterSchema = (t: Translator) =>
  z.object({ email: emailField(t) });
export type NewsletterValues = z.infer<ReturnType<typeof newsletterSchema>>;

/** Rendez-vous : téléphone requis dynamiquement si canal WhatsApp/Appel. */
export const appointmentSchema = (t: Translator) =>
  z
    .object({
      name: nameField(t),
      company: z.string().trim().max(120, t("tooLong", { max: 120 })).optional().or(z.literal("")),
      email: emailField(t),
      phone: z.string().trim().max(30, t("tooLong", { max: 30 })).optional().or(z.literal("")),
      canal: z.string().min(1, t("selectRequired")),
      slot: z.string().min(1, t("selectRequired")),
      subject: z.string().min(1, t("selectRequired")),
      message: z.string().max(2000, t("tooLong", { max: 2000 })).optional().or(z.literal("")),
      agreeTerms: agreeTermsField(t),
    })
    .refine(
      (data) =>
        !["whatsapp", "phone"].includes(data.canal) ||
        (typeof data.phone === "string" && PHONE_RE.test(data.phone)),
      { path: ["phone"], message: t("phoneRequiredForChannel") },
    );
export type AppointmentValues = z.infer<ReturnType<typeof appointmentSchema>>;
