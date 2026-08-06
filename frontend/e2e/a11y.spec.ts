import { test, expect, type Page } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

/**
 * Scan axe-core sur les routes clés. Sert de baseline mesurée avant les
 * correctifs a11y (cf. plan) — les violations remontées ici pilotent les
 * corrections, plutôt que de deviner à partir du code (ex. classes opacity-*).
 */

async function scan(page: Page) {
  return new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa"]).analyze();
}

test("accueil — aucune violation axe critique/sérieuse", async ({ page }) => {
  await page.goto("/fr");
  const results = await scan(page);
  const blocking = results.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious",
  );
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
});

test("compétences — aucune violation axe critique/sérieuse", async ({ page }) => {
  await page.goto("/fr/competences");
  const results = await scan(page);
  const blocking = results.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious",
  );
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
});

test("blog — liste et un article réel — aucune violation axe critique/sérieuse", async ({
  page,
}) => {
  await page.goto("/fr/blog");
  const listResults = await scan(page);
  const listBlocking = listResults.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious",
  );
  expect(listBlocking, JSON.stringify(listBlocking, null, 2)).toEqual([]);

  const firstArticle = page.locator("main a[href*='/blog/']").first();
  if ((await firstArticle.count()) === 0) {
    test.skip(true, "Aucun article renvoyé par l'API — backend indisponible");
  }
  await firstArticle.click();
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  const articleResults = await scan(page);
  const articleBlocking = articleResults.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious",
  );
  expect(articleBlocking, JSON.stringify(articleBlocking, null, 2)).toEqual([]);
});

test("contact — mode devis — aucune violation axe critique/sérieuse", async ({
  page,
}) => {
  await page.goto("/fr/contact");
  const results = await scan(page);
  const blocking = results.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious",
  );
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
});

test("contact — mode rendez-vous — aucune violation axe critique/sérieuse", async ({
  page,
}) => {
  await page.goto("/fr/contact");
  await page.getByRole("button", { name: /planifier/i }).click();
  const results = await scan(page);
  const blocking = results.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious",
  );
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
});
