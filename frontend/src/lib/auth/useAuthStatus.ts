"use client";

import { useEffect, useState } from "react";
import { getAuthStatus } from "./actions";

/**
 * Statut de connexion résolu après montage (Server Action), pas via
 * `cookies()` pendant le rendu : sinon le layout partagé forcerait toutes
 * les pages (y compris `force-static`) en rendu dynamique. `null` tant que
 * la résolution est en cours — permet d'afficher un skeleton plutôt qu'un
 * état "déconnecté" trompeur qui flashe.
 */
export function useAuthStatus(): boolean | null {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);

  useEffect(() => {
    let active = true;
    getAuthStatus().then((status) => {
      if (active) setIsAuthenticated(status);
    });
    return () => {
      active = false;
    };
  }, []);

  return isAuthenticated;
}
