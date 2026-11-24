import type { TypeKey } from "@/lib/quoteWizard/constants";
import type { ProtoIconName } from "@/components/proto/assets";

/**
 * Icône du prototype par type de prestation (prototype `QT`), pour les
 * tuiles de l'étape 1 du questionnaire — distincte de `TYPE_ICONS` (émojis)
 * utilisée par le questionnaire de l'espace compte. `lib/quoteWizard` reste
 * indépendant des composants : cette correspondance vit ici, pas là-bas.
 */
export const TYPE_ICONS_PROTO: Record<TypeKey, ProtoIconName> = {
  typeWeb: "web",
  typeMobile: "app",
  typeIa: "spark",
  typeCyber: "lock",
  typeAssurance: "umb",
  typeDesign: "pen",
  typeOther: "help",
};
