import { describe, expect, it } from "vitest";
import { renderToString } from "react-dom/server";
import MarketingTemplate from "./template";

/**
 * Le HTML serveur ne doit jamais masquer la page : le fondu d'entrée est une
 * animation CSS, pas un état React qui ne passe à « visible » qu'après
 * hydratation (page noire tant que le JS n'a pas tourné, invisible s'il
 * échoue).
 */
describe("MarketingTemplate", () => {
  it("rend le contenu visible dès le HTML serveur, sans dépendre du JavaScript", () => {
    const html = renderToString(
      <MarketingTemplate>
        <p>Contenu</p>
      </MarketingTemplate>,
    );

    expect(html).toContain("Contenu");
    expect(html).toContain('class="page-fade-in"');
    expect(html).not.toMatch(/opacity\s*:\s*0/);
    expect(html).not.toMatch(/visibility\s*:\s*hidden/);
  });
});
