<?php

namespace App\Tests\EventSubscriber;

use App\Entity\AboutContent;
use App\Entity\AiAssistantEntry;
use App\Entity\AiAssistantSettings;
use App\Entity\Article;
use App\Entity\Course;
use App\Entity\Experience;
use App\Entity\HomeContent;
use App\Entity\Media;
use App\Entity\Project;
use App\Entity\ProjectInfo;
use App\Entity\Skill;
use App\Entity\SkillCategory;
use App\Entity\Tag;
use App\Entity\Testimonial;
use App\Entity\User;
use App\EventSubscriber\FrontendRevalidationSubscriber;
use App\Message\RevalidateFrontendMessage;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class FrontendRevalidationSubscriberTest extends TestCase
{
    /** @return LifecycleEventArgs<ObjectManager> */
    private function createArgs(object $entity): LifecycleEventArgs
    {
        return new LifecycleEventArgs($entity, $this->createStub(ObjectManager::class));
    }

    /** @param string[] $expectedTags */
    #[DataProvider('entityTagProvider')]
    public function testPostPersistThenFlushDispatchesExpectedTag(object $entity, array $expectedTags): void
    {
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (RevalidateFrontendMessage $message) use (&$dispatched) {
            $dispatched[] = $message->tag;

            return new Envelope($message);
        });

        $subscriber = new FrontendRevalidationSubscriber($bus);
        $subscriber->postPersist($this->createArgs($entity));
        $subscriber->postFlush();

        sort($dispatched);
        $expected = $expectedTags;
        sort($expected);
        $this->assertSame($expected, $dispatched);
    }

    /**
     * @return iterable<string, array{0: object, 1: string[]}>
     */
    public static function entityTagProvider(): iterable
    {
        yield 'Project' => [new Project(), ['projects']];
        yield 'ProjectInfo' => [new ProjectInfo(), ['projects']];
        yield 'Article' => [new Article(), ['articles']];
        yield 'Tag (invalide articles)' => [new Tag(), ['articles']];
        yield 'Experience' => [new Experience(), ['experiences']];
        yield 'Skill' => [new Skill(), ['skills']];
        yield 'SkillCategory (invalide aussi skills)' => [new SkillCategory(), ['skill-categories', 'skills']];
        yield 'HomeContent' => [new HomeContent(), ['home-content']];
        yield 'Testimonial' => [new Testimonial(), ['testimonials']];
        yield 'Course' => [new Course(), ['courses']];
        yield 'AboutContent' => [new AboutContent(), ['about-content']];
        yield 'AiAssistantSettings' => [new AiAssistantSettings(), ['ai-assistant']];
        yield 'AiAssistantEntry' => [new AiAssistantEntry(), ['ai-assistant']];
        yield 'Media rattaché à un Project' => [(new Media())->setProject(new Project()), ['projects']];
        yield 'Media rattaché à un Article' => [(new Media())->setArticle(new Article()), ['articles']];
        yield 'Media rattaché à un Testimonial' => [(new Media())->setTestimonial(new Testimonial()), ['testimonials']];
    }

    public function testUnrelatedEntityIsIgnored(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = new FrontendRevalidationSubscriber($bus);
        $subscriber->postPersist($this->createArgs(new User()));
        $subscriber->postFlush();
    }

    public function testMultipleChangesOfTheSameTagInOneFlushDispatchOnlyOnce(): void
    {
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (RevalidateFrontendMessage $message) use (&$dispatched) {
            $dispatched[] = $message->tag;

            return new Envelope($message);
        });

        $subscriber = new FrontendRevalidationSubscriber($bus);
        $subscriber->postPersist($this->createArgs(new Project()));
        $subscriber->postUpdate($this->createArgs(new Project()));
        $subscriber->postPersist($this->createArgs(new Project()));
        $subscriber->postFlush();

        $this->assertSame(['projects'], $dispatched);
    }

    public function testPostRemoveAlsoTriggersRevalidation(): void
    {
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (RevalidateFrontendMessage $message) use (&$dispatched) {
            $dispatched[] = $message->tag;

            return new Envelope($message);
        });

        $subscriber = new FrontendRevalidationSubscriber($bus);
        $subscriber->postRemove($this->createArgs(new Testimonial()));
        $subscriber->postFlush();

        $this->assertSame(['testimonials'], $dispatched);
    }

    /**
     * Reproduit App\Controller\Admin\AdminProjectController::deleteMedia() :
     * Project::removeMedia() nullifie $media->project AVANT que
     * EntityManager::remove()/flush() ne déclenchent postRemove -- à ce
     * stade, plus aucun getter n'indique le propriétaire d'origine. Le
     * subscriber doit alors invalider les trois tags par prudence plutôt que
     * de ne rien invalider du tout.
     */
    public function testMediaRemovedWithoutResolvableOwnerInvalidatesAllThreeTags(): void
    {
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (RevalidateFrontendMessage $message) use (&$dispatched) {
            $dispatched[] = $message->tag;

            return new Envelope($message);
        });

        $media = new Media(); // ni project, ni article, ni testimonial

        $subscriber = new FrontendRevalidationSubscriber($bus);
        $subscriber->postRemove($this->createArgs($media));
        $subscriber->postFlush();

        sort($dispatched);
        $this->assertSame(['articles', 'projects', 'testimonials'], $dispatched);
    }

    public function testEmptyFlushDispatchesNothing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = new FrontendRevalidationSubscriber($bus);
        $subscriber->postFlush();
    }

    public function testSubscribedEventsIncludePostRemove(): void
    {
        $subscriber = new FrontendRevalidationSubscriber($this->createStub(MessageBusInterface::class));

        $this->assertSame(
            ['postPersist', 'postUpdate', 'postRemove', 'postFlush'],
            $subscriber->getSubscribedEvents(),
        );
    }
}
