"use client";

import { useTranslations } from "next-intl";
import { Badge, Card } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import type { Article } from "@/lib/types";

function excerpt(html: string, length = 140) {
  const text = html.replace(/<[^>]+>/g, "");
  return text.length > length ? `${text.slice(0, length)}…` : text;
}

export function ArticleCard({ article }: { article: Article }) {
  const tc = useTranslations("common");

  return (
    <Card className="flex flex-col overflow-hidden p-0">
      <div className="flex h-[120px] items-center justify-center bg-brand-light px-4 text-center">
        <span className="font-mono text-xs text-brand-dark/55">
          {article.title}
        </span>
      </div>
      <div className="flex flex-1 flex-col p-5">
        {article.tags.length > 0 && (
          <div className="mb-2 flex flex-wrap gap-1.5">
            {article.tags.map((tag) => (
              <Badge key={tag.id} variant="outline">
                {tag.name}
              </Badge>
            ))}
          </div>
        )}
        <div
          className="mb-2 text-base font-semibold"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          {article.title}
        </div>
        <p className="flex-1 text-sm opacity-70">{excerpt(article.content)}</p>
        <Link
          href={{ pathname: "/blog/[slug]", params: { slug: article.slug } }}
          className="mt-3 text-sm font-semibold text-brand-primary hover:underline"
        >
          {tc("readMore")} →
        </Link>
      </div>
    </Card>
  );
}
