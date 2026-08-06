"use client";

import { useEffect, useState } from "react";
import { useReducedMotion } from "@/lib/useReducedMotion";

interface RoleRotatorProps {
  roles: string[];
  intervalMs?: number;
}

/**
 * Fait défiler plusieurs intitulés (les différentes casquettes du profil) en
 * fondu, un seul à la fois. `minWidth` (en `ch`) évite que le badge parent ne
 * change trop brutalement de largeur d'un rôle à l'autre.
 */
export function RoleRotator({ roles, intervalMs = 2600 }: RoleRotatorProps) {
  const [index, setIndex] = useState(0);
  const [visible, setVisible] = useState(true);
  const reduce = useReducedMotion();

  useEffect(() => {
    if (reduce || roles.length <= 1) return;
    const fadeOut = setTimeout(() => setVisible(false), intervalMs - 250);
    const swap = setTimeout(() => {
      setIndex((i) => (i + 1) % roles.length);
      setVisible(true);
    }, intervalMs);
    return () => {
      clearTimeout(fadeOut);
      clearTimeout(swap);
    };
  }, [index, intervalMs, reduce, roles.length]);

  const maxLen = Math.max(...roles.map((role) => role.length));

  return (
    <span
      className="inline-block transition-opacity duration-[250ms] ease-out"
      style={{ opacity: visible ? 1 : 0, minWidth: `${maxLen}ch` }}
    >
      {roles[index]}
    </span>
  );
}
