<?php

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\Experience;
use App\Entity\Project;
use App\Entity\Skill;
use App\Entity\SkillCategory;
use App\Message\AiAssistantIngestMessage;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Déclenche la (ré)ingestion RAG (App\Message\AiAssistantIngestMessage) à
 * chaque création/modification d'une entité Project/Article/Experience/
 * SkillCategory — même principe que AlertNotificationSubscriber : dispatch
 * différé à postFlush plutôt que dans postPersist/postUpdate, pour ne pas
 * mettre en file un message correspondant à une écriture qui finirait par
 * échouer (rollback) plus loin dans le même flush.
 *
 * Un Skill n'est pas ingéré individuellement (cf. AiAssistantIngestionService)
 * : modifier un Skill redéclenche l'ingestion de sa SkillCategory parente,
 * pour que le chunk agrégé reflète le nouveau niveau/nom.
 */
class AiAssistantContentChangeSubscriber implements EventSubscriber
{
    /** @var array<string, array{entityType: string, entityId: int}> déduplique si une même entité change plusieurs fois dans un flush */
    private array $pending = [];

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate, Events::postFlush];
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

    public function postFlush(): void
    {
        if ([] === $this->pending) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $item) {
            $this->messageBus->dispatch(new AiAssistantIngestMessage($item['entityType'], $item['entityId']));
        }
    }

    private function queue(object $entity): void
    {
        if ($entity instanceof Skill) {
            $category = $entity->getSkillCategory();
            if (null === $category->getId()) {
                return;
            }
            $this->push('SkillCategory', $category->getId());

            return;
        }

        $entityType = match (true) {
            $entity instanceof Project => 'Project',
            $entity instanceof Article => 'Article',
            $entity instanceof Experience => 'Experience',
            $entity instanceof SkillCategory => 'SkillCategory',
            default => null,
        };

        if (null === $entityType || null === $entity->getId()) {
            return;
        }

        $this->push($entityType, $entity->getId());
    }

    private function push(string $entityType, int $entityId): void
    {
        $this->pending[$entityType . '#' . $entityId] = ['entityType' => $entityType, 'entityId' => $entityId];
    }
}
