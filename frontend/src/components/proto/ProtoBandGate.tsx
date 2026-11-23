"use client";

import { usePathname } from "@/i18n/navigation";
import { ProtoBand } from "./ProtoBand";

/** Bandeau « freelance » : sur toutes les pages sauf Connexion et Contact (comme footer() du prototype). */
export function ProtoBandGate() {
  const pathname = usePathname();
  if (pathname === "/connexion" || pathname === "/contact") return null;
  return <ProtoBand />;
}
