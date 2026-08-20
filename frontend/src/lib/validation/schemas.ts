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
    availability: z.string().max(120, t("tooLong", { max: 120 })).optional().or(z.literal("")),
    portfolioUrl: optionalUrlField(t),
    // Compétences/langues gérées en state local (sélecteur à puces), pas via react-hook-form — cf. ProfileForm.tsx.
    yearsOfExperience: z
      .string()
      .trim()
      .refine((v) => v === "" || (/^\d+$/.test(v) && Number(v) <= 60), t("tooLong", { max: 60 }))
      .optional()
      .or(z.literal("")),
    city: z.string().trim().max(100, t("tooLong", { max: 100 })).optional().or(z.literal("")),
    linkedinUrl: optionalUrlField(t),
    githubUrl: optionalUrlField(t),
  });
export type ProfileValues = z.infer<ReturnType<typeof profileSchema>>;

/** Changement de mot de passe connecté : preuve de l'ancien mot de passe exigée côté backend. */
export const changePasswordSchema = (t: Translator) =>
  z
    .object({
      currentPassword: z.string().min(1, t("passwordRequired")),
      password: strongPasswordField(t),
      confirmPassword: z.string().min(1, t("passwordRequired")),
    })
    .refine((data) => data.password === data.confirmPassword, {
      path: ["confirmPassword"],
      message: t("passwordMismatch"),
    });
export type ChangePasswordValues = z.infer<ReturnType<typeof changePasswordSchema>>;

export const newsletterSchema = (t: Translator) =>
  z.object({ email: emailField(t) });
export type NewsletterValues = z.infer<ReturnType<typeof newsletterSchema>>;

export const forgotPasswordSchema = (t: Translator) =>
  z.object({ email: emailField(t) });
export type ForgotPasswordValues = z.infer<ReturnType<typeof forgotPasswordSchema>>;

export const resetPasswordSchema = (t: Translator) =>
  z
    .object({
      password: strongPasswordField(t),
      confirmPassword: z.string().min(1, t("passwordRequired")),
    })
    .refine((data) => data.password === data.confirmPassword, {
      path: ["confirmPassword"],
      message: t("passwordMismatch"),
    });
export type ResetPasswordValues = z.infer<ReturnType<typeof resetPasswordSchema>>;

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

export const supportTicketSchema = (t: Translator) =>
  z.object({
    name: nameField(t),
    email: emailField(t),
    subject: z.string().trim().min(1, t("selectRequired")).max(255, t("tooLong", { max: 255 })),
    message: z.string().trim().min(10, t("tooShort", { min: 10 })).max(5000, t("tooLong", { max: 5000 })),
  });
export type SupportTicketValues = z.infer<ReturnType<typeof supportTicketSchema>>;

export const supportTicketReplySchema = (t: Translator) =>
  z.object({
    message: z.string().trim().min(10, t("tooShort", { min: 10 })).max(5000, t("tooLong", { max: 5000 })),
  });
export type SupportTicketReplyValues = z.infer<ReturnType<typeof supportTicketReplySchema>>;

export const candidateMessageSchema = (t: Translator) =>
  z.object({
    body: z.string().trim().min(10, t("tooShort", { min: 10 })).max(5000, t("tooLong", { max: 5000 })),
  });
export type CandidateMessageValues = z.infer<ReturnType<typeof candidateMessageSchema>>;
