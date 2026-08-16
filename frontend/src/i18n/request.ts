import { getRequestConfig } from "next-intl/server";
import { hasLocale } from "next-intl";
import { routing } from "./routing";
import fr from "../messages/fr.json";
import en from "../messages/en.json";

/**
 * Imports statiques plutôt que `import(\`../messages/${locale}.json\`)` :
 * un chemin dynamique construit à l'exécution est beaucoup plus fragile à
 * suivre pour le rechargement à chaud de Turbopack — une clé fraîchement
 * ajoutée pouvait transitoirement ne pas être vue juste après un
 * redémarrage à froid (MISSING_MESSAGE), le temps que le graphe de
 * dépendances se stabilise. Un import statique par locale est suivi de
 * façon fiable et déterministe, à froid comme à chaud. Seulement 2 locales
 * (routing.locales) : aucun downside à les lister explicitement plutôt que
 * de les résoudre dynamiquement.
 */
const MESSAGES_BY_LOCALE = { fr, en } as const;

export default getRequestConfig(async ({ requestLocale }) => {
  const requested = await requestLocale;
  const locale = hasLocale(routing.locales, requested)
    ? requested
    : routing.defaultLocale;

  return {
    locale,
    messages: MESSAGES_BY_LOCALE[locale],
  };
});
