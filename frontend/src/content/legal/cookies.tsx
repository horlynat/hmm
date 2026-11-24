import type { LegalSection } from "@/components/legal-proto/LegalPage";

export interface CookiesStrings {
  crumb: string;
  title: string;
  lede: string;
  updatedLabel: string;
  sections: LegalSection[];
}

export const cookiesContent: Record<"fr" | "en", CookiesStrings> = {
  fr: {
    crumb: "Cookies",
    title: "Politique de cookies",
    lede: "Ce que ce site enregistre sur votre appareil, et ce qu’il n’enregistre pas.",
    updatedLabel: "Dernière mise à jour",
    sections: [
      {
        id: "lg0",
        title: "En résumé",
        body: (
          <p>
            Ce site n’utilise <b>aucun cookie publicitaire</b> et ne vous suit pas d’un site à l’autre. C’est pourquoi aucun bandeau ne vous demande votre
            accord.
          </p>
        ),
      },
      {
        id: "lg1",
        title: "Ce qui est enregistré",
        body: (
          <div className="ltab">
            <div>
              <b>Session de l’espace clients et freelances</b>
              <span>Vous garder connecté en toute sécurité. Nécessaire, supprimé à la déconnexion.</span>
            </div>
            <div>
              <b>Préférence de langue</b>
              <span>Afficher le site en français ou en anglais. Nécessaire.</span>
            </div>
            <div>
              <b>Protection contre les attaques</b>
              <span>Filtrer les robots et les abus. Nécessaire.</span>
            </div>
            <div>
              <b>Mesure d’audience</b>
              <span>
                Aucune
              </span>
            </div>
          </div>
        ),
      },
      {
        id: "lg2",
        title: "WhatsApp et services externes",
        body: <p>Lorsque vous cliquez sur un bouton WhatsApp, vous quittez ce site : les règles de Meta s’appliquent alors.</p>,
      },
      {
        id: "lg3",
        title: "Si cela change",
        body: (
          <p>
            Si un outil déposant d’autres cookies est ajouté, un bandeau vous demandera votre accord avant tout dépôt, et vous pourrez le retirer à tout
            moment depuis cette page.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Gérer les cookies",
        body: <p>Vous pouvez supprimer les cookies dans les réglages de votre navigateur. Supprimer le cookie de session vous déconnecte de votre espace.</p>,
      },
    ],
  },
  en: {
    crumb: "Cookies",
    title: "Cookie policy",
    lede: "What this site stores on your device, and what it does not.",
    updatedLabel: "Last updated",
    sections: [
      {
        id: "lg0",
        title: "In summary",
        body: (
          <p>
            This site uses <b>no advertising cookies</b> and does not track you across other sites. That is why no banner asks for your consent.
          </p>
        ),
      },
      {
        id: "lg1",
        title: "What is stored",
        body: (
          <div className="ltab">
            <div>
              <b>Client and freelance area session</b>
              <span>Keeps you securely signed in. Necessary, deleted on sign-out.</span>
            </div>
            <div>
              <b>Language preference</b>
              <span>Shows the site in French or English. Necessary.</span>
            </div>
            <div>
              <b>Protection against attacks</b>
              <span>Filters bots and abuse. Necessary.</span>
            </div>
            <div>
              <b>Audience measurement</b>
              <span>
                None
              </span>
            </div>
          </div>
        ),
      },
      {
        id: "lg2",
        title: "WhatsApp and external services",
        body: <p>When you click a WhatsApp button, you leave this site: Meta’s rules then apply.</p>,
      },
      {
        id: "lg3",
        title: "If this changes",
        body: (
          <p>
            If a tool that sets other cookies is added, a banner will ask for your consent before any is set, and you will be able to withdraw it at any
            time from this page.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Managing cookies",
        body: <p>You can delete cookies in your browser settings. Deleting the session cookie signs you out of your account area.</p>,
      },
    ],
  },
};
