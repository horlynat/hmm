/**
 * Contenu long du centre d'aide public (/aide) : prose "comment ça marche" et
 * FAQ. Module TS dédié plutôt que fr.json/en.json — ce texte est édité
 * indépendamment des libellés d'UI et n'a pas besoin des fonctionnalités
 * ICU/pluriel de next-intl.
 */

export interface HelpCenterDoc {
  title: string;
  body: string;
}

export interface HelpCenterFaqEntry {
  question: string;
  answer: string;
}

export interface HelpCenterContent {
  docs: HelpCenterDoc[];
  faq: HelpCenterFaqEntry[];
}

export const helpCenterContent: Record<"fr" | "en", HelpCenterContent> = {
  fr: {
    docs: [
      {
        title: "Demander un devis",
        body: "Le questionnaire guidé (page Contact) vous demande le type de prestation (développement web, mobile, IA, cybersécurité, assurance, design...), le contexte de votre projet, un budget indicatif et vos coordonnées. Vous recevez un retour structuré sous 48h.",
      },
      {
        title: "Confier un projet suivi",
        body: "Pour un accompagnement suivi (au-delà d'un devis ponctuel), la création d'un compte client permet de décrire votre projet, suivre son avancement et centraliser les échanges depuis votre espace personnel.",
      },
      {
        title: "Espace client",
        body: "Depuis votre compte, retrouvez vos demandes de devis, vos projets en cours, vos factures et vos paramètres de sécurité (session, mot de passe, double authentification).",
      },
      {
        title: "Support",
        body: "Pour toute question technique ou administrative, ouvrez un ticket de support depuis cette page. Vous recevez un email de confirmation avec un lien pour suivre l'échange et répondre à tout moment, sans avoir besoin de créer un compte.",
      },
    ],
    faq: [
      {
        question: "Faut-il un compte pour demander un devis ?",
        answer: "Non, le questionnaire de devis est ouvert à tous. Un compte n'est nécessaire que pour confier un projet suivi et accéder à votre espace client.",
      },
      {
        question: "Combien de temps pour recevoir une réponse à un devis ?",
        answer: "Un retour structuré est envoyé sous 48h après réception de votre demande.",
      },
      {
        question: "Comment suivre l'échange sur mon ticket de support ?",
        answer: "L'email de confirmation contient un lien permanent vers le fil de votre ticket : vous pouvez le consulter et y répondre à tout moment, sans compte ni mot de passe.",
      },
      {
        question: "Puis-je répondre à un ticket déjà marqué comme résolu ?",
        answer: "Oui. Une réponse de votre part rouvre automatiquement le ticket, qui repasse \"En cours\" côté support.",
      },
      {
        question: "Comment rejoindre l'équipe en tant que freelance ?",
        answer: "La page \"Rejoindre en freelance\" permet de créer un profil collaborateur (spécialités, disponibilité, portfolio) pour être proposé sur les missions clients correspondantes.",
      },
    ],
  },
  en: {
    docs: [
      {
        title: "Requesting a quote",
        body: "The guided questionnaire (Contact page) asks about the type of work (web, mobile, AI, cybersecurity, insurance, design...), your project's context, an indicative budget and your contact details. You receive a structured response within 48h.",
      },
      {
        title: "Bringing in a tracked project",
        body: "For ongoing support (beyond a one-off quote), creating a client account lets you describe your project, track its progress and centralize communication from your personal space.",
      },
      {
        title: "Client area",
        body: "From your account, find your quote requests, ongoing projects, invoices and security settings (sessions, password, two-factor authentication).",
      },
      {
        title: "Support",
        body: "For any technical or administrative question, open a support ticket from this page. You'll receive a confirmation email with a link to follow the thread and reply at any time, no account required.",
      },
    ],
    faq: [
      {
        question: "Do I need an account to request a quote?",
        answer: "No, the quote questionnaire is open to everyone. An account is only needed to bring in a tracked project and access your client area.",
      },
      {
        question: "How long until I get a response to a quote?",
        answer: "A structured response is sent within 48h of receiving your request.",
      },
      {
        question: "How do I follow up on my support ticket?",
        answer: "The confirmation email contains a permanent link to your ticket's thread: you can view it and reply at any time, no account or password needed.",
      },
      {
        question: "Can I reply to a ticket already marked as resolved?",
        answer: "Yes. Any reply from you automatically reopens the ticket, which switches back to \"In progress\" on the support side.",
      },
      {
        question: "How do I join the team as a freelancer?",
        answer: "The \"Join as a freelancer\" page lets you create a collaborator profile (specialties, availability, portfolio) to be proposed for matching client missions.",
      },
    ],
  },
};
