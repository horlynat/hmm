<?php

namespace App\Repository;

use App\Entity\Translation;
use App\Service\BilingualFieldReflector;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Translation>
 *
 * Lit/écrit les valeurs "xxxEn" des entités bilingues (HomeContent,
 * AboutContent, Project, Article) depuis/vers la table `translation`
 * générique, en s'appuyant sur BilingualFieldReflector pour retrouver les
 * paires de champs par réflexion — aucune de ces 4 entités ne connaît elle-
 * même la table `translation`.
 */
class TranslationRepository extends ServiceEntityRepository
{
    private const LOCALE = 'en'; // seule locale traduite aujourd'hui (le français reste natif sur l'entité)

    public function __construct(
        ManagerRegistry $registry,
        private readonly BilingualFieldReflector $reflector,
    ) {
        parent::__construct($registry, Translation::class);
    }

    /**
     * Peuple les propriétés "xxxEn" (transitoires, non mappées Doctrine) de
     * $entity à partir des lignes de traduction existantes. Appelé
     * automatiquement au chargement — cf. App\EventListener\TranslationHydrationListener.
     */
    public function hydrateEntity(object $entity): void
    {
        $id = $this->entityId($entity);
        if (null === $id) {
            return;
        }

        $pairs = $this->reflector->discoverPairs($entity);
        if (!$pairs) {
            return;
        }

        $rows = $this->createQueryBuilder('t')
            ->andWhere('t.entityType = :type')
            ->andWhere('t.entityId = :id')
            ->andWhere('t.locale = :locale')
            ->setParameter('type', $this->entityType($entity))
            ->setParameter('id', $id)
            ->setParameter('locale', self::LOCALE)
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $field = $row->getField();
            if (!isset($pairs[$field])) {
                continue; // ligne orpheline (champ renommé/supprimé depuis) : ignorée plutôt que planter
            }

            $value = $row->getValue();
            if ($this->reflector->isArrayField($pairs[$field]['enSetter'])) {
                $decoded = null !== $value ? json_decode($value, true) : null;
                $value = is_array($decoded) ? $decoded : null;
            }

            $pairs[$field]['enSetter']->invoke($entity, $value);
        }
    }

    /**
     * Écrit les valeurs "xxxEn" actuelles de $entity dans la table
     * `translation` (upsert par champ — une ligne absente ou vide est
     * supprimée plutôt que laissée à '' ). Appelé explicitement par les
     * contrôleurs admin après `flush()` (a besoin de l'id, donc après
     * persistance pour une entité neuve).
     */
    public function syncFromEntity(object $entity): void
    {
        $id = $this->entityId($entity);
        if (null === $id) {
            return;
        }

        $type = $this->entityType($entity);
        $pairs = $this->reflector->discoverPairs($entity);
        $em = $this->getEntityManager();

        foreach ($pairs as $field => $pair) {
            $value = $pair['enGetter']->invoke($entity);
            $raw = $this->reflector->isArrayField($pair['enSetter'])
                ? (is_array($value) && $value ? json_encode($value, JSON_UNESCAPED_UNICODE) : null)
                : (is_string($value) && '' !== trim($value) ? $value : null);

            $existing = $this->findOneBy(['entityType' => $type, 'entityId' => $id, 'field' => $field, 'locale' => self::LOCALE]);

            if (null === $raw) {
                if (null !== $existing) {
                    $em->remove($existing);
                }
                continue;
            }

            if (null === $existing) {
                $existing = (new Translation())
                    ->setEntityType($type)
                    ->setEntityId($id)
                    ->setField($field)
                    ->setLocale(self::LOCALE);
                $em->persist($existing);
            }
            $existing->setValue($raw);
        }

        $em->flush();
    }

    private function entityType(object $entity): string
    {
        $parts = explode('\\', $entity::class);

        return end($parts);
    }

    private function entityId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            return null;
        }
        $id = $entity->getId();

        return is_int($id) ? $id : null;
    }
}
