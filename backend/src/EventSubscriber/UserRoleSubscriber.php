<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;

class UserRoleSubscriber implements EventSubscriber
{
    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate, // si tu veux aussi gérer la modification
        ];
    }

    /** @param LifecycleEventArgs<ObjectManager> $args */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof User) {
            return;
        }

        if ($entity->getEmail() === 'horlynat@gmail.com') {
            $entity->setRoles(['ROLE_ADMIN']);
        } else {
            $entity->setRoles(['ROLE_USER']);
        }
    }

    /** @param LifecycleEventArgs<ObjectManager> $args */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof User) {
            return;
        }

        if ($entity->getEmail() === 'horlynat@gmail.com') {
            $entity->setRoles(['ROLE_ADMIN']);
        } else {
            $entity->setRoles(['ROLE_USER']);
        }
    }
}
