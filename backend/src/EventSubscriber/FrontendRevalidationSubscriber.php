<?php

namespace App\EventSubscriber;

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
use App\Message\RevalidateFrontendMessage;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Déclenche la revalidation du cache ISR du frontend (webhook
 * /api/revalidate, cf. App\Service\FrontendRevalidator) à chaque création/
 * modification/suppression d'un contenu public — même principe que
 * AiAssistantContentChangeSubscriber : dispatch différé à postFlush plutôt
 * que dans postPersist/postUpdate/postRemove, pour ne pas mettre en file un
 * message correspondant à une écriture qui finirait par échouer (rollback)
 * plus loin dans le même flush. Les deux subscribers sont indépendants,
 * écoutent les mêmes événements Doctrine sans interagir l'un avec l'autre.
 *
 * postRemove est inclus (contrairement à AiAssistantContentChangeSubscriber,
 * qui n'a pas besoin de désindexer côté RAG) : supprimer un projet ou un
 * témoignage doit aussi rafraîchir le site public, pas seulement sa création
 * ou sa modification.
 */
class FrontendRevalidationSubscriber implements EventSubscriber
{
    /** @var array<string, true> déduplique si plusieurs entités du même tag changent dans un flush */
    private array $pendingTags = [];

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate, Events::postRemove, Events::postFlush];
    }

    /** @param LifecycleEventArgs<ObjectManager> $args */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->queue($args->getObject());
    }

    /** @param LifecycleEventArgs<ObjectManager> $args */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->queue($args->getObject());
    }

    /** @param LifecycleEventArgs<ObjectManager> $args */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->queue($args->getObject());
    }

    public function postFlush(): void
    {
        if ([] === $this->pendingTags) {
            return;
        }

        $tags = array_keys($this->pendingTags);
        $this->pendingTags = [];

        foreach ($tags as $tag) {
            $this->messageBus->dispatch(new RevalidateFrontendMessage($tag));
        }
    }

    private function queue(object $entity): void
    {
        // Une SkillCategory renommée invalide aussi "skills" : chaque Skill
        // embarque le nom de sa catégorie côté frontend (skill.skillCategory.name,
        // cf. frontend/src/lib/api/skills.ts).
        if ($entity instanceof SkillCategory) {
            $this->push('skill-categories');
            $this->push('skills');

            return;
        }

        // Un Tag renommé/supprimé invalide "articles" : Article::$tags (api_public)
        // est rendu sur /blog et /blog/[slug] (cf. frontend/src/components/sections/
        // ArticleCard.tsx). Project::$tags n'est pas exposé en api_public, donc pas
        // besoin d'invalider "projects" ici.
        if ($entity instanceof Tag) {
            $this->push('articles');

            return;
        }

        // Un Media appartient à UN SEUL des trois (project/article/testimonial,
        // cf. App\Entity\Media) — mais à la suppression, la relation peut déjà
        // être détachée avant le flush (Project::removeMedia() fait
        // $media->setProject(null) avant l'appel à EntityManager::remove(),
        // donc postRemove ne peut plus lire le propriétaire via le getter). Dans
        // ce cas on invalide les trois tags par prudence plutôt que de rater
        // silencieusement l'invalidation — même logique défensive que pour
        // SkillCategory ci-dessus.
        if ($entity instanceof Media) {
            $owner = $entity->getProject() ?? $entity->getArticle() ?? $entity->getTestimonial();
            $mediaTag = match (true) {
                $owner instanceof Project => 'projects',
                $owner instanceof Article => 'articles',
                $owner instanceof Testimonial => 'testimonials',
                default => null,
            };

            if (null === $mediaTag) {
                $this->push('projects');
                $this->push('articles');
                $this->push('testimonials');

                return;
            }

            $this->push($mediaTag);

            return;
        }

        $tag = match (true) {
            $entity instanceof Project, $entity instanceof ProjectInfo => 'projects',
            $entity instanceof Article => 'articles',
            $entity instanceof Experience => 'experiences',
            $entity instanceof Skill => 'skills',
            $entity instanceof HomeContent => 'home-content',
            $entity instanceof Testimonial => 'testimonials',
            $entity instanceof Course => 'courses',
            $entity instanceof AboutContent => 'about-content',
            $entity instanceof AiAssistantSettings, $entity instanceof AiAssistantEntry => 'ai-assistant',
            default => null,
        };

        if (null !== $tag) {
            $this->push($tag);
        }
    }

    private function push(string $tag): void
    {
        $this->pendingTags[$tag] = true;
    }
}
