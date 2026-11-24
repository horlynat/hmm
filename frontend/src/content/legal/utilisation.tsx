import { Link } from "@/i18n/navigation";
import type { LegalSection } from "@/components/legal-proto/LegalPage";

export interface UtilisationStrings {
  crumb: string;
  title: string;
  lede: string;
  updatedLabel: string;
  sections: LegalSection[];
}

export const utilisationContent: Record<"fr" | "en", UtilisationStrings> = {
  fr: {
    crumb: "Conditions d’utilisation",
    title: "Conditions générales d’utilisation",
    lede: "Les règles d’utilisation de ce site, de son assistant, de la démonstration WhatsApp et de l’espace clients et freelances.",
    updatedLabel: "Dernière mise à jour",
    sections: [
      {
        id: "lg0",
        title: "Le site",
        body: (
          <p>
            horlynat.com présente mon activité personnelle et mes offres. Son accès est gratuit. Les informations publiées sont données de bonne foi et
            peuvent évoluer ; seule la proposition écrite que je vous adresse engage.
          </p>
        ),
      },
      {
        id: "lg1",
        title: "L’assistant du site",
        body: (
          <p>
            L’assistant vous oriente vers la bonne offre et répond aux questions générales. Il peut se tromper, et ses réponses ne valent ni devis, ni
            conseil juridique, ni conseil en assurance. Pour toute décision, je vous réponds en personne. N’y saisissez ni mot de passe, ni données
            bancaires, ni informations sensibles.
          </p>
        ),
      },
      {
        id: "lg2",
        title: "La démonstration WhatsApp",
        body: (
          <p>
            La « Boutique Ndinga » est une boutique fictive. Ses produits, ses prix et son tableau de bord sont des exemples destinés à illustrer le
            fonctionnement de l’offre. Aucun message n’est réellement envoyé à une entreprise.
          </p>
        ),
      },
      {
        id: "lg3",
        title: "Espace clients",
        body: (
          <p>
            Il vous permet de suivre vos projets et d’échanger vos documents. Vous êtes responsable de la confidentialité de vos identifiants ; la double
            authentification est vivement recommandée. Les documents déposés restent votre propriété.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Espace freelances",
        body: (
          <>
            <p>Les freelances qui rejoignent l’espace s’engagent à :</p>
            <ul className="chk" style={{ marginTop: "10px" }}>
              <li>
                <i>✓</i>fournir des informations exactes sur leur profil et leurs compétences ;
              </li>
              <li>
                <i>✓</i>garder strictement confidentiels les projets et les données auxquels ils accèdent, y compris après la collaboration ;
              </li>
              <li>
                <i>✓</i>ne pas démarcher directement les clients rencontrés par l’espace, pendant la collaboration et la durée prévue dans leur engagement écrit ;
              </li>
              <li>
                <i>✓</i>respecter les délais et la qualité convenus pour chaque tâche.
              </li>
            </ul>
            <p>
              Chaque collaboration fait l’objet d’un accord écrit fixant la tâche, la rémunération et la propriété du travail. Un profil peut être suspendu
              en cas de manquement.
            </p>
          </>
        ),
      },
      {
        id: "lg5",
        title: "Ce qui est interdit",
        body: (
          <p>
            Tenter d’accéder sans autorisation au site, à ses comptes ou à ses données, tester sa sécurité sans accord écrit, perturber son fonctionnement,
            ou l’utiliser à des fins illicites. Ces actes sont punis par la loi. Une faille découverte doit m’être signalée en privé.
          </p>
        ),
      },
      {
        id: "lg6",
        title: "Données et cookies",
        body: (
          <p>
            Voir la{" "}
            <Link className="lnk" href="/politique-de-confidentialite">
              politique de protection des données
            </Link>{" "}
            et la{" "}
            <Link className="lnk" href="/politique-de-cookies">
              politique de cookies
            </Link>
            .
          </p>
        ),
      },
      {
        id: "lg7",
        title: "Évolution",
        body: (
          <p>
            Ces conditions peuvent évoluer ; la version en ligne fait foi. Elles sont régies par le droit de la République du Congo ; à défaut d’accord
            amiable, le litige est porté devant les juridictions compétentes de Brazzaville.
          </p>
        ),
      },
    ],
  },
  en: {
    crumb: "Terms of use",
    title: "General terms of use",
    lede: "The rules for using this site, its assistant, the WhatsApp demo and the client and freelance area.",
    updatedLabel: "Last updated",
    sections: [
      {
        id: "lg0",
        title: "The site",
        body: (
          <p>
            horlynat.com presents my personal activity and my services. Access is free. Published information is given in good faith and may change; only
            the written proposal I send you is binding.
          </p>
        ),
      },
      {
        id: "lg1",
        title: "The site's assistant",
        body: (
          <p>
            The assistant directs you to the right service and answers general questions. It can make mistakes, and its answers are not a quote, legal
            advice, or insurance advice. For any decision, I answer you in person. Do not enter a password, banking details, or sensitive information in
            it.
          </p>
        ),
      },
      {
        id: "lg2",
        title: "The WhatsApp demo",
        body: (
          <p>
            “Ndinga Store” is a fictional shop. Its products, prices and dashboard are examples meant to illustrate how the service works. No message is
            actually sent to a business.
          </p>
        ),
      },
      {
        id: "lg3",
        title: "Client area",
        body: (
          <p>
            It lets you track your projects and exchange documents. You are responsible for keeping your credentials confidential; two-factor
            authentication is strongly recommended. Uploaded documents remain your property.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Freelance area",
        body: (
          <>
            <p>Freelancers who join the area agree to:</p>
            <ul className="chk" style={{ marginTop: "10px" }}>
              <li>
                <i>✓</i>provide accurate information about their profile and skills;
              </li>
              <li>
                <i>✓</i>keep strictly confidential the projects and data they access, including after the collaboration ends;
              </li>
              <li>
                <i>✓</i>not directly solicit clients met through the area, during the collaboration and for the period set out in their written commitment;
              </li>
              <li>
                <i>✓</i>meet the timelines and quality agreed for each task.
              </li>
            </ul>
            <p>
              Each collaboration is covered by a written agreement setting out the task, the pay and the ownership of the work. A profile may be suspended
              in case of a breach.
            </p>
          </>
        ),
      },
      {
        id: "lg5",
        title: "What is forbidden",
        body: (
          <p>
            Attempting to access the site, its accounts or its data without authorization, testing its security without written agreement, disrupting its
            operation, or using it for unlawful purposes. These acts are punishable by law. A discovered flaw must be reported to me privately.
          </p>
        ),
      },
      {
        id: "lg6",
        title: "Data and cookies",
        body: (
          <p>
            See the{" "}
            <Link className="lnk" href="/politique-de-confidentialite">
              data protection policy
            </Link>{" "}
            and the{" "}
            <Link className="lnk" href="/politique-de-cookies">
              cookie policy
            </Link>
            .
          </p>
        ),
      },
      {
        id: "lg7",
        title: "Changes",
        body: (
          <p>
            These terms may change; the version published online is authoritative. They are governed by the law of the Republic of the Congo; failing an amicable
            agreement, the dispute is brought before the competent courts of Brazzaville.
          </p>
        ),
      },
    ],
  },
};
