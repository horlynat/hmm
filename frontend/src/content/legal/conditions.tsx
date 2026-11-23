import type { LegalSection } from "@/components/legal-proto/LegalPage";

export interface ConditionsStrings {
  crumb: string;
  title: string;
  lede: string;
  updatedLabel: string;
  sections: LegalSection[];
}

export const conditionsContent: Record<"fr" | "en", ConditionsStrings> = {
  fr: {
    crumb: "Conditions de prestation",
    title: "Conditions générales de prestation",
    lede: "Les règles qui s’appliquent lorsque vous me confiez une mission : communes à toutes les offres, puis propres à chacune.",
    updatedLabel: "Dernière mise à jour",
    sections: [
      {
        id: "lg0",
        title: "Commande",
        body: (
          <p>
            Chaque mission fait l’objet d’une proposition écrite : contenu, livrables, prix, délai et modalités de paiement. La commande est ferme après
            votre accord écrit (un message WhatsApp ou un e-mail suffit) et le versement de l’acompte. La proposition prévaut sur les prix affichés sur le
            site.
          </p>
        ),
      },
      {
        id: "lg1",
        title: "Prix et paiement",
        body: (
          <p>
            Prix en francs CFA (XAF). Acompte de <span className="todo">[X %]</span> à la commande, solde à la livraison. Paiement par Mobile Money (MTN,
            Airtel) ou virement bancaire. <span className="todo">[Préciser vos numéros de paiement et les pénalités de retard éventuelles]</span> Les frais
            de service externes (hébergement, abonnement WhatsApp Business, service d’IA) sont indiqués à part dans la proposition et restent à votre
            charge, sauf mention contraire.
          </p>
        ),
      },
      {
        id: "lg2",
        title: "Délais",
        body: (
          <p>
            Le délai court à partir de la réception de l’acompte et des éléments demandés dans la rubrique « Ce dont j’ai besoin de vous » de chaque offre.
            Un retard dans la remise de ces éléments décale d’autant la livraison.
          </p>
        ),
      },
      {
        id: "lg3",
        title: "Livraison et validation",
        body: (
          <p>
            Je vous livre une version à tester. Vous disposez de <span className="todo">[X jours]</span> pour signaler ce qui ne correspond pas à la
            proposition ; je le corrige sans frais. Sans retour dans ce délai, la livraison est considérée comme acceptée. Les demandes nouvelles font
            l’objet d’un devis complémentaire.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Propriété du travail livré",
        body: (
          <p>
            Après paiement complet, vous devenez propriétaire de ce qui a été créé spécialement pour vous : contenus, design, code spécifique. Je conserve
            mes outils, méthodes et composants réutilisables, sur lesquels vous recevez un droit d’utilisation illimité pour votre projet. Je peux citer la
            mission dans mes réalisations, sans donnée confidentielle, sauf si vous vous y opposez.
          </p>
        ),
      },
      {
        id: "lg5",
        title: "Garantie et suivi",
        body: (
          <p>
            Les anomalies signalées pendant <span className="todo">[X mois]</span> après la livraison sont corrigées gratuitement. La maintenance, les
            mises à jour et l’hébergement au-delà de cette période font l’objet d’un contrat de suivi distinct.
          </p>
        ),
      },
      {
        id: "lg6",
        title: "Assistant IA WhatsApp",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>Vous fournissez un numéro WhatsApp dédié et un compte WhatsApp Business ; l’usage reste soumis aux règles de Meta, qui peut suspendre
              un compte qui les enfreint.
            </li>
            <li>
              <i>✓</i>L’assistant répond à partir des informations que vous lui donnez : vous restez responsable de l’exactitude de vos prix, stocks et
              conditions de vente.
            </li>
            <li>
              <i>✓</i>Une IA peut se tromper. Les demandes importantes sont transmises à un responsable que vous désignez.
            </li>
            <li>
              <i>✓</i>Je traite les conversations de vos clients en tant que sous-traitant, selon la politique de protection des données.
            </li>
          </ul>
        ),
      },
      {
        id: "lg7",
        title: "Audit de sécurité",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>Aucun test n’est réalisé sans votre <b>autorisation écrite</b>, qui fixe le périmètre (adresses, systèmes), les dates et les méthodes.
              Vous garantissez être habilité à donner cette autorisation.
            </li>
            <li>
              <i>✓</i>Les tests restent dans ce périmètre et sont conduits de façon à limiter tout risque d’interruption. Une sauvegarde préalable de votre
              côté est recommandée.
            </li>
            <li>
              <i>✓</i>Les failles découvertes sont strictement confidentielles et remises à vous seul.
            </li>
            <li>
              <i>✓</i>Un audit réduit les risques sans pouvoir les supprimer : il ne garantit pas l’absence de toute faille, ni contre des attaques futures.
            </li>
          </ul>
        ),
      },
      {
        id: "lg8",
        title: "Audit d’architecture",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>L’accès à votre code et à votre documentation est utilisé uniquement pour l’audit et retiré à la fin de la mission.
            </li>
            <li>
              <i>✓</i>Le rapport donne des recommandations ; leur mise en œuvre fait l’objet d’une mission distincte si vous le souhaitez.
            </li>
          </ul>
        ),
      },
      {
        id: "lg9",
        title: "Diagnostic risques et assurances",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>Il s’agit d’une analyse indépendante de vos risques et de vos contrats, fondée sur mon expérience de l’assurance.
            </li>
            <li>
              <i>✓</i>Je ne vends pas d’assurance et ne perçois aucune commission d’assureur. <span className="todo">[À confirmer]</span>
            </li>
            <li>
              <i>✓</i>Le diagnostic n’est ni une consultation juridique, ni une garantie d’indemnisation : seul votre assureur décide de la prise en charge
              d’un sinistre, selon votre contrat.
            </li>
            <li>
              <i>✓</i>Vos contrats sont restitués ou supprimés à la fin du diagnostic.
            </li>
          </ul>
        ),
      },
      {
        id: "lg10",
        title: "Confidentialité",
        body: (
          <p>
            Tout ce que vous me confiez sur votre activité reste confidentiel, pendant la mission et après. Un accord de confidentialité peut être signé
            avant même le premier échange détaillé.
          </p>
        ),
      },
      {
        id: "lg11",
        title: "Responsabilité",
        body: (
          <p>
            Je m’engage à réaliser chaque mission avec soin, selon les règles de l’art. Ma responsabilité est limitée aux dommages directs et au montant de
            la mission concernée, sauf faute grave. <span className="todo">[À faire valider par un juriste]</span>
          </p>
        ),
      },
      {
        id: "lg12",
        title: "Annulation et litiges",
        body: (
          <p>
            En cas d’annulation de votre part après la commande, l’acompte reste acquis à hauteur du travail réalisé. En cas de désaccord, nous cherchons
            d’abord une solution amiable.{" "}
            <span className="todo">[Droit applicable et tribunal compétent, par exemple ceux de Brazzaville, à faire valider par un juriste]</span>
          </p>
        ),
      },
    ],
  },
  en: {
    crumb: "Service terms",
    title: "General terms of service",
    lede: "The rules that apply when you entrust me with a mission: common to all services, then specific to each.",
    updatedLabel: "Last updated",
    sections: [
      {
        id: "lg0",
        title: "Order",
        body: (
          <p>
            Each mission is covered by a written proposal: scope, deliverables, price, timeline and payment terms. The order is firm once you accept in
            writing (a WhatsApp message or an email is enough) and pay the deposit. The proposal takes precedence over the prices shown on the site.
          </p>
        ),
      },
      {
        id: "lg1",
        title: "Price and payment",
        body: (
          <p>
            Prices in CFA francs (XAF). Deposit of <span className="todo">[X%]</span> on order, balance on delivery. Payment by Mobile Money (MTN, Airtel)
            or bank transfer. <span className="todo">[Specify your payment numbers and any late-payment penalties]</span> External service fees (hosting,
            WhatsApp Business subscription, AI service) are listed separately in the proposal and remain your responsibility, unless stated otherwise.
          </p>
        ),
      },
      {
        id: "lg2",
        title: "Timelines",
        body: (
          <p>
            The timeline starts once the deposit and the items requested under “What I need from you” for each service are received. A delay in providing
            these items pushes the delivery back by the same amount.
          </p>
        ),
      },
      {
        id: "lg3",
        title: "Delivery and acceptance",
        body: (
          <p>
            I deliver you a version to test. You have <span className="todo">[X days]</span> to report anything that does not match the proposal; I fix it
            at no charge. With no response within this period, delivery is considered accepted. New requests are quoted separately.
          </p>
        ),
      },
      {
        id: "lg4",
        title: "Ownership of the delivered work",
        body: (
          <p>
            After full payment, you own what was created specifically for you: content, design, bespoke code. I keep my own tools, methods and reusable
            components, for which you receive an unlimited right of use for your project. I may mention the mission among my work, with no confidential
            data, unless you object.
          </p>
        ),
      },
      {
        id: "lg5",
        title: "Warranty and follow-up",
        body: (
          <p>
            Issues reported within <span className="todo">[X months]</span> of delivery are fixed free of charge. Maintenance, updates and hosting beyond
            this period are covered by a separate follow-up contract.
          </p>
        ),
      },
      {
        id: "lg6",
        title: "WhatsApp AI assistant",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>You provide a dedicated WhatsApp number and a WhatsApp Business account; use remains subject to Meta’s rules, which may suspend an
              account that breaches them.
            </li>
            <li>
              <i>✓</i>The assistant answers based on the information you give it: you remain responsible for the accuracy of your prices, stock and sales
              terms.
            </li>
            <li>
              <i>✓</i>An AI can make mistakes. Important requests are forwarded to a contact you designate.
            </li>
            <li>
              <i>✓</i>I process your customers’ conversations as a processor, in accordance with the data protection policy.
            </li>
          </ul>
        ),
      },
      {
        id: "lg7",
        title: "Security audit",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>No test is carried out without your <b>written authorization</b>, which sets the scope (addresses, systems), dates and methods. You
              warrant that you are entitled to give this authorization.
            </li>
            <li>
              <i>✓</i>Tests stay within this scope and are conducted to limit any risk of disruption. A prior backup on your side is recommended.
            </li>
            <li>
              <i>✓</i>Flaws found are strictly confidential and given to you alone.
            </li>
            <li>
              <i>✓</i>An audit reduces risk but cannot remove it: it does not guarantee the absence of every flaw, nor protection against future attacks.
            </li>
          </ul>
        ),
      },
      {
        id: "lg8",
        title: "Architecture audit",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>Access to your code and documentation is used only for the audit and withdrawn at the end of the mission.
            </li>
            <li>
              <i>✓</i>The report provides recommendations; implementing them is a separate mission if you wish.
            </li>
          </ul>
        ),
      },
      {
        id: "lg9",
        title: "Risk and insurance diagnostic",
        body: (
          <ul className="chk" style={{ marginTop: "0" }}>
            <li>
              <i>✓</i>This is an independent analysis of your risks and contracts, based on my experience in insurance.
            </li>
            <li>
              <i>✓</i>I do not sell insurance and receive no commission from any insurer. <span className="todo">[To be confirmed]</span>
            </li>
            <li>
              <i>✓</i>The diagnostic is neither legal advice nor a guarantee of compensation: only your insurer decides whether a claim is covered, under
              your contract.
            </li>
            <li>
              <i>✓</i>Your contracts are returned or deleted at the end of the diagnostic.
            </li>
          </ul>
        ),
      },
      {
        id: "lg10",
        title: "Confidentiality",
        body: (
          <p>
            Everything you share with me about your business stays confidential, during the mission and afterwards. A confidentiality agreement can be
            signed before the first detailed discussion.
          </p>
        ),
      },
      {
        id: "lg11",
        title: "Liability",
        body: (
          <p>
            I commit to carrying out each mission carefully, in line with professional standards. My liability is limited to direct damages and to the
            amount of the relevant mission, except in case of gross negligence. <span className="todo">[To be validated by a lawyer]</span>
          </p>
        ),
      },
      {
        id: "lg12",
        title: "Cancellation and disputes",
        body: (
          <p>
            If you cancel after placing an order, the deposit is retained in proportion to the work carried out. In case of disagreement, we first seek an
            amicable solution.{" "}
            <span className="todo">[Applicable law and competent court, for example those of Brazzaville, to be validated by a lawyer]</span>
          </p>
        ),
      },
    ],
  },
};
