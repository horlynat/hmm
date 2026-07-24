import { test, expect } from "@playwright/test";

/**
 * Ces parcours doivent réussir même si l'API backend est inaccessible (cf.
 * plan : le blocage `access_control` renverra 401 tant qu'il n'est pas
 * corrigé côté backend) — apiFetch doit alors renvoyer un état vide géré
 * proprement par la page, jamais un crash.
 */

test("la page d'accueil se charge et redirige vers la locale par défaut", async ({
  page,
}) => {
  await page.goto("/");
  await expect(page).toHaveURL(/\/fr/);
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
});

test("la page réalisations se charge malgré une API indisponible", async ({
  page,
}) => {
  const response = await page.goto("/fr/realisations");
  expect(response?.ok()).toBeTruthy();
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
});

test("le wizard de devis se rend et avance à l'étape 2", async ({ page }) => {
  await page.goto("/fr/contact");
  await page.getByText("Développement web", { exact: true }).click();
  await page.getByRole("button", { name: "Suivant" }).click();
  await expect(page.getByText("Étape 2 sur 7")).toBeVisible();
});
