import type { LegalSection } from "@/components/legal-proto/LegalPage";

/**
 * Contenu bilingue de /mentions-legales — prose du prototype (FR, texte
 * d'origine), EN traduit. Module TS dédié, pas next-intl : texte long,
 * structuré par sections ancrées (cf. src/content/help-center.ts).
 */
export interface MentionsLegalesStrings {
  crumb: string;
  title: string;
  lede: string;
  updatedLabel: string;
  sections: LegalSection[];
}

const CONTACT_EMAIL = "contact@horlynat.com";

export const mentionsLegalesContent: Record<"fr" | "en", MentionsLegalesStrings> = {
  fr: {
    crumb: "Mentions légales",
    title: "Mentions légales",
    lede: "Qui édite ce site, qui l’héberge, et à quel titre.",
    updatedLabel: "Dernière mise à jour",
    sections: [
      {
        id: "lg0",
        title: "Éditeur",
        body: (
          <>
            <p>
              Horlynat Mampassi Mbama, personne physique, ingénieur et architecte logiciel, consultant en cybersécurité, gestion et management des risques.
              Brazzaville, République du Congo. Contact :{" "}
              <a className="lnk" href={`mailto:${CONTACT_EMAIL}`}>
                {CONTACT_EMAIL}
              </a>
              , WhatsApp +242 06 642 90 90.
            </p>
            <p>Directeur de la publication : Horlynat Mampassi Mbama.</p>
          </>
        ),
      },
      {
        id: "lg1",
        title: "Activité personnelle et Digital Business Group",
        body: (
          <p>
            Ce site présente mon activité <b>personnelle</b>. Je suis aussi fondateur et directeur général de Digital Business Group, société distincte. Les
            offres et les contrats proposés ici me lient personnellement, et non Digital Business Group, sauf mention écrite contraire. Les réalisations
            menées pour Digital Business Group sont présentées avec son accord.
          </p>
        ),
      },
      {
        id: "lg2",
        title: "Hébergement",
        body: (
          <p>
            LWS – Ligne Web Services SAS, 10 rue Penthièvre, 75008 Paris, France. Contact : www.lws.fr
          </p>
        ),
      },
      {
        id: "lg3",
        title: "Prix et contenus",
        body: (
          <p>
            Les prix sont exprimés en francs CFA (XAF) et sont des prix de départ indicatifs. Les délais et exemples affichés, comme la boutique de
            démonstration de l’assistant WhatsApp, sont illustratifs. Seule la proposition écrite remise après échange engage.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Propriété intellectuelle",
        body: (
          <p>
            Les textes, illustrations et la présentation de ce site m’appartiennent. Les marques citées, comme WhatsApp, appartiennent à leurs propriétaires.
            La police Montserrat est utilisée sous licence libre SIL Open Font License.
          </p>
        ),
      },
      {
        id: "lg5",
        title: "Sécurité",
        body: (
          <p>
            Si vous découvrez une faille sur ce site, merci de me la signaler en privé à{" "}
            <a className="lnk" href={`mailto:${CONTACT_EMAIL}?subject=Signalement%20de%20s%C3%A9curit%C3%A9`}>
              {CONTACT_EMAIL}
            </a>
            , sans l’exploiter ni la rendre publique. Je vous réponds rapidement.
          </p>
        ),
      },
    ],
  },
  en: {
    crumb: "Legal notice",
    title: "Legal notice",
    lede: "Who publishes this site, who hosts it, and in what capacity.",
    updatedLabel: "Last updated",
    sections: [
      {
        id: "lg0",
        title: "Publisher",
        body: (
          <>
            <p>
              Horlynat Mampassi Mbama, individual, software engineer and architect, cybersecurity and risk management consultant. Brazzaville, Republic of
              the Congo. Contact:{" "}
              <a className="lnk" href={`mailto:${CONTACT_EMAIL}`}>
                {CONTACT_EMAIL}
              </a>
              , WhatsApp +242 06 642 90 90.
            </p>
            <p>Publication director: Horlynat Mampassi Mbama.</p>
          </>
        ),
      },
      {
        id: "lg1",
        title: "Personal activity and Digital Business Group",
        body: (
          <p>
            This site presents my <b>personal</b> activity. I am also founder and CEO of Digital Business Group, a separate company. The services and
            contracts offered here bind me personally, not Digital Business Group, unless stated otherwise in writing. Work carried out for Digital
            Business Group is presented with its agreement.
          </p>
        ),
      },
      {
        id: "lg2",
        title: "Hosting",
        body: (
          <p>
            LWS – Ligne Web Services SAS, 10 rue Penthièvre, 75008 Paris, France. Contact: www.lws.fr
          </p>
        ),
      },
      {
        id: "lg3",
        title: "Prices and content",
        body: (
          <p>
            Prices are shown in CFA francs (XAF) and are indicative starting prices. The timelines and examples shown, such as the WhatsApp assistant demo
            shop, are illustrative. Only the written proposal issued after a discussion is binding.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Intellectual property",
        body: (
          <p>
            The text, illustrations and presentation of this site are mine. Trademarks mentioned, such as WhatsApp, belong to their owners. The Montserrat
            typeface is used under the free SIL Open Font License.
          </p>
        ),
      },
      {
        id: "lg5",
        title: "Security",
        body: (
          <p>
            If you discover a flaw on this site, please report it to me privately at{" "}
            <a className="lnk" href={`mailto:${CONTACT_EMAIL}?subject=Security%20report`}>
              {CONTACT_EMAIL}
            </a>
            , without exploiting it or disclosing it publicly. I will reply quickly.
          </p>
        ),
      },
    ],
  },
};
