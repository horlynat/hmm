/**
 * Catégories "phares" mises en avant sur la home, dans l'ordre demandé par
 * le client — remplace l'heuristique générique "top N par nombre de
 * compétences" (ancien `maxCategories`) par une sélection éditoriale
 * explicite. Chaque slot liste les libellés acceptés pour cette catégorie
 * admin (fr + en — la home est bilingue, `Skill.skillCategory.name` est
 * déjà localisé par `getSkills()` selon la locale courante) ; un slot sans
 * aucune correspondance dans les compétences reçues n'apparaît simplement
 * pas, même logique de dégradation que le reste de `SkillsByCategory`
 * (catégorie sans compétence publiée = absente, pas d'erreur).
 *
 * Les libellés eux-mêmes restent du texte admin libre (App\Entity\SkillCategory
 * côté back) : cette liste doit être mise à jour si l'admin renomme l'une de
 * ces catégories, mais elle ne dérive plus automatiquement des données
 * (contrairement aux pages /a-propos et /competences, qui elles affichent
 * TOUTES les catégories reçues sans sélection ni ordre imposé).
 */
export const HOME_FEATURED_SKILL_CATEGORIES: string[][] = [
  ["Consultant en Cyber Sécurité", "Cybersecurity Consultant"],
  ["Consultant en Gestion des Risques", "Risk Management Consultant"],
  ["IA & Automatisations", "AI & Automation"],
  ["Développeur Web FullStack", "Full-Stack Web Developer"],
  ["Développement Mobile", "Mobile Development"],
  ["DevOps & Infrastructure"],
];
