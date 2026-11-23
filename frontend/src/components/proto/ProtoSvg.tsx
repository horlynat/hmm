import {
  PROTO_ICONS,
  PROTO_ID_BADGE,
  PROTO_OFFER_VISUALS,
  PROTO_PROJECT_ART,
  PROTO_SILHOUETTE,
  PROTO_TRUST_ICONS,
  type ProtoIconName,
  type ProtoOfferVisual,
} from "./assets";

/**
 * Injecte une chaîne SVG STATIQUE du prototype (cf. assets.ts). Le contenu
 * est une constante du code source : jamais de donnée utilisateur ni de
 * back-office — c'est ce qui rend l'injection sûre. Le type `markup` n'accepte
 * donc que des clés connues via les composants ci-dessous, pas une chaîne libre.
 */
function Raw({ markup }: { markup: string }) {
  // Rend le <svg> lui-même (pas d'élément enveloppe) : le CSS du prototype
  // cible parfois l'icône en enfant direct (`.byr>svg`, `.dwa>svg`…) ou
  // compte les enfants (`:last-child`). Seul le contenu interne est injecté.
  const m = /^\s*<svg([^>]*)>([\s\S]*)<\/svg>\s*$/.exec(markup);
  if (!m) return null;
  return <svg {...svgAttributes(m[1])} dangerouslySetInnerHTML={{ __html: m[2] }} />;
}

/** Attributs de la balise <svg> d'origine, convertis en props React. */
function svgAttributes(raw: string): Record<string, string> {
  const props: Record<string, string> = {};
  for (const [, name, value] of raw.matchAll(/([a-zA-Z:-]+)="([^"]*)"/g)) {
    const key = name === "class" ? "className" : name.startsWith("aria-") || name.startsWith("data-") ? name : name.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase());
    props[key] = value;
  }
  return props;
}

/** Icône du prototype, redimensionnée (18 px d'origine). */
export function ProtoIcon({ name, size }: { name: ProtoIconName; size?: number }) {
  const base = PROTO_ICONS[name];
  const markup = size ? base.replace(/width="\d+" height="\d+"/, `width="${size}" height="${size}"`) : base;
  return <Raw markup={markup} />;
}

/** Icône du bandeau de garanties, par position (0 à 4). */
export function TrustIcon({ index }: { index: number }) {
  const markup = PROTO_TRUST_ICONS[index];
  return markup ? <Raw markup={markup} /> : null;
}

/**
 * Correspondance offre -> illustration : d'abord par slug (illustrations du
 * prototype), sinon par type d'aperçu saisi au back-office, sinon tableau de
 * bord générique. Une offre ajoutée plus tard a donc toujours un visuel.
 */
const BY_PREVIEW: Record<string, ProtoOfferVisual> = {
  chat: "whatsapp",
  screens: "site",
  report: "audit",
  flow: "automatisation",
  roadmap: "architecture",
  risk: "risques",
};

export function offerVisualKey(slug: string, previewType: string): ProtoOfferVisual {
  if (Object.hasOwn(PROTO_OFFER_VISUALS, slug)) return slug as ProtoOfferVisual;
  return BY_PREVIEW[previewType] ?? "application";
}

export function OfferVisual({ slug, previewType }: { slug: string; previewType: string }) {
  return <Raw markup={PROTO_OFFER_VISUALS[offerVisualKey(slug, previewType)]} />;
}

/** Médaillon « photo » du bloc d'identité du hero. */
export function IdBadgeArt() {
  return <Raw markup={PROTO_ID_BADGE} />;
}

/** Silhouette d'attente du portrait (tant qu'aucune photo n'est fournie). */
export function SilhouetteArt() {
  return <Raw markup={PROTO_SILHOUETTE} />;
}

/** Illustration de réalisation du prototype (repli sans image de couverture). */
export function ProjectArt({ name }: { name: keyof typeof PROTO_PROJECT_ART }) {
  return <Raw markup={PROTO_PROJECT_ART[name]} />;
}
