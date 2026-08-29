<?php

namespace App\Tests\Doctrine;

use App\Doctrine\RichTextSanitizerListener;
use App\Entity\Article;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Vérifie que le listener appelle bien le sanitizer sur les champs HTML riche,
 * à la création comme à la modification — la liste blanche exacte est testée
 * par Symfony (composant html-sanitizer), on se contente ici d'un sanitizer
 * réel minimal (autorise <strong>, retire <script>).
 */
final class RichTextSanitizerListenerTest extends TestCase
{
    private function listener(): RichTextSanitizerListener
    {
        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig())->allowElement('strong'),
        );

        return new RichTextSanitizerListener($sanitizer);
    }

    public function testPrePersistSanitizesArticleBody(): void
    {
        $article = (new Article())->setContent('<strong>ok</strong><script>alert(1)</script>');

        $this->listener()->prePersist(new PrePersistEventArgs($article, $this->createStub(EntityManagerInterface::class)));

        self::assertStringNotContainsString('<script>', $article->getContent());
        self::assertStringContainsString('<strong>ok</strong>', $article->getContent());
    }

    public function testPrePersistSanitizesProjectDescriptionAndEnglishVariant(): void
    {
        $project = (new Project())
            ->setDescription('<script>evil()</script><strong>fr</strong>')
            ->setDescriptionEn('<img src=x onerror=alert(1)><strong>en</strong>');

        $this->listener()->prePersist(new PrePersistEventArgs($project, $this->createStub(EntityManagerInterface::class)));

        self::assertStringNotContainsString('<script>', $project->getDescription());
        self::assertStringNotContainsString('onerror', (string) $project->getDescriptionEn());
    }

    public function testPreUpdateOnlyRewritesChangedFields(): void
    {
        $article = (new Article())->setContent('<strong>x</strong>');
        $changeSet = ['content' => ['<strong>old</strong>', '<strong>new</strong><script>x</script>']];

        $args = new PreUpdateEventArgs($article, $this->createStub(EntityManagerInterface::class), $changeSet);
        $this->listener()->preUpdate($args);

        self::assertStringNotContainsString('<script>', (string) $args->getNewValue('content'));
        self::assertStringContainsString('<strong>new</strong>', (string) $args->getNewValue('content'));
    }

    public function testIgnoresUnrelatedEntities(): void
    {
        $this->listener()->prePersist(new PrePersistEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class)));

        $this->addToAssertionCount(1);
    }
}
