"use server";

import { cookies } from "next/headers";
import { revalidatePath } from "next/cache";
import { CURRENCY_COOKIE, isCurrency, type Currency } from "./config";

/**
 * Pose la préférence de devise en cookie (lisible côté serveur, contrairement
 * au thème en localStorage) et force le re-rendu des pages déjà en cache
 * (montants convertis dans /compte/*).
 */
export async function setCurrency(currency: Currency): Promise<void> {
  if (!isCurrency(currency)) return;

  const store = await cookies();
  store.set(CURRENCY_COOKIE, currency, {
    path: "/",
    maxAge: 60 * 60 * 24 * 365,
    sameSite: "lax",
  });

  revalidatePath("/", "layout");
}
