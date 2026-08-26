"use client";

import { useTranslations } from "next-intl";
import Image from "next/image";
import { Badge, Card, ViewTransitionLink } from "@/components/ui";
import { getMediaUrl } from "@/lib/media";
import { getExcerpt } from "@/lib/text";
import { articleImageTransitionName } from "@/lib/viewTransitionNames";
import type { Article } from "@/lib/types";

export function ArticleCard({ article }: { article: Article }) {
  const tc = useTranslations("common");
  const image = article.media.find((m) => m.type === "image");

  return (
    <Card className="flex flex-col overflow-hidden p-0">
      {image ? (
        <div
          className="vt-target relative aspect-[14/5] w-full bg-brand-light"
          style={{ viewTransitionName: articleImageTransitionName(article.id) }}
        >
          <Image
            src={getMediaUrl(image.filePath)}
            alt={image.altText ?? article.title}
            fill
            sizes="(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw"
            className="object-cover"
          />
        </div>
      ) : (
        <div className="flex h-[130px] items-center justify-center bg-brand-light px-4 text-center">
          <span className="font-mono text-xs text-[var(--color-on-brand-light)]">
            {article.title}
          </span>
        </div>
      )}
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
        <p className="flex-1 text-sm opacity-70">{getExcerpt(article.content, 140)}</p>
        <ViewTransitionLink
          href={{ pathname: "/blog/[slug]", params: { slug: article.slug } }}
          viewTransitionName={image ? articleImageTransitionName(article.id) : undefined}
          className="mt-3 text-sm font-semibold text-brand-primary hover:underline"
        >
          {tc("readMore")} →
        </ViewTransitionLink>
      </div>
    </Card>
  );
}
