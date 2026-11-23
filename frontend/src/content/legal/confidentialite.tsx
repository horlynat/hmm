import type { LegalSection } from "@/components/legal-proto/LegalPage";

export interface ConfidentialiteStrings {
  crumb: string;
  title: string;
  lede: string;
  updatedLabel: string;
  sections: LegalSection[];
}

const CONTACT_EMAIL = "contact@horlynat.com";

export const confidentialiteContent: Record<"fr" | "en", ConfidentialiteStrings> = {
  fr: {
    crumb: "Protection des données",
    title: "Protection des données personnelles",
    lede:
      "Ce que je fais de vos données, et de celles de vos clients lorsque je travaille pour vous. Cette politique applique la loi congolaise n° 29-2019 et, pour les personnes situées dans l’Union européenne, le RGPD.",
    updatedLabel: "Dernière mise à jour",
    sections: [
      {
        id: "lg0",
        title: "Deux rôles différents",
        body: (
          <>
            <p>Selon la situation, je n’ai pas les mêmes responsabilités :</p>
            <div className="ltab">
              <div>
                <b>Quand vous me contactez ou devenez client</b>
                <span>
                  Je suis <b>responsable du traitement</b> : je décide de l’usage de vos données, dans les limites décrites ici.
                </span>
              </div>
              <div>
                <b>Quand je traite les données de vos propres clients</b>
                <span>
                  Je suis votre <b>sous-traitant</b> : j’agis uniquement sur vos instructions, pour la prestation commandée. C’est le cas de l’assistant
                  WhatsApp, des applications que j’héberge ou que je maintiens pour vous, et des audits.
                </span>
              </div>
            </div>
          </>
        ),
      },
      {
        id: "lg1",
        title: "Qui je suis",
        body: (
          <p>
            Horlynat Mampassi Mbama, personne physique, exerçant à titre personnel à Brazzaville, République du Congo. Ce site et mes missions personnelles
            sont distincts de la société Digital Business Group, qui dispose de sa propre politique. Contact :{" "}
            <a className="lnk" href={`mailto:${CONTACT_EMAIL}?subject=Protection%20des%20donn%C3%A9es`}>
              {CONTACT_EMAIL}
            </a>
            .
          </p>
        ),
      },
      {
        id: "lg2",
        title: "Vos données de prospect et de client",
        body: (
          <div className="ltab">
            <div>
              <b>Formulaire de contact</b>
              <span>
                Nom, WhatsApp ou e-mail, besoin choisi, description du projet. Pour vous répondre et vous adresser une proposition. Base : votre
                consentement.
              </span>
            </div>
            <div>
              <b>Assistant du site</b>
              <span>
                Vos questions et ses réponses. Pour vous orienter vers la bonne offre. Base : votre consentement. Les conversations ne servent pas à vous
                profiler.
              </span>
            </div>
            <div>
              <b>WhatsApp et e-mail</b>
              <span>Nos échanges. Pour le suivi de votre demande. WhatsApp est un service de Meta, soumis à ses propres conditions.</span>
            </div>
            <div>
              <b>Proposition, contrat, factures</b>
              <span>Identité, coordonnées, contenu de la prestation, paiements. Base : le contrat et les obligations comptables.</span>
            </div>
            <div>
              <b>Espace clients et freelances</b>
              <span>Identifiants, profil, documents du projet, historique. Base : le contrat ou nos conditions d’utilisation.</span>
            </div>
            <div>
              <b>Sécurité du site</b>
              <span>Adresse IP, type d’appareil, journaux techniques. Base : intérêt légitime à protéger le site.</span>
            </div>
          </div>
        ),
      },
      {
        id: "lg3",
        title: "Les données que vous me confiez pour une prestation",
        body: (
          <>
            <p>Pour réaliser certaines missions, j’accède à des informations de votre organisation :</p>
            <ul className="chk" style={{ marginTop: "10px" }}>
              <li>
                <i>✓</i>
                <span>
                  <b>Assistant WhatsApp</b> : vos produits et prix, et les conversations de vos clients avec l’assistant.
                </span>
              </li>
              <li>
                <i>✓</i>
                <span>
                  <b>Audit de sécurité ou d’architecture</b> : accès à votre site, votre code ou votre documentation, et les failles découvertes.
                </span>
              </li>
              <li>
                <i>✓</i>
                <span>
                  <b>Diagnostic risques et assurances</b> : vos contrats, vos sinistres passés et la description de votre activité.
                </span>
              </li>
            </ul>
            <p>
              Je m’engage à n’utiliser ces informations que pour la mission, à ne jamais les réutiliser ni les revendre, à en limiter l’accès, et à les
              restituer ou les supprimer à la fin de la mission, selon votre choix. Pour l’assistant WhatsApp, vous restez responsable de l’information de
              vos propres clients ; je vous fournis un texte type à leur présenter. Un accord de sous-traitance peut être signé à votre demande.
            </p>
          </>
        ),
      },
      {
        id: "lg4",
        title: "Durées de conservation",
        body: (
          <div className="ltab">
            <div>
              <b>Demande sans suite</b>
              <span>
                <span className="todo">[12 mois]</span> après le dernier échange
              </span>
            </div>
            <div>
              <b>Dossier client</b>
              <span>
                Le temps de la mission et du suivi, puis <span className="todo">[durée]</span>
              </span>
            </div>
            <div>
              <b>Factures et pièces comptables</b>
              <span>La durée imposée par la loi</span>
            </div>
            <div>
              <b>Rapports d’audit et failles découvertes</b>
              <span>
                Remis à vous seul, puis supprimés de mes outils <span className="todo">[sous X jours]</span> après la mission
              </span>
            </div>
            <div>
              <b>Contrats d’assurance confiés</b>
              <span>Restitués ou supprimés à la fin du diagnostic</span>
            </div>
            <div>
              <b>Conversations de l’assistant du site</b>
              <span>
                <span className="todo">[durée]</span>
              </span>
            </div>
          </div>
        ),
      },
      {
        id: "lg5",
        title: "Qui y a accès",
        body: (
          <p>
            Moi seul. Lorsqu’un freelance participe à votre projet, il n’accède qu’aux éléments nécessaires à sa tâche, sous engagement écrit de
            confidentialité, et vous en êtes informé à l’avance. Prestataires techniques : <span className="todo">[hébergeur du site]</span>,{" "}
            <span className="todo">[fournisseur du service d’IA]</span>, Meta (WhatsApp). Vos données ne sont jamais vendues ni utilisées pour de la
            publicité.
          </p>
        ),
      },
      {
        id: "lg6",
        title: "Données hors du Congo",
        body: (
          <p>
            L’hébergement, le service d’IA et WhatsApp peuvent traiter des données à l’étranger. Je choisis des prestataires offrant des garanties de
            protection, comme les clauses contractuelles types de la Commission européenne pour les personnes situées dans l’Union.{" "}
            <span className="todo">[Préciser le pays et la garantie de chaque prestataire]</span>
          </p>
        ),
      },
      {
        id: "lg7",
        title: "Intelligence artificielle",
        body: (
          <p>
            L’assistant du site et les assistants WhatsApp reposent sur un service d’IA externe. Je le configure pour que vos données ne servent pas à
            entraîner ses modèles, lorsque le service le permet. <span className="todo">[À confirmer selon le fournisseur retenu]</span> Aucune décision
            vous concernant n’est prise automatiquement par l’IA.
          </p>
        ),
      },
      {
        id: "lg8",
        title: "Incident de sécurité",
        body: (
          <p>
            En cas de violation de données présentant un risque, je vous informe dans les meilleurs délais, et je préviens l’autorité compétente lorsque la
            loi l’exige. Si l’incident touche des données que je traite pour vous, je vous alerte sans délai pour que vous puissiez informer vos propres
            clients.
          </p>
        ),
      },
      {
        id: "lg9",
        title: "Vos droits",
        body: (
          <>
            <p>
              Accès, correction, suppression, limitation, opposition, portabilité et retrait du consentement. Écrivez à{" "}
              <a className="lnk" href={`mailto:${CONTACT_EMAIL}?subject=Protection%20des%20donn%C3%A9es`}>
                {CONTACT_EMAIL}
              </a>{" "}
              ; je réponds sous un mois au plus. Si vous êtes client d’une entreprise qui utilise un assistant que j’ai mis en place, adressez-vous d’abord
              à elle : je l’aide à vous répondre.
            </p>
            <p>
              Vous pouvez saisir la Commission nationale pour la protection des données à caractère personnel (Congo) ou, dans l’Union européenne,
              l’autorité de votre pays.
            </p>
            <p>
              <a className="b b-g sm" href={`mailto:${CONTACT_EMAIL}?subject=Exercice%20de%20mes%20droits`}>
                Exercer mes droits
              </a>
            </p>
          </>
        ),
      },
    ],
  },
  en: {
    crumb: "Data protection",
    title: "Personal data protection",
    lede:
      "What I do with your data, and with your own clients' data when I work for you. This policy applies Congolese law no. 29-2019 and, for people located in the European Union, the GDPR.",
    updatedLabel: "Last updated",
    sections: [
      {
        id: "lg0",
        title: "Two different roles",
        body: (
          <>
            <p>Depending on the situation, I do not have the same responsibilities:</p>
            <div className="ltab">
              <div>
                <b>When you contact me or become a client</b>
                <span>
                  I am the <b>data controller</b>: I decide how your data is used, within the limits described here.
                </span>
              </div>
              <div>
                <b>When I process your own customers’ data</b>
                <span>
                  I am your <b>processor</b>: I act only on your instructions, for the service ordered. This applies to the WhatsApp assistant, the
                  applications I host or maintain for you, and audits.
                </span>
              </div>
            </div>
          </>
        ),
      },
      {
        id: "lg1",
        title: "Who I am",
        body: (
          <p>
            Horlynat Mampassi Mbama, individual, operating in a personal capacity in Brazzaville, Republic of the Congo. This site and my personal missions
            are separate from Digital Business Group, which has its own policy. Contact:{" "}
            <a className="lnk" href={`mailto:${CONTACT_EMAIL}?subject=Data%20protection`}>
              {CONTACT_EMAIL}
            </a>
            .
          </p>
        ),
      },
      {
        id: "lg2",
        title: "Your data as a prospect or client",
        body: (
          <div className="ltab">
            <div>
              <b>Contact form</b>
              <span>Name, WhatsApp or email, chosen need, project description. To reply to you and send you a proposal. Basis: your consent.</span>
            </div>
            <div>
              <b>Site assistant</b>
              <span>Your questions and its answers. To direct you to the right service. Basis: your consent. Conversations are not used to profile you.</span>
            </div>
            <div>
              <b>WhatsApp and email</b>
              <span>Our exchanges. To follow up on your request. WhatsApp is a Meta service, subject to its own terms.</span>
            </div>
            <div>
              <b>Proposal, contract, invoices</b>
              <span>Identity, contact details, scope of the service, payments. Basis: the contract and accounting obligations.</span>
            </div>
            <div>
              <b>Client and freelance area</b>
              <span>Credentials, profile, project documents, history. Basis: the contract or our terms of use.</span>
            </div>
            <div>
              <b>Site security</b>
              <span>IP address, device type, technical logs. Basis: legitimate interest in protecting the site.</span>
            </div>
          </div>
        ),
      },
      {
        id: "lg3",
        title: "The data you entrust to me for a mission",
        body: (
          <>
            <p>To carry out certain missions, I access information belonging to your organisation:</p>
            <ul className="chk" style={{ marginTop: "10px" }}>
              <li>
                <i>✓</i>
                <span>
                  <b>WhatsApp assistant</b>: your products and prices, and your customers’ conversations with the assistant.
                </span>
              </li>
              <li>
                <i>✓</i>
                <span>
                  <b>Security or architecture audit</b>: access to your site, your code or your documentation, and any flaws found.
                </span>
              </li>
              <li>
                <i>✓</i>
                <span>
                  <b>Risk and insurance diagnostic</b>: your contracts, your past claims and a description of your business.
                </span>
              </li>
            </ul>
            <p>
              I commit to using this information only for the mission, never reusing or reselling it, limiting access to it, and returning or deleting it
              at the end of the mission, as you choose. For the WhatsApp assistant, you remain responsible for informing your own customers; I provide you
              with a template text to present to them. A data processing agreement can be signed at your request.
            </p>
          </>
        ),
      },
      {
        id: "lg4",
        title: "Retention periods",
        body: (
          <div className="ltab">
            <div>
              <b>Request with no follow-up</b>
              <span>
                <span className="todo">[12 months]</span> after the last exchange
              </span>
            </div>
            <div>
              <b>Client file</b>
              <span>
                The duration of the mission and follow-up, then <span className="todo">[duration]</span>
              </span>
            </div>
            <div>
              <b>Invoices and accounting records</b>
              <span>The period required by law</span>
            </div>
            <div>
              <b>Audit reports and flaws found</b>
              <span>
                Given to you alone, then deleted from my tools <span className="todo">[within X days]</span> after the mission
              </span>
            </div>
            <div>
              <b>Insurance contracts entrusted to me</b>
              <span>Returned or deleted at the end of the diagnostic</span>
            </div>
            <div>
              <b>Site assistant conversations</b>
              <span>
                <span className="todo">[duration]</span>
              </span>
            </div>
          </div>
        ),
      },
      {
        id: "lg5",
        title: "Who has access",
        body: (
          <p>
            Me alone. When a freelancer works on your project, they access only what their task requires, under a written confidentiality commitment, and
            you are informed in advance. Technical providers: <span className="todo">[site host]</span>,{" "}
            <span className="todo">[AI service provider]</span>, Meta (WhatsApp). Your data is never sold or used for advertising.
          </p>
        ),
      },
      {
        id: "lg6",
        title: "Data outside the Congo",
        body: (
          <p>
            Hosting, the AI service and WhatsApp may process data abroad. I choose providers offering protection guarantees, such as the European
            Commission’s standard contractual clauses for people located in the Union.{" "}
            <span className="todo">[Specify the country and the guarantee of each provider]</span>
          </p>
        ),
      },
      {
        id: "lg7",
        title: "Artificial intelligence",
        body: (
          <p>
            The site assistant and the WhatsApp assistants rely on an external AI service. I configure it so your data is not used to train its models,
            where the service allows it. <span className="todo">[To be confirmed depending on the provider chosen]</span> No decision concerning you is
            made automatically by the AI.
          </p>
        ),
      },
      {
        id: "lg8",
        title: "Security incident",
        body: (
          <p>
            In the event of a data breach presenting a risk, I inform you as soon as possible, and notify the competent authority where the law requires
            it. If the incident affects data I process for you, I alert you without delay so you can inform your own customers.
          </p>
        ),
      },
      {
        id: "lg9",
        title: "Your rights",
        body: (
          <>
            <p>
              Access, correction, deletion, restriction, objection, portability and withdrawal of consent. Write to{" "}
              <a className="lnk" href={`mailto:${CONTACT_EMAIL}?subject=Data%20protection`}>
                {CONTACT_EMAIL}
              </a>{" "}
              ; I reply within one month at the latest. If you are a customer of a business using an assistant I have set up, contact that business first:
              I help it answer you.
            </p>
            <p>
              You may contact the national commission for the protection of personal data (Congo) or, in the European Union, the authority of your
              country.
            </p>
            <p>
              <a className="b b-g sm" href={`mailto:${CONTACT_EMAIL}?subject=Exercising%20my%20rights`}>
                Exercise my rights
              </a>
            </p>
          </>
        ),
      },
    ],
  },
};
