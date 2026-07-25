import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink } from "@/components/ui";
import { getArticleBySlug } from "@/lib/api/articles";

export default async function ArticleDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const [article, t] = await Promise.all([
    getArticleBySlug(slug),
    getTranslations("blog"),
  ]);

  if (!article) {
    notFound();
  }

  return (
    <article className="px-6 py-16">
      <div className="mx-auto max-w-[760px]">
        {article.tags.length > 0 && (
          <div className="mb-4 flex flex-wrap gap-1.5">
            {article.tags.map((tag) => (
              <Badge key={tag.id} variant="outline">
                {tag.name}
              </Badge>
            ))}
          </div>
        )}
        <h1 className="mb-8 text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
          {article.title}
        </h1>
        {/* Contenu HTML rédigé côté admin Symfony (ROLE_ADMIN) — source de confiance, pas d'entrée utilisateur. */}
        <div
          className="article-body opacity-85"
          dangerouslySetInnerHTML={{ __html: article.content }}
        />
        <div className="mt-10 border-t border-[var(--border-softer)] pt-6">
          <ButtonLink href="/blog" variant="secondary">
            {t("eyebrow")} ←
          </ButtonLink>
        </div>
      </div>
    </article>
  );
}
