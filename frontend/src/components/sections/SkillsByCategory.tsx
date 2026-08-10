import { Badge, Reveal } from "@/components/ui";
import { SkillChip } from "./SkillChip";
import type { Skill } from "@/lib/types";

interface CategoryGroup {
  id: number;
  name: string;
  skills: Skill[];
}

function groupByCategory(skills: Skill[]): CategoryGroup[] {
  const groups = new Map<number, CategoryGroup>();

  for (const skill of skills) {
    const { id, name } = skill.skillCategory;
    const group = groups.get(id) ?? { id, name, skills: [] };
    group.skills.push(skill);
    groups.set(id, group);
  }

  return Array.from(groups.values())
    .map((group) => ({ ...group, skills: group.skills.slice().sort((a, b) => b.level - a.level) }))
    .sort((a, b) => b.skills.length - a.skills.length || a.name.localeCompare(b.name));
}

interface SkillsByCategoryProps {
  skills: Skill[];
  /** Tronque chaque carte aux `n` compétences les mieux notées — pour un aperçu condensé (home). */
  maxSkillsPerCategory?: number;
  /**
   * Limite aux `n` catégories comptant le plus de compétences — pour
   * l'aperçu condensé de la home ("catégories phares"). Dérivé des données
   * reçues (pas de nom de catégorie codé en dur, qui casserait dès que
   * l'admin renomme ou réorganise ses catégories).
   */
  maxCategories?: number;
}

/**
 * Regroupe les compétences par catégorie (App\Entity\SkillCategory côté
 * back) plutôt qu'une liste à plat — une catégorie sans compétence publiée
 * n'apparaît simplement pas (dérivé des `skills` reçus, pas d'appel séparé
 * à `getSkillCategories()`).
 */
export function SkillsByCategory({ skills, maxSkillsPerCategory, maxCategories }: SkillsByCategoryProps) {
  const grouped = groupByCategory(skills);
  const categories = maxCategories ? grouped.slice(0, maxCategories) : grouped;

  return (
    <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
      {categories.map((category, i) => {
        const shown = maxSkillsPerCategory
          ? category.skills.slice(0, maxSkillsPerCategory)
          : category.skills;

        return (
          <Reveal key={category.id} delay={i * 0.06}>
            <div className="card h-full p-5">
              <div className="mb-4 flex items-center justify-between gap-2">
                <h3 className="text-sm font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {category.name}
                </h3>
                <Badge variant="neutral">{category.skills.length}</Badge>
              </div>
              <div className="space-y-2">
                {shown.map((skill) => (
                  <SkillChip key={skill.id} skill={skill} />
                ))}
              </div>
            </div>
          </Reveal>
        );
      })}
    </div>
  );
}
