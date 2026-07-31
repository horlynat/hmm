import { useSyncExternalStore } from "react";

function subscribe(callback: () => void) {
  window.addEventListener("scroll", callback, { passive: true });
  return () => window.removeEventListener("scroll", callback);
}

function getSnapshot() {
  return window.scrollY > 8;
}

function getServerSnapshot() {
  return false;
}

/** `useSyncExternalStore` (pas de `setState` dans un effet) pour le fond du header. */
export function useScrolled(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
