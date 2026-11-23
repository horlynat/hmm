"use client";

import { useEffect, useState } from "react";

/** Liseré de progression de lecture du prototype (`.rprog i`, pleine page). */
export function ArticleProgress() {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    function onScroll() {
      const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
      const max = scrollHeight - clientHeight;
      setProgress(max > 0 ? Math.min(1, Math.max(0, scrollTop / max)) : 0);
    }
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    return () => {
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
    };
  }, []);

  return (
    <div className="rprog" aria-hidden="true">
      <i style={{ width: `${progress * 100}%` }} />
    </div>
  );
}
