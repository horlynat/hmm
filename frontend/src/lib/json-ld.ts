/**
 * `JSON.stringify` n'échappe jamais `<` : un champ admin (titre d'article,
 * nom de techno, accroche du hero...) contenant la sous-chaîne `</script>`
 * refermerait prématurément la balise et laisserait le reste s'exécuter
 * comme du HTML/JS réel, pour tous les visiteurs de la page — un tokenizer
 * HTML termine un élément `<script>` sur la séquence littérale `</script`,
 * indépendamment du contexte JS/JSON. `<` neutralise ce risque.
 */
export function jsonLdScript(data: unknown): string {
  return JSON.stringify(data).replace(/</g, "\\u003c");
}
