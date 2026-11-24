import { describe, expect, it, vi } from "vitest";
import { fireEvent, render } from "@testing-library/react";
import { NextIntlClientProvider } from "next-intl";
import type { Article } from "@/lib/types";
import fr from "@/messages/fr.json";
import { articleCategory, matchesTheme } from "./blogThemes";
import { BlogCover } from "./BlogCover";
import { BlogFilter } from "./BlogFilter";

vi.mock("@/i18n/navigation", () => ({
  Link: ({ href, children, ...rest }: { href: unknown; children: React.ReactNode }) => (
    <a href={typeof href === "string" ? href : JSON.stringify(href)} {...rest}>
      {children}
    </a>
  ),
}));

function art(id: number, slug: string, tags: string[]): Article {
  return { id, slug, title: `T ${slug}`, content: "<p>x</p>", tags: tags.map((name, i) => ({ id: id * 10 + i, name })), media: [] };
}

const auto = art(2, "automatiser", ["Automatisation", "Architecture"]);
const hello = art(1, "bienvenue", ["Présentation"]);
const cyberEn = art(3, "cyber-en", ["Cybersecurity"]);
const risk = art(4, "assure", ["Gestion des risques"]);

describe("blog themes (prototype pBlog)", () => {
  it("matches the prototype editorial themes, FR and EN", () => {
    expect(matchesTheme(auto, "architecture")).toBe(true);
    expect(matchesTheme(auto, "cyber")).toBe(false);
    expect(matchesTheme(hello, "ia")).toBe(true); // règle du prototype : IA ↔ Présentation
    expect(matchesTheme(cyberEn, "cyber")).toBe(true);
    expect(matchesTheme(risk, "risques")).toBe(true);
    expect(matchesTheme(risk, "tous")).toBe(true);
  });

  it("maps categories to the prototype covers (BCAT)", () => {
    expect(articleCategory(auto)).toBe("architecture");
    expect(articleCategory(hello)).toBe("presentation");
    expect(articleCategory(cyberEn)).toBe("cyber");
    expect(articleCategory(risk)).toBe("risques");
    expect(articleCategory(art(5, "x", ["Divers"]))).toBe("");
  });
});

describe("BlogCover", () => {
  it("draws the prototype illustration with the category hue and never injects tag text", () => {
    const evil = art(9, "evil", ['<img src=x onerror="alert(1)">Architecture']);
    const { container } = render(<BlogCover article={evil} sizes="100vw" />);
    expect(container.querySelector("img")).toBeNull();
    expect(container.innerHTML).not.toContain("onerror");
    expect(container.querySelector("stop")?.getAttribute("stop-color")).toBe("hsl(212 70% 42%)");
    expect(container.querySelectorAll("circle").length).toBeGreaterThanOrEqual(5);
    const rect = container.querySelector("rect");
    expect(rect?.getAttribute("fill")).toBe("url(#cv-evil-s)");
  });

  it("uses the Présentation hue (30) and left alignment when asked", () => {
    const { container } = render(<BlogCover article={hello} large alignLeft sizes="100vw" />);
    expect(container.querySelector("svg")?.getAttribute("preserveAspectRatio")).toBe("xMinYMid slice");
    expect(container.querySelector("stop")?.getAttribute("stop-color")).toBe("hsl(30 70% 42%)");
  });
});

describe("BlogFilter", () => {
  function setup(list: Article[]) {
    return render(
      <NextIntlClientProvider locale="fr" messages={fr}>
        <BlogFilter articles={list} excerpts={new Map()} head={<h1>Blog</h1>} />
      </NextIntlClientProvider>,
    );
  }

  it("shows the prototype themes in the header and features the most recent article", () => {
    const { getByRole, container } = setup([hello, auto]);
    const group = getByRole("group");
    expect([...group.querySelectorAll("button")].map((b) => b.textContent)).toEqual([
      "Tous les thèmes",
      "Architecture",
      "Cybersécurité",
      "Gestion des risques",
      "IA",
    ]);
    expect(container.querySelector(".ph .bfil")).not.toBeNull();
    expect(container.querySelector(".bfeat h2")?.textContent).toBe("T automatiser");
  });

  it("filters, shows the prototype empty state and resets", () => {
    const { getByText, container } = setup([hello, auto]);
    fireEvent.click(getByText("Cybersécurité"));
    expect(container.querySelector(".bfeat")).toBeNull();
    expect(getByText("Aucun article sur ce thème pour l’instant.")).toBeTruthy();
    fireEvent.click(getByText("Voir tous les articles"));
    expect(container.querySelector(".bfeat h2")?.textContent).toBe("T automatiser");
    fireEvent.click(getByText("IA"));
    expect(container.querySelector(".bfeat h2")?.textContent).toBe("T bienvenue");
  });
});
