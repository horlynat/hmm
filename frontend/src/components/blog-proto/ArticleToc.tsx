"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import type { ArticleSection } from "@/lib/articleToc";

/**
 * Sommaire de l'article (`.atoc`), surbrillance de la section visible
 * (IntersectionObserver, motif `.atoc a.on`) et mini-barre de progression
 * propre au corps de l'article (`.atob i`), en plus du liseré pleine page
 * (ArticleProgress).
 */
export function ArticleToc({ sections, minutes }: { sections: ArticleSection[]; minutes: number }) {
  const t = useTranslations("blogPage");
  const [active, setActive] = useState<string | undefined>(sections[0]?.id);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    if (sections.length === 0) return;
    const elements = sections.map((s) => document.getElementById(s.id)).filter((el): el is HTMLElement => el !== null);
    if (elements.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((e) => e.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible[0]) setActive(visible[0].target.id);
      },
      { rootMargin: "-110px 0px -65% 0px" },
    );
    for (const el of elements) observer.observe(el);
    return () => observer.disconnect();
  }, [sections]);

  useEffect(() => {
    const body = document.getElementById("article-body");
    if (!body) return;
    function onScroll() {
      if (!body) return;
      const rect = body.getBoundingClientRect();
      const total = rect.height - window.innerHeight * 0.5;
      const passed = -rect.top;
      setProgress(total > 0 ? Math.min(1, Math.max(0, passed / total)) : 0);
    }
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    return () => {
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
    };
  }, []);

  if (sections.length === 0) return null;

  return (
    <nav className="atoc" aria-label={t("toc")}>
      <div className="atoh">
        <b>{t("toc")}</b>
        <small>{t("readingTime", { minutes })}</small>
      </div>
      <div className="atob" aria-hidden="true">
        <i style={{ width: `${progress * 100}%` }} />
      </div>
      <ol>
        {sections.map((s) => (
          <li key={s.id}>
            <a href={`#${s.id}`} className={active === s.id ? "on" : undefined} aria-current={active === s.id ? "location" : undefined}>
              {s.heading}
            </a>
          </li>
        ))}
      </ol>
    </nav>
  );
}
