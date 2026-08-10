import type { ReactNode } from "react";
import { Badge, Reveal } from "@/components/ui";
import { BookIcon, GraduationCapIcon, RibbonIcon } from "./AboutIcons";
import { formatMonthYear } from "@/lib/date";
import type { Course, CourseType } from "@/lib/types";

const TYPE_ORDER: CourseType[] = ["diplome", "certification", "formation"];

const TYPE_ICON: Record<CourseType, ReactNode> = {
  diplome: <GraduationCapIcon />,
  certification: <RibbonIcon />,
  formation: <BookIcon />,
};

interface QualificationsListProps {
  courses: Course[];
  /** Libellés déjà traduits par la page appelante (cf. Timeline, même convention). */
  labels: Record<CourseType, string>;
}

/**
 * Remplace `Timeline` pour "Qualifications et certifications" : regroupe par
 * type (App\Enum\CourseTypeEnum côté back) au lieu d'une liste à plat, avec
 * icône dédiée et plage de dates (MM/YYYY) par élément — un type sans aucune
 * formation n'affiche simplement pas de groupe. `Course::$endDate` est
 * toujours renseigné côté back (contrairement à Experience) : pas de repli
 * "en cours" nécessaire ici.
 */
export function QualificationsList({ courses, labels }: QualificationsListProps) {
  let i = 0;

  return (
    <div className="space-y-6">
      {TYPE_ORDER.map((type) => {
        const items = courses
          .filter((course) => course.type === type)
          .slice()
          .sort((a, b) => b.startDate.localeCompare(a.startDate));

        if (0 === items.length) return null;

        return (
          <div key={type}>
            <Badge variant="outline" className="mb-3">
              {labels[type]}
            </Badge>
            <div className="space-y-3">
              {items.map((course) => (
                <Reveal key={course.id} delay={i++ * 0.06}>
                  <div className="card flex gap-3 p-4">
                    <span
                      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-brand-primary"
                      aria-hidden="true"
                    >
                      {TYPE_ICON[type]}
                    </span>
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-baseline gap-x-2">
                        <h4
                          className="text-sm font-semibold"
                          style={{ fontFamily: "var(--font-heading)" }}
                        >
                          {course.title}
                        </h4>
                        <span className="font-mono text-xs opacity-60">
                          {formatMonthYear(course.startDate)} – {formatMonthYear(course.endDate)}
                        </span>
                      </div>
                      <p className="mb-1.5 text-xs opacity-70">{course.institution}</p>
                      <p className="text-sm opacity-80">{course.description}</p>
                    </div>
                  </div>
                </Reveal>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}
