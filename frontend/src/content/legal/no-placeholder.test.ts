import { describe, expect, it } from "vitest";
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";

/**
 * Garde-fou : aucune mention provisoire du prototype (`<span className="todo">`,
 * crochets jaunes « [à définir] ») ne doit être publiée. Les pages légales et
 * la page Contact en contenaient ; toutes ont reçu leur valeur réelle.
 */
function sourceFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) return sourceFiles(path);
    return /\.(tsx?|json)$/.test(name) && !/\.test\.tsx?$/.test(name) ? [path] : [];
  });
}

describe("mentions provisoires", () => {
  it("aucun bloc « todo » dans le code publié", () => {
    const offenders = sourceFiles(join(process.cwd(), "src")).filter((f) => /className=["']todo["']/.test(readFileSync(f, "utf8")));
    expect(offenders).toEqual([]);
  });

  it("aucun crochet provisoire dans les pages légales ni les traductions", () => {
    const files = [...sourceFiles(join(process.cwd(), "src/content/legal")), join(process.cwd(), "src/messages/fr.json"), join(process.cwd(), "src/messages/en.json")];
    const pattern = /\[(?:X ?%|X (?:jours|days|mois|months)|durée|duration|date|jours et heures|days and hours|à définir|to be defined|À confirmer|To be confirmed)[^\]]*\]/i;
    const offenders = files.filter((f) => pattern.test(readFileSync(f, "utf8")));
    expect(offenders).toEqual([]);
  });
});
