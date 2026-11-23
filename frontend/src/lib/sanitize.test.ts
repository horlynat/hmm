// @vitest-environment node
import { describe, expect, it } from "vitest";
import { getArticleExcerpt, getReadingTimeMinutes, sanitizeArticleHtml } from "./sanitize";

describe("I1 — sanitizeArticleHtml class allowlist", () => {
  it("keeps editor classes", () => {
    expect(sanitizeArticleHtml('<p class="ql-align-center">x</p>')).toBe('<p class="ql-align-center">x</p>');
    expect(sanitizeArticleHtml('<li class="ql-indent-2">x</li>')).toContain('class="ql-indent-2"');
  });

  it("drops arbitrary (e.g. Tailwind overlay) classes", () => {
    const out = sanitizeArticleHtml('<a href="https://x.test" class="fixed inset-0 z-50 ql-align-right">click</a>');
    expect(out).not.toMatch(/fixed|inset-0|z-50/);
    expect(out).toContain("ql-align-right");
    expect(sanitizeArticleHtml('<div class="fixed inset-0"><p class="sr-only">x</p></div>')).not.toContain("class=");
  });

  it("still strips scripts, handlers and javascript: URLs", () => {
    const out = sanitizeArticleHtml('<img src="x" onerror="alert(1)"><a href="javascript:alert(1)">a</a><script>alert(1)</script><p style="position:fixed">p</p>');
    expect(out).not.toMatch(/onerror|javascript:|<script|style=/);
  });
});

describe("getArticleExcerpt / getReadingTimeMinutes — adjacent block elements", () => {
  // HTML valide (pas besoin d'espace entre deux balises de bloc) que
  // l'éditeur riche produit couramment : sans correction, le texte obtenu
  // collait la fin d'un bloc au début du suivant ("constat.Automatiser").
  const html = "<h2>Le constat</h2><p>Publier, mettre à jour.</p><h2>Suite</h2><p>Autre paragraphe.</p>";

  it("inserts a space between adjacent block elements rather than gluing them", () => {
    const excerpt = getArticleExcerpt(html, 200);
    expect(excerpt).toBe("Le constat Publier, mettre à jour. Suite Autre paragraphe.");
    expect(excerpt).not.toMatch(/constatPublier|jour\.Suite/);
  });

  it("counts words correctly across adjacent block elements", () => {
    // "glued" aurait compté un seul mot géant ("constatPublier...") à la place de deux.
    expect(getReadingTimeMinutes(html, 1)).toBeGreaterThanOrEqual(2);
  });
});
